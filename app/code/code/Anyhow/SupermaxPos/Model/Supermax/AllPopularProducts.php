<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class AllPopularProducts implements \Anyhow\SupermaxPos\Api\Supermax\AllPopularProductsInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\Collection $supermaxOutlet
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->supermaxOutlet = $supermaxOutlet;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function getAllPopularProducts()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $connection = $this->resource->getConnection();             
                $storeId = null;
                $userOutletId = null;
                $userId =$this->supermaxSession->getPosUserId();

                if(!empty($userId)){
                    $userTable = $this->resource->getTableName('ah_supermax_pos_user');
                    $userDatas = $connection->query("SELECT * FROM $userTable WHERE pos_user_id = $userId");
                    if(!empty($userDatas)){
                        foreach($userDatas as $userData){
                            $storeId = $userData['store_view_id'];
                            $userOutletId = $userData['pos_outlet_id'];
                        }
                    }
                }

                // popular products limit.
                $limit = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_product_configutaion/ah_supermax_pos_no_of_visible_popular_products', $storeId);
                if(empty($limit)){
                    $limit = 20;
                }

                $salesOrderItemTable = $this->resource->getTableName('sales_order_item');
                $query = "SELECT COUNT(product_id) as sold, product_id FROM $salesOrderItemTable";
                
                // get product Assignment Basis and product ids array accordingly.
                $outletProductData = array();
                $userOutlet = $this->supermaxOutlet->addFieldToFilter('pos_outlet_id', $userOutletId);
                $outletData = $userOutlet->getData();
                $assignmentCriteria = "";
                if(!empty($outletData)) {
                    $assignmentCriteria = $outletData[0]['product_assignment_basis'];
                }
                if($assignmentCriteria == 'category'){
                    $categoryData = $connection->select();
                    $categoryData->from(
                        ['scto' => $this->resource->getTableName('ah_supermax_pos_category_to_outlet')],
                        ['category_id']
                    )->joinLeft(
                        ['ccp' => $this->resource->getTableName('catalog_category_product')],
                        "scto.category_id = ccp.category_id",
                        ['product_id']
                    );
                    $categoryProductCollection = $connection->query($categoryData)->fetchAll();
                    foreach($categoryProductCollection as $categoryProduct){
                        $outletProductData[] =  (int)$categoryProduct['product_id'];
                    }
                    if(!empty($outletProductData)){
                        $query .=" WHERE product_id IN(".implode(',',$outletProductData).")";
                    }
                } elseif($assignmentCriteria == 'product') {
                    $outletProductTableName = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
                    $outletProductDatas = $connection->query("SELECT product_id FROM $outletProductTableName WHERE parent_outlet_id = $userOutletId ")->fetchAll();
                    if(!empty($outletProductDatas)){
                        foreach($outletProductDatas as $product){
                            $outletProductData[] =  (int)$product['product_id'];
                        }
                    }


                    if(!empty($outletProductData)){
                        $query .=" WHERE product_id IN(".implode(',',$outletProductData).")";
                    }
                } 
                $query .= " GROUP BY product_id ORDER BY COUNT(product_id) DESC LIMIT $limit";
                $salesOrderDatas = $connection->query($query)->fetchAll();

                if(!empty($salesOrderDatas)){
                    foreach($salesOrderDatas as $salesOrderData){
                        $result[] = array(
                            'product_id' => (int)$salesOrderData['product_id'],
                            'sold' => (int)$salesOrderData['sold']
                        );
                    }
                }
                
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}