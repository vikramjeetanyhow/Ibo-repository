<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     mohit.pandit@embitel.com
 */

namespace Ibo\HomePage\Model\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;

class HomeCategories extends AbstractDb
{
	const TYPE = 'top_brands';

    protected $curl;

	public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        Status $productStatus,
        Visibility $productVisibility,
        ProductCategoryList $productCategory,
        AttributeRepository $attributeRepository,
        CollectionFactory $productCollectionFactory,
        AttributeCollectionFactory $productAttributeCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        CoreSession $coreSession,
        $connectionName = null
    ) {
		$this->scopeConfig = $scopeConfig;
        $this->productStatus = $productStatus;
        $this->productCategory = $productCategory;
        $this->productVisibility = $productVisibility;
        $this->attributeRepository = $attributeRepository;
    	$this->productCollectionFactory = $productCollectionFactory;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->_categoryFactory = $categoryFactory;
        $this->_coreSession = $coreSession;
        $this->curl = $curl;
        
        parent::__construct($context,$connectionName);
    }

    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('ibo_home_category', 'id');
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

    	$parentCatId = $this->scopeConfig->getValue("banner_cate/cat_config/banner_parent_cagegory");
    	$brandCategoryId = $object->getData('category_id');
    	$type = $object->getData('type');

    	$allAttributes = [];
    	$updateData = null;
    	$updateAttributesId = '';
    	if($this->isValid($brandCategoryId) == $parentCatId && $type == self::TYPE)
		{
			$allAttributes = $this->getAttributesByCategory($brandCategoryId);
			$removeDiffAttributeIds = array_diff(array_unique($this->getProductAttributeList($brandCategoryId)),$allAttributes);
			if(count($removeDiffAttributeIds) > 0){
                $this->removeCategoryInAttributeFilter($removeDiffAttributeIds,$brandCategoryId);
            }
            
			if(count($allAttributes) > 0)
			{
				$updateAttributes = $this->updateAttribute($allAttributes, $brandCategoryId);
	            $updateAttributesId =  implode(',',$updateAttributes);
	        }
		}				
		$object->setAttributeIds($updateAttributesId);
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
        
        foreach ($allAttributes as $attributeCode) {
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

    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->addLog('Entered After save category');
        
        if($object->getData('type') == 'top_categories') {
            $payload = $this->getCategoryPayload($object);
            $this->addLog('Payload :'.$payload);
            $url = trim($this->scopeConfig->getValue("cate/ibo_cat_config/top_category_api"));
            $this->addLog('Url :'.$url);
            $this->CurlExecute($payload,$url,$this->getAuthToken());
        }

        if($object->getData('type') == 'top_brands') {
            $payload = $this->getBrandPayload($object);
            $this->addLog('Payload :'.$payload);
            $url = trim($this->scopeConfig->getValue("cate/ibo_cat_config/top_brands_api"));
            $this->addLog('Url :'.$url);
            $this->CurlExecute($payload,$url,$this->getAuthToken());
        }
        //$this->addLog('Data : CatId :'.$CategoryId.' Active : '.$isActive. ' Groups: '.$customerGroups.'Start Date :'.$startDate.' End date :'.$endDate.' Type:'.$catType);

    }

    public function getBrandPayload($object) {

        $category = $this->_categoryFactory->create()->load($object->getData('category_id'));
        if($category->getIsActive()) {
            $isActive = true;
        } else {
            $isActive = false;
        }

        $customerGroups = explode(',',$object->getData('customer_group'));

        $brandData = [];
        $brandData['catalog_version'] = 'ONLINE';
        $brandData['collection_type'] = 'TOP-BRANDS';
        $brandsData['brand_id'] = $object->getData('category_id');
        $brandsData['brand_order'] = 1;
        $brandsData['is_active'] = $isActive;
        $brandsData['customer_groups'] = $customerGroups;
        $brandsData['start_datetime'] = $object->getData('from_date');
        if($object->getData('to_date') != '') {
            $brandsData['end_datetime'] = $object->getData('to_date');
        }
        $brandData['brands'][] = $brandsData;

        return json_encode($brandData, JSON_UNESCAPED_SLASHES);
    }

    public function getCategoryPayload($object) {

        $category = $this->_categoryFactory->create()->load($object->getData('category_id'));
        if($category->getIsActive()) {
            $isActive = true;
        } else {
            $isActive = false;
        }

        $customerGroups = explode(',',$object->getData('customer_group'));

        $catData = [];
        $catData['catalog_version'] = 'ONLINE';
        $catData['collection_type'] = 'TOP-CATEGORIES';
        $cateData['category_id'] = $object->getData('category_id');
        $cateData['category_order'] = 1;
        $cateData['is_active'] = $isActive;
        $cateData['customer_groups'] = $customerGroups;
        $cateData['start_datetime'] = $object->getData('from_date');
        if($object->getData('to_date') != '') {
            $cateData['end_datetime'] = $object->getData('to_date');
        }
        $catData['categories'][]= $cateData;

        return json_encode($catData, JSON_UNESCAPED_SLASHES);
    }

    public function getAuthToken()
    {
        $this->_coreSession->start();

        if($this->_coreSession->getCoreMediaAuthtoken()) {
            $this->addLog('Get Token');
            return $this->_coreSession->getCoreMediaAuthtoken();
        } else {

        $this->generateTokenApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/generate_token_api"));
        $this->xAuthToken = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_auth_token"));

        $tokenResult  = '';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_HTTPGET, true);
        $headers = ["x-auth-token" => $this->xAuthToken];
        $this->curl->setHeaders($headers);
        try {
            $this->addLog('Auth Curl Initiated');
            $this->curl->get($this->generateTokenApi);
            $authresult = $this->curl->getBody();
            $authresultData = json_decode($authresult, true);
            $this->addLog('Auth Curl response');
            $this->addLog(json_encode($authresultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {           
            $this->addLog("Auth Curl There is some error"."=======".$e->getMessage());
        }

        if ($authresultData) {
            $this->addLog('Curl Executed');

            if (isset($authresultData['errors'])) {
                $this->addLog(print_r($authresultData['errors'],true));
            }

            if (isset($authresultData['token'])) {
                $tokenResult  = $authresultData['token'];

            }
            $this->_coreSession->setCoreMediaAuthtoken($tokenResult);
            $this->addLog('Set Token');
            return $tokenResult;

        }   
    }    

    }

    public function CurlExecute($payload,$url,$token)
    {
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        $returnResult = '';        
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["x-channel-id" => $this->xChannelId,"Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->curl->setHeaders($headers);

        try {
            $this->addLog('Curl Initiated');
            $this->curl->post($url,$payload);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
            $this->addLog(json_encode($resultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        if ($resultData) {
            $this->addLog('Curl Executed');
            $this->categoryData = [];
            $this->brandData = [];
            if (isset($resultData['errors'])) {
                $this->addLog(print_r($resultData['errors'],true));
                $returnResult = "There is some error";
            }
        }

        if ($returnResult != '') {
            return 'Error thrown';
        } else {
            return "success";//category_id
        }
    }

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
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/topcat_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }


}