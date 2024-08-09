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

use Magento\Framework\DataObject;

class Tax extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\TaxInterface
{    
    public function __construct( 
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Tax\Model\TaxClass\Source\Product $productTaxClassSource,
        \Magento\Tax\Model\TaxClass\Source\Customer $customerTaxClassSource,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
        $this->productTaxClassSource = $productTaxClassSource;
        $this->customerTaxClassSource = $customerTaxClassSource;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function getAllTaxes()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {

                $taxes = array();
                $tax_result = array();
                $connection= $this->resource->getConnection();
                $params = $this->helper->getParams();

                if(!empty($params)){
                    $countryId = trim($params['country_id'] ?? null);
                    $regionId = (int)trim($params['region_id'] ?? 0);
                    $postcode = (int)trim($params['postcode'] ?? null);
                }else{
                    $userId = $this->supermaxSession->getPosUserId();
                    $joinOutletData = $this->joinOutletData($connection, $userId);
                    $outletAddresses = $connection->query($joinOutletData);

                    if(!empty($outletAddresses)){
                        foreach($outletAddresses as $outletAddress){
                            if($outletAddress['outlet_address_type'] == 1){
                                $countryId = $outletAddress['country_id'];
                                $regionId = (int)$outletAddress['region_id'];
                                $postcode = (int)$outletAddress['postcode'];
                            }else{
                                $storeId = $outletAddress['store_view_id'];
                                $countryId = $this->helper->getConfig('general/store_information/country_id', $storeId);
                                $regionId = (int)$this->helper->getConfig('general/store_information/region_id', $storeId);
                                $postcode = (int)$this->helper->getConfig('general/store_information/postcode', $storeId);
                            }
                        }
                    }
                }

                $joinTaxData = $this->joinTaxCalculation($connection, $countryId, $regionId, $postcode);
                $taxCalculations = $connection->query($joinTaxData);

                if(!empty($taxCalculations)) {
                    foreach($taxCalculations as $taxCalculation) {                    
                        $taxes[$taxCalculation['tax_calculation_rule_id']] = array(
                            'tax_rule_id' => (int)$taxCalculation['tax_calculation_rule_id'],
                            'tax_title' => html_entity_decode($taxCalculation['code']),
                            'tax_rate_id' => (int)$taxCalculation['tax_calculation_rate_id'],
                            'tax_country_id' => $taxCalculation['tax_country_id'],
                            'tax_region_id' =>(int)$taxCalculation['tax_region_id'],
                            'tax_postcode' => html_entity_decode($taxCalculation['tax_postcode']),
                            'tax_rate' => (float)$taxCalculation['rate'],
                            'zip_is_range' => (bool)$taxCalculation['zip_is_range'],
                            'zip_from' => $taxCalculation['zip_from'],
                            'zip_to' => $taxCalculation['zip_to'],
                            'priority' => (int)$taxCalculation['priority'],
                            'position' => (int)$taxCalculation['position'],
                            'caclculate_subtotal' => (int)$taxCalculation['calculate_subtotal'],
                            'customer_tax_class' => array(
                                'class_id' => (int)$taxCalculation['customer_class_id'],
                                'class_name' => html_entity_decode($taxCalculation['customer_class_name'])
                            ),
                            'product_tax_class' => array(
                                'class_id' => (int)$taxCalculation['product_class_id'],
                                'class_name' => html_entity_decode($taxCalculation['product_class_name'])
                            )
                        );
                    }
                    if($taxes){
                        foreach($taxes as $key=>$val){
                            $is_tax_rate_present = $this->search($tax_result, 'tax_rate_id', $val['tax_rate_id']);
                            if(!$is_tax_rate_present){
                                $tax_result[] = $val;
                            }
                        }
                    }
                    
                    $result = array(
                        'taxes' => $tax_result,
                        'product_tax_classes' => $this->getAllProductTaxClasses(),
                        'customer_tax_classes' => $this->getAllCustomerTaxClasses()
                    );
                }
            } else {
                $error = true;
            }
        }catch (\Exception $e) {
            $error = true;
        }
        $data = array( 'error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    public function search($array, $key, $value){
        $results = array();

        if (is_array($array) && $array) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, $this->search($subarray, $key, $value));
            }
        }

        return $results;
    }


    public function joinTaxCalculation($connection, $countryId, $regionId, $postcode){
        $select = $connection->select();
        $select->from(
            ['tc' => $this->resource->getTableName('tax_calculation')],
            ['tax_calculation_rate_id', 'customer_tax_class_id', 'product_tax_class_id']
        )->joinLeft(
            ['tcra' => $this->resource->getTableName('tax_calculation_rate')],
            "tcra.tax_calculation_rate_id = tc.tax_calculation_rate_id",
            ['code', 'tax_country_id', 'tax_region_id', 'tax_postcode', 'rate', 'zip_is_range', 'zip_from', 'zip_to']
        )->joinLeft(
            ['tcru' => $this->resource->getTableName('tax_calculation_rule')],
            "tcru.tax_calculation_rule_id = tc.tax_calculation_rule_id",
            ['tax_calculation_rule_id','priority', 'position', 'calculate_subtotal']
        )->joinLeft(
            ['txc1' => $this->resource->getTableName('tax_class')],
            "txc1.class_id = tc.customer_tax_class_id",
            ['customer_class_id' => 'class_id', 'customer_class_name' => 'class_name']
        )
        ->joinLeft(
            ['txc2' => $this->resource->getTableName('tax_class')],
            "txc2.class_id = tc.product_tax_class_id",
            ['product_class_id' => 'class_id', 'product_class_name' => 'class_name']
        )->Where("tcra.tax_country_id = '$countryId' AND IF (tcra.tax_region_id = '0', tcra.tax_region_id = '0' , tcra.tax_region_id = $regionId AND (tcra.tax_postcode = $postcode OR tcra.tax_postcode = '*' OR (tcra.zip_from <= $postcode AND tcra.zip_to >= $postcode)))")->order('tcra.tax_region_id ASC');
        
        return $select;
    }

    // To get data from user and outlet tables.
    public function joinOutletData($connection, $userId){
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_outlet_id', 'store_view_id']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['outlet_address_type']
        )->joinLeft(
            ['spoa' => $this->resource->getTableName('ah_supermax_pos_outlet_address')],
            "spu.pos_outlet_id = spoa.parent_outlet_id",
            ['country_id', 'region_id', 'postcode']
        )->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    // Get all Product Tax Classes.
    public function getAllProductTaxClasses(){
        $productTaxClasses = array();
        $taxClasses = $this->productTaxClassSource->getAllOptions();
        foreach($taxClasses as $productTaxClass){
            if($productTaxClass['value'] == 0){
                continue;
            }
            $productTaxClasses[] = array(
                'class_id' => $productTaxClass['value'],
                'class_name' => $productTaxClass['label']
            );
        }
        return $productTaxClasses;                      
    }

    // Get all Customer Tax Classes.
    public function getAllCustomerTaxClasses(){
        $customerTaxClasses = array();
        $taxClasses = $this->customerTaxClassSource->getAllOptions();
        foreach($taxClasses as $customerTaxClass){
            if($customerTaxClass['value'] == 0){
                continue;
            }
            $customerTaxClasses[] = array(
                'class_id' => $customerTaxClass['value'],
                'class_name' => $customerTaxClass['label']
            );
        }
        return $customerTaxClasses;                      
    }
}


