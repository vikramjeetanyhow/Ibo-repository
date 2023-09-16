<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Model\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Ibo\CoreMedia\Helper\Data as CoreMediaHelper;

class Banner extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
	protected $updateAttributesId = '';
    protected $storeManager;
	public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        Status $productStatus,
        Visibility $productVisibility,
        ProductCategoryList $productCategory,
        AttributeRepository $attributeRepository,
        CollectionFactory $productCollectionFactory,
        AttributeCollectionFactory $productAttributeCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CoreMediaHelper $coreMediaHelper,
        \Magento\Framework\App\ResourceConnection $resource,
        $connectionName = null
    ) {
		$this->scopeConfig = $scopeConfig;
        $this->productStatus = $productStatus;
        $this->productCategory = $productCategory;
        $this->productVisibility = $productVisibility;
        $this->attributeRepository = $attributeRepository;
    	$this->productCollectionFactory = $productCollectionFactory;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->storeManager = $storeManager;
        $this->coreMediaHelper = $coreMediaHelper;
        $this->_resource = $resource;
        parent::__construct($context,$connectionName);
    }

    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('embitel_banner', 'banner_id');   //here "embitel_banner" is table name and "banner_id" is the primary key of custom table
    }

    /**
     * Process product data before save
     *
     * @param DataObject $object
     * @return $this
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
    	$self = parent::_beforeSave($object);
		$bannerCategoryIds = ($object->getData('cat_ids'))?explode(',',$object->getData('cat_ids')):[];
		$bannerAttributeIds = ($object->getData('attribute_ids'))?explode(',',$object->getData('attribute_ids')):[];
		$bannerParentCatId = $this->scopeConfig->getValue("banner_cate/cat_config/banner_parent_cagegory");	
		foreach ($bannerCategoryIds as $bannerCategoryId)
		{
			$allAttributes = [];
			if($this->isValid($bannerCategoryId) == $bannerParentCatId)
			{
				$allAttributes = $this->getAttributesByCategory($bannerCategoryId);
				$removeDiffAttributeIds = array_diff(array_unique($this->getProductAttributeList($bannerCategoryId)),$allAttributes);
				if(count($removeDiffAttributeIds) > 0){
                    $this->removeCategoryInAttributeFilter($removeDiffAttributeIds,$bannerCategoryId);
                }
				if(count($allAttributes) > 0)
				{
					$updateAttributes = $this->updateAttribute($allAttributes, $bannerCategoryId);
		            $this->updateAttributesId.=  implode(',',$updateAttributes);
				}
			}else{
				$this->updateAttributesId.= implode(',',$this->getProductAttributeList($bannerCategoryId));
			}
		}
		$object->setAttributeIds($this->updateAttributesId);
		return $self;
    }

    public function isValid($categoryId)
    {
    	$categoryQuery = "SELECT parent_id FROM `catalog_category_entity` WHERE `entity_id` = '$categoryId'";
        $catParentId = $this->getConnection()->fetchOne($categoryQuery);
        return $catParentId;
    }

    public function getAttributesByCategory($categoryIds)
    {
    	$category = [];
    	$prodAttributes = [];
    	$collection = $this->productCollectionFactory->create();        
        $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $collection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        $collection->addCategoriesFilter(['in' => $categoryIds]);
        foreach ($collection->getData() as $products)
        {	
        	$categoryIds = $this->productCategory->getCategoryIds($products['entity_id']);
	        if ($categoryIds) {
	        	$category = array_merge($category, $categoryIds); 
	        }
	    }
	    $resultCategoryIds = array_unique($category);
		if(count($resultCategoryIds) > 0){
			foreach ($resultCategoryIds as $categoryId) {
				$prodAttributes = array_merge($this->getProductAttributeList($categoryId),$prodAttributes);
    		}
		}
		return array_unique($prodAttributes);
	}

    public function getProductAttributeList($categoryId)
    {
    	/** @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $productAttributes */
        $productAttributes = $this->productAttributeCollectionFactory->create();
        $productAttributes->addFieldToFilter(
            ['is_filterable', 'is_filterable_in_search'],
            [[1, 2], 1]
        );
        $productAttributes->addFieldToFilter('attribute_category_ids',['neq'=>NULL]);
        $attributes = [];
        $attributeCategoriesJson = [];       

        foreach ($productAttributes as $attribute) {
        	$attributeCategories = $attribute->getAttributeCategoryIds();
        	if(!is_string($attributeCategories) && is_array($attributeCategories)){
    			$attributeCategories = json_encode($attributeCategories);
    		}
        	$attributeCategoriesJson = json_decode($attributeCategories);		        	
        	if(is_array($attributeCategoriesJson) && in_array($categoryId, $attributeCategoriesJson)){
        		$attId = $attribute->getAttributeId();
        		$attributes[$attId] = $attribute->getAttributeCode();
        	}
        }
        return $attributes;
    }

   
    public function updateAttribute($allAttributes, $bannerCategoryIds)
    {	
    	$updateAttributeIds = [];
        $attributeCategoriesJson = [];
        foreach (array_unique($allAttributes) as $attributeCode) {
    		$attribute = $this->attributeRepository->get($attributeCode);
    		$attributeCategories = $attribute->getAttributeCategoryIds();
    		if(!is_string($attributeCategories) && is_array($attributeCategories)){
    			$attributeCategories = json_encode($attributeCategories);
    		}
    		$attributeCategoriesJson = json_decode($attributeCategories);
    		if(!is_array($bannerCategoryIds) && !in_array($bannerCategoryIds, $attributeCategoriesJson))
    		{
    			$attributeCategoriesJson[] = $bannerCategoryIds;
            	$attribute->setAttributeCategoryIds($attributeCategoriesJson);
            	$attribute->save();
            	$updateAttributeIds[] = trim($attribute->getAttributeCode());
        	}
        	
            if(!is_array($bannerCategoryIds) && in_array($bannerCategoryIds, $attributeCategoriesJson)){
        		$updateAttributeIds[] = trim($attribute->getAttributeCode());
        	}
    	}
    	return $updateAttributeIds;
    }

    public function removeCategoryInAttributeFilter($removeDiffAttributeIds,$bannerCategoryIds)
    {
        $attributeCategoriesJson = [];
        foreach ($removeDiffAttributeIds as $attributeCode) {                                
            $attribute = $this->attributeRepository->get($attributeCode);
            $attributeCategories = $attribute->getAttributeCategoryIds();
            if(!is_string($attributeCategories) && is_array($attributeCategories)){
    			$attributeCategories = json_encode($attributeCategories);
    		}
            $attributeCategoriesJson = json_decode($attributeCategories);
           
            if(!is_array($bannerCategoryIds) && in_array($bannerCategoryIds, $attributeCategoriesJson))
            {
                if (($key = array_search($bannerCategoryIds, $attributeCategoriesJson)) !== false) {                      
                            unset($attributeCategoriesJson[$key]);
                }
                $attribute->setAttributeCategoryIds(array_values($attributeCategoriesJson));
                $attribute->save();                       
            }
        }      
    }

    // public function _afterSave(\Magento\Framework\Model\AbstractModel $object) {
    //     $this->addLog('Entered After Save'); $this->addLog('Mobile :'.$this->storeManager->getStore()->getBaseUrl().'media/'.$object->getData('mobile_image')); $this->addLog('Desktop:'.$this->storeManager->getStore()->getBaseUrl().'media/'.$object->getData('desktop_image')); 

    //     if(($object->getData('mobile_image') != '') && (!(strpos($object->getData('mobile_image'), 'media') !== false))){
    //         $custom_mobile_image = $this->coreMediaHelper->getCoreMediaImageUrl($this->storeManager->getStore()->getBaseUrl().'media/'.$object->getData('mobile_image'));
    //         $this->addLog('Custom Mobile img:'.$custom_mobile_image);
    //         $connection = $this->_resource->getConnection();
    //         $tableName = $this->_resource->getTableName('embitel_banner');
    //         $sql = "update ".$tableName." set mobile_image_custom ='".$custom_mobile_image."' where banner_id = ".$object->getId();
        
    //         $connection->query($sql);
    //     }

    //     if(($object->getData('desktop_image') != '') && (!(strpos($object->getData('desktop_image'), 'media') !== false))){

    //         $custom_desktop_image = $this->coreMediaHelper->getCoreMediaImageUrl($this->storeManager->getStore()->getBaseUrl().'media/'.$object->getData('desktop_image'));
    //         $this->addLog('Custom desktop img:'.$custom_desktop_image);
    //         $connection = $this->_resource->getConnection();
    //         $tableName = $this->_resource->getTableName('embitel_banner');
    //         $sql = "update ".$tableName." set desktop_image_custom ='".$custom_desktop_image."' where banner_id = ".$object->getId();
           
    //         $connection->query($sql);
    //     }

    // }

    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->scopeConfig->getValue(
                "cate/ibo_cat_config/cat_sync_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/banner_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

}