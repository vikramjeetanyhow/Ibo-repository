<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Barcode;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Assign extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Backend\Model\View\Result\Redirect $resultRedirect,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency
    ) {
        parent::__construct($context);
		$this->resource = $resourceConnection;
		$this->resultRedirect = $resultRedirect;
        $this->supermaxSession = $supermaxSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->currency = $currency;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $outletId = $data['outlet_id'];
        $barcode = $data['barcode-input'];
        $error = '';
        $message = '';
        $productDirectoryPath = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product/';
        $storeCurrencyCode = $this->storeManager->getStore()->getBaseCurrencyCode();
        $baseCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        $connection= $this->resource->getConnection();

        $attributeTableName = $this->resource->getTableName('eav_attribute');
        $attributeNameIds = $connection->query("SELECT attribute_id FROM $attributeTableName Where attribute_code = 'name' ");
        foreach($attributeNameIds as $attrib){
            $attribName = $attrib['attribute_id'];
        }
        $attributeStatusIds = $connection->query("SELECT attribute_id FROM $attributeTableName Where attribute_code = 'status' ");
        foreach($attributeStatusIds as $attrib){
            $attribStatus = $attrib['attribute_id'];
        }
        $attributePriceIds = $connection->query("SELECT attribute_id FROM $attributeTableName Where attribute_code = 'price' ");
        foreach($attributePriceIds as $attrib){
            $attribPrice = $attrib['attribute_id'];
        }
        $attributeThumbIds = $connection->query("SELECT attribute_id FROM $attributeTableName Where attribute_code = 'thumbnail' ");
        foreach($attributeThumbIds as $attrib){
            $attribThumb = $attrib['attribute_id'];
        }
        $productId = null;
        $productSku = '';
        $productName = '';
        $productPrice = null;
        $productImage = '';
        $productStatus = '';
        $productBarcode = '';
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        try {
            $outletData = $connection->query("SELECT product_assignment_basis FROM $outletTable WHERE pos_outlet_id = $outletId")->fetchAll();
            if(!empty($outletData)){
                foreach($outletData as $outlet){
                    $productAssignmentBasis = $outlet['product_assignment_basis'];
                    if($productAssignmentBasis == 'product'){

                        $productDataCollection = $this->productData($barcode, $attribName, $attribStatus, $attribPrice, $attribThumb);
                        $product = $connection->query($productDataCollection)->fetch();
                        if(!empty($product)) {
                            $productId = $product['entity_id'];
                            $productSku = $product['sku'];
                            $productName = $product['name'];
                            $productPrice = $baseCurrencySymbol.number_format((float)$product['price'],2);
                            $productImage = $productDirectoryPath.$product['thumbnail'];
                            if($product['status']==1){
                                $productStatus = 'Enabled'; 
                            }else{
                                $productStatus = 'Disabled'; 
                            }
                            $productBarcode = $product['barcode'];
                            $message = 'You have successfully assigned record(s)';
                        } else {
                            $error = 'There is no product associated with this barcode';
                        }
                    } else {
                        $error = 'You did not save "Product Assignment basis" as "Product based".';
                    }
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $error = $e;
        } catch (\Exception $e) {
            $error = 'Something went wrong while assigning the product(s).';
        }
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData(['data' => $message, 'error'=> $error, 'productid'=> $productId, 'productimage'=> $productImage, 'productname'=> $productName, 'productprice'=> $productPrice, 'productsku'=> $productSku, 'productstatus'=> $productStatus, 'productbarcode'=> $productBarcode]);
    }

    public function productData($barcode, $attribName, $attribStatus, $attribPrice, $attribThumb){
        $connection= $this->resource->getConnection();
        $select = $connection->select();
        $select->from(['main_table' => $this->resource->getTableName('catalog_product_entity')],
        ['sku', 'entity_id', 'barcode']
        )->joinLeft(
            ['cpev' => $this->resource->getTableName('catalog_product_entity_varchar')], 
            "main_table.entity_id = cpev.entity_id AND cpev.store_id = 0 AND cpev.attribute_id = $attribName",
            ['name'=> 'value']
        )->joinLeft(
            ['cpei' =>$this->resource->getTableName('catalog_product_entity_int')], 
            "main_table.entity_id = cpei.entity_id AND cpei.store_id = 0 AND cpei.attribute_id = $attribStatus",
            ['status'=> 'value']
        )->joinLeft(
            ['cped' => $this->resource->getTableName('catalog_product_entity_decimal')], 
            "main_table.entity_id = cped.entity_id AND cped.store_id = 0 AND cped.attribute_id = $attribPrice",
            ['price'=> 'value']
        )->joinLeft(
            ['cpevv' => $this->resource->getTableName('catalog_product_entity_varchar')], 
            "main_table.entity_id = cpevv.entity_id AND cpevv.store_id = 0 AND cpevv.attribute_id =  $attribThumb",
            ['thumbnail'=> 'value']
        )->where("main_table.barcode = '$barcode'");

        return $select;
    }
}
