<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\AdvancedSearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Search\Request\IndexScopeResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Search\Request\Dimension;
use Magento\Catalog\Model\Indexer\Category\Product\AbstractAction;
use Magento\Framework\Search\Request\IndexScopeResolverInterface as TableResolver;
use Magento\Catalog\Model\Indexer\Product\Price\DimensionCollectionFactory;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;

/**
 * @api
 * @since 100.1.0
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Index extends \Magento\AdvancedSearch\Model\ResourceModel\Index
{
    /**
     * Index constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param MetadataPool $metadataPool
     * @param string|null $connectionName
     * @param TableResolver|null $tableResolver
     * @param DimensionCollectionFactory|null $dimensionCollectionFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        MetadataPool $metadataPool,
        $connectionName = null,
        TableResolver $tableResolver = null, 
        DimensionCollectionFactory $dimensionCollectionFactory = null
    ) {
        parent::__construct($context, $storeManager,$metadataPool,$connectionName,$tableResolver,$dimensionCollectionFactory);
        $this->storeManager = $storeManager;
        $this->configSourceRegion = ObjectManager::getInstance()->get("Ibo\RegionalPricing\Model\Config\Source\Region");
        $this->metadataPool = $metadataPool;
        $this->tableResolver = $tableResolver ?: ObjectManager::getInstance()->get(IndexScopeResolverInterface::class);
        $this->dimensionCollectionFactory = $dimensionCollectionFactory
            ?: ObjectManager::getInstance()->get(DimensionCollectionFactory::class);
    }

    /**
     * Implementation of abstract construct
     * @return void
     * @since 100.1.0
     */
    protected function _construct()
    {
    }

    /**
     * Return array of price data per customer and website by products
     *
     * @param null|array $productIds
     * @return array
     * @since 100.1.0
     */
    protected function _getCatalogProductPriceData($productIds = null,$getExceptOneQty = false)
    {
        $connection = $this->getConnection();
        $catalogProductIndexPriceSelect = [];

        foreach ($this->dimensionCollectionFactory->create() as $dimensions) {
            if (!isset($dimensions[WebsiteDimensionProvider::DIMENSION_NAME]) ||
                $this->websiteId === null ||
                $dimensions[WebsiteDimensionProvider::DIMENSION_NAME]->getValue() === $this->websiteId) {
                $select = $connection->select()->from(
                    $this->tableResolver->resolve('catalog_product_index_price', $dimensions),
                    ['entity_id', 'customer_group_id', 'website_id', 'min_price']
                );
                $select->joinLeft(array('cpetp' =>'catalog_product_entity_tier_price'), 'cpetp.entity_id = catalog_product_index_price.entity_id and catalog_product_index_price.customer_group_id=cpetp.customer_group_id', array('cpetp.customer_zone','cpetp.value','cpetp.qty'));
                if ($productIds) {
                    /*$select->where('catalog_product_index_price.customer_group_id not in (0,1,2,3) and cpetp.qty=1 and entity_id IN (?)', $productIds);*/
                    if ($getExceptOneQty) {
                        $select->where('catalog_product_index_price.customer_group_id not in (0,1,2,3) and cpetp.qty > 1 and catalog_product_index_price.entity_id IN (?)', $productIds);
                    } else {
                        $select->where('catalog_product_index_price.customer_group_id not in (0,1,2,3) and catalog_product_index_price.entity_id IN (?)', $productIds);
                    }  
                } 
                $catalogProductIndexPriceSelect[] = $select;
            }
        }
        $catalogProductIndexPriceUnionSelect = $connection->select()->union($catalogProductIndexPriceSelect);

        $result = [];
        foreach ($connection->fetchAll($catalogProductIndexPriceUnionSelect) as $row) { 
            if ((int)$row['qty'] > 1) {
                $customerIdZone = ($row['customer_zone'])?$row['customer_group_id']."_".$row['customer_zone']."_".(int)$row['qty']:$row['customer_group_id'];
                $result[$row['website_id']][$row['entity_id']][$customerIdZone] = array('cust_group_id'=>$row['customer_group_id'],'zone'=>$row['customer_zone'],'qty'=>(int)$row['qty'],'price'=>(float)round($row['value'], 2));
            } else {
                $customerIdZone = ($row['customer_zone'])?$row['customer_group_id']."_".$row['customer_zone']:$row['customer_group_id'];
                $result[$row['website_id']][$row['entity_id']][$customerIdZone] = round($row['value'], 2);
            }
            
        } 
        return $result;
    }

    /**
     * Retrieve price data for product
     *
     * @param null|array $productIds
     * @param int $storeId
     * @return array
     * @since 100.1.0
     */
    public function getPriceIndexData($productIds, $storeId, $getExceptOneQty = false)
    {
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

        $this->websiteId = $websiteId;
        $priceProductsIndexData = $this->_getCatalogProductPriceData($productIds,$getExceptOneQty);
        $this->websiteId = null;
        //GET all regions from system configuration
        //loop throug all regions and check if pricedata contains value for that region
        //If not contains then take default price for that customer group
        $connection = $this->getConnection();
        $customerGroup = $connection->fetchCol("select customer_group_id from customer_group where customer_group_id not in (0,1,2,3)"); 
        // print_r($customerGroup);die;
        $customerGroups = $customerGroup;
        $regions = $this->configSourceRegion->toOptionArray();
        $customerGroupRegion = array();
        foreach($regions as $region) { 
            foreach($customerGroups as $customerGroup) { 
                $customerGroupRegion[] = $customerGroup."_".$region["value"];
            }
        } 
        //print_r($customerGroupRegion);
        $diffValues = [];
        if (isset($priceProductsIndexData[$websiteId])) { 
            foreach($priceProductsIndexData[$websiteId] as $id => $productPrice) {
                $productPriceKeys = array_keys($productPrice);
                $diffValues = array_keys(array_flip(array_diff($productPriceKeys, $customerGroupRegion)));
                if(!empty($diffValues)) {
                    foreach ($diffValues as $key => $value) {
                        $priceProductsIndexData[$websiteId][$id]['tier_price'][] = $productPrice[$value];
                        unset($priceProductsIndexData[$websiteId][$id][$value]);
                    }
                }
                if (!$getExceptOneQty) {
                    foreach($customerGroupRegion as $regionKey => $region) {
                        if(!isset($productPrice[$region])) {
                            $customerGroupId = explode("_",$region); 
                            $regionId = (isset($customerGroupId[1]))?$customerGroupId[1]:"";
                            $customerGroupId = (isset($customerGroupId[0]))?$customerGroupId[0]:"";
                            if($customerGroupId && $regionId) { 
                                if(isset($priceProductsIndexData[$websiteId][$id][$region])) { 
                                    $defaultRegionPrice = $priceProductsIndexData[$websiteId][$id][$region]; 
                                } elseif(isset($priceProductsIndexData[$websiteId][$id][$customerGroupId."_default"])) { 
                                    $defaultRegionPrice = $priceProductsIndexData[$websiteId][$id][$customerGroupId."_default"];
                                } else { 
                                    $defaultCustomerGrpId = $connection->fetchCol("select value from core_config_data where path = 'customer/create_account/default_group'"); 
                                    $defaultRegionPrice = isset($priceProductsIndexData[$websiteId][$id][$defaultCustomerGrpId[0]."_default"])?$priceProductsIndexData[$websiteId][$id][$defaultCustomerGrpId[0]."_default"]:0;
                                }
                                if(isset($priceProductsIndexData[$websiteId][$id][$customerGroupId])) { 
                                    unset($priceProductsIndexData[$websiteId][$id][$customerGroupId]);
                                }
                                $priceProductsIndexData[$websiteId][$id][$region] = $defaultRegionPrice; 
                            }
                        }
                    }
                }
            }
        } 
        if (!isset($priceProductsIndexData[$websiteId])) {
            return [];
        }
        return $priceProductsIndexData[$websiteId];
    }

    /**
     * Prepare system index data for products.
     *
     * @param int $storeId
     * @param null|array $productIds
     * @return array
     * @since 100.1.0
     */
    public function getCategoryProductIndexData($storeId = null, $productIds = null)
    { 
        $connection = $this->getConnection();

        $catalogCategoryProductDimension = new Dimension(\Magento\Store\Model\Store::ENTITY, $storeId);

        $catalogCategoryProductTableName = $this->tableResolver->resolve(
            AbstractAction::MAIN_INDEX_TABLE,
            [
                $catalogCategoryProductDimension
            ]
        );

        $select = $connection->select()->from(
            [$catalogCategoryProductTableName],
            ['category_id', 'product_id', 'position', 'store_id']
        )->where(
            'store_id = ?',
            $storeId
        );

        if ($productIds) {
            $select->where('product_id IN (?)', $productIds);
        }

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row['product_id']][$row['category_id']] = $row['position'];
        }

        return $result;
    }

    /**
     * Retrieve moved categories product ids
     *
     * @param int $categoryId
     * @return array
     * @since 100.1.0
     */
    public function getMovedCategoryProductIds($categoryId)
    {
        $connection = $this->getConnection();

        $identifierField = $this->metadataPool->getMetadata(CategoryInterface::class)->getIdentifierField();

        $select = $connection->select()->distinct()->from(
            ['c_p' => $this->getTable('catalog_category_product')],
            ['product_id']
        )->join(
            ['c_e' => $this->getTable('catalog_category_entity')],
            'c_p.category_id = c_e.' . $identifierField,
            []
        )->where(
            $connection->quoteInto('c_e.path LIKE ?', '%/' . $categoryId . '/%')
        )->orWhere(
            'c_p.category_id = ?',
            $categoryId
        );

        return $connection->fetchCol($select);
    }
}
