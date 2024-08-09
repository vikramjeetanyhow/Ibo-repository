<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxProduct;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
   
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxProduct',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxProduct'
        );
    }

    protected function _initSelect()
    {
        parent::_initSelect();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $productData = array();
        $connection = $resource->getConnection();
        $outletTableName = $resource->getTableName('ah_supermax_pos_outlet');
        
        $outletId = $objectManager->get('Anyhow\SupermaxPos\Model\Session')->getPosOutletId();
        $setup = $objectManager->get('Magento\Framework\Setup\SchemaSetupInterface');
        $outletData = $connection->query("SELECT product_assignment_basis FROM $outletTableName Where pos_outlet_id = $outletId ");
        $assignmentCriteria = '';
        if(!empty($outletData)){
            foreach($outletData as $outlet){
                $assignmentCriteria = $outlet['product_assignment_basis'];
            }
        }
        $entityTypeTable = $resource->getTableName('eav_entity_type');
        $attributeTableName = $resource->getTableName('eav_attribute');
        $attributeNameIds = $connection->query("SELECT att.attribute_id FROM $attributeTableName AS att LEFT JOIN $entityTypeTable AS ent ON att.entity_type_id = ent.entity_type_id Where att.attribute_code = 'name' AND  ent.entity_type_code = 'catalog_product' ");
        foreach($attributeNameIds as $attrib){
            $attribName = $attrib['attribute_id'];
        }
        $attributeStatusIds = $connection->query("SELECT att.attribute_id FROM $attributeTableName AS att LEFT JOIN $entityTypeTable AS ent ON att.entity_type_id = ent.entity_type_id Where attribute_code = 'status' AND  ent.entity_type_code = 'catalog_product'");
        foreach($attributeStatusIds as $attrib){
            $attribStatus = $attrib['attribute_id'];
        }
        $attributePriceIds = $connection->query("SELECT att.attribute_id FROM $attributeTableName  AS att LEFT JOIN $entityTypeTable AS ent ON att.entity_type_id = ent.entity_type_id Where attribute_code = 'price' AND  ent.entity_type_code = 'catalog_product'");
        foreach($attributePriceIds as $attrib){
            $attribPrice = $attrib['attribute_id'];
        }
        $attributeThumbIds = $connection->query("SELECT att.attribute_id FROM $attributeTableName  AS att LEFT JOIN $entityTypeTable AS ent ON att.entity_type_id = ent.entity_type_id Where attribute_code = 'thumbnail' AND  ent.entity_type_code = 'catalog_product'");
        foreach($attributeThumbIds as $attrib){
            $attribThumb = $attrib['attribute_id'];
        }
       
        $this->getSelect()->joinLeft(
            ['cpev' => $this->getTable('catalog_product_entity_varchar')], 
            "main_table.entity_id = cpev.entity_id AND cpev.store_id = 0 AND cpev.attribute_id = $attribName",
            ['name'=> 'value']
        )->joinLeft(
            ['cpei' => $this->getTable('catalog_product_entity_int')], 
            "main_table.entity_id = cpei.entity_id AND cpei.store_id = 0 AND cpei.attribute_id = $attribStatus",
            ['status'=> 'value']
        )->joinLeft(
            ['cped' => $this->getTable('catalog_product_entity_decimal')], 
            "main_table.entity_id = cped.entity_id AND cped.store_id = 0 AND cped.attribute_id = $attribPrice",
            ['price'=> 'value']
        )->joinLeft(
            ['cpevv' => $this->getTable('catalog_product_entity_varchar')], 
            "main_table.entity_id = cpevv.entity_id AND cpevv.store_id = 0 AND cpevv.attribute_id =  $attribThumb",
            ['thumbnail'=> 'value']
        );
        if($setup->tableExists('inventory_source')){
            $salesInventoryTable = $resource->getTableName('inventory_source_item'); 
            $this->getSelect()->columns(['qty'=> new \Zend_Db_Expr(
                "(SELECT SUM($salesInventoryTable.quantity) FROM $salesInventoryTable WHERE $salesInventoryTable.sku = main_table.sku GROUP BY $salesInventoryTable.sku)"
            )]);
        } else {
            $singleSourceInventoryTable = $resource->getTableName('cataloginventory_stock_item');
            $this->getSelect()->columns(['qty'=> new \Zend_Db_Expr(
                "(SELECT SUM($singleSourceInventoryTable.qty) FROM $singleSourceInventoryTable WHERE $singleSourceInventoryTable.product_id = main_table.entity_id AND $singleSourceInventoryTable.stock_id = 1 GROUP BY $singleSourceInventoryTable.product_id)"
            )]);
        }
        // if products are assigned on category basis
        if($assignmentCriteria == 'category'){
            $productData = array();
            $data = $connection->select();
            $data->from(
                ['scto' => $resource->getTableName('ah_supermax_pos_category_to_outlet')],
                ['category_id']
            )->joinLeft(
                ['ccp' => $resource->getTableName('catalog_category_product')],
                "scto.category_id = ccp.category_id",
                ['product_id']
            );
            $productCollection = $connection->query($data)->fetchAll();
            foreach($productCollection as $product){
                $productData[] =  (int)$product['product_id'];
            }
            // $outletCategoryTableName = $resource->getTableName('ah_supermax_pos_category_to_outlet');
            // $outletCategoryData = $connection->query("SELECT category_id FROM $outletCategoryTableName WHERE parent_outlet_id = $outletId ");
            // if(!empty($outletCategoryData)){
            //     $visible = [1,2,3,4];
            //     foreach($outletCategoryData as $category){
            //         $categoryFactory = $objectManager->get('Magento\Catalog\Model\Category')->load($category['category_id']);
            //         $categoryProducts = $categoryFactory->getProductCollection()->getData();
            //         if(!empty($categoryProducts)) {
            //             foreach($categoryProducts as $categoryProduct) {
            //                 $productData[] =  (int)$categoryProduct['entity_id'];
            //             }
            //         }
            //     }
            // }
            $this->getSelect()->where('main_table.entity_id IN (?)', $productData)->group('main_table.entity_id');
        }

        // if products are assigned on product basis
        if($assignmentCriteria == 'product'){
            $productData = array();
            $outletProductTableName = $resource->getTableName('ah_supermax_pos_product_to_outlet');
            $outletProductData = $connection->query("SELECT product_id FROM $outletProductTableName WHERE parent_outlet_id = $outletId ");
            if(!empty($outletProductData)){
                foreach($outletProductData as $product){
                    $productData[] =  (int)$product['product_id'];
                }
            }
            $this->getSelect()->where('main_table.entity_id IN (?)', $productData)->group('main_table.entity_id');
            
        }

        if($assignmentCriteria == 'all'){
            $this->getSelect();
        }
    
        return $this;
    }
}