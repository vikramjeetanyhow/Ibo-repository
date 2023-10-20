<?php

namespace Embitel\Catalog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection;

class BrandCatalogServicePush 
{
    private $brandData = [];
    private $brandPushIds = [];
    protected $curl;
    protected $storeManager;
    protected $scopeConfig;
    protected $logger;
    protected $generateTokenApi;
    protected $xAuthToken;
    protected $xChannelId;
    protected $token = '';
    protected $rootId = '';
    protected $brandrootId = '';

    public function __construct(
        Curl $curl,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CoreSession $coreSession,
        CategoryRepository $categoryRepository,
        CategoryFactory $categoryFactory,
        Attribute $eavAttribute,
        Collection $eavCollection
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->_coreSession = $coreSession;
        $this->categoryRepository = $categoryRepository;
        $this->categoryFactory = $categoryFactory;
        $this->eavAttribute = $eavAttribute;
        $this->eavCollection = $eavCollection;
    }

    public function pushData($brandId) {

        $this->addLog('==============Getting Brand Data started==================');
        $this->getBrandIdCollection();

        if(gettype($brandId)== "integer"){

            $category = $this->categoryRepository->get($brandId);
            $subCategories = $category->getChildrenCategories();
            foreach($subCategories as $subCategory) {
              //  $brandId ='';
                $categoryName = $subCategory->getName();
                $subcategoryId = $subCategory->getId();
                $catoryData = $this->categoryFactory->create()->getCollection()
                   ->addAttributeToSelect('*')
                   ->addAttributeToFilter('entity_id',$subcategoryId);
                   if($catoryData->getSize()) {
                        $brandCatId = $catoryData->getFirstItem()->getData();
                        $brandIboId = isset($brandCatId['ibo_brand_id']) ? $brandCatId['ibo_brand_id'] : '';
                    }
                
                    $returnData = $this->brandPush($brandCatId);
                    if($returnData['msg'] == 'success') {
                        $subCategory->setIboBrandId($returnData['brand_id']);
                        $subCategory->save();
                        $this->addLog('==============Update category ibo brand id done==================');
                        if($brandIboId == '') {
                            $this->addLog('BrandID Created for catId'.$subcategoryId);
                        }
                    }           
            }
        }else{
            $brandIdsInArrays = $brandId;
            foreach($brandIdsInArrays as $brandIds){
                $storeId = 0;
                $category = $this->categoryRepository->get($brandIds,$storeId);
                $brandCatId = $category->getData();

                $subcategoryId = (int)$brandCatId['entity_id'];
                $catoryData = $this->categoryFactory->create()->load($subcategoryId);

                if(!empty($brandCatId)) {
                    $brandCatId = $brandCatId;
                    $brandIboId = isset($brandCatId['ibo_brand_id']) ? $brandCatId['ibo_brand_id'] : '';
                }
                
                $catoryData->setMetaTitle($brandCatId['meta_title']);
                $catoryData->setMetaDescription($brandCatId['meta_description']);
                $catoryData->setMetaKeywords($brandCatId['meta_keywords']);
                $catoryData->save();

                $returnData = $this->brandPush($brandCatId);
                if($returnData['msg'] == 'success') {
                    $category->setIboBrandId($returnData['brand_id']);
                    $category->save();
                    $this->addLog('==============Update category ibo brand id done==================');
                    if($brandIboId == '') {
                        $this->addLog('BrandID Created for catId'.$subcategoryId);
                    }
                }
            }
        }

    }

    public function brandPush($brandName)
    {
        $this->createBrandApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/create_brand_api"));
        
        try{    
                $data = '';
                 $brandPayload = $this->brandPayload($brandName);
                 if($this->token == ''){ 
                    $this->token = $this->getAuthToken();
                  }
                 if($this->token != ''){
                     $this->addLog('==============request==========================');
                     $this->addLog(json_encode($this->brandData,JSON_PRETTY_PRINT));
                     $this->addLog('==============response==========================');
                     $data = $this->CurlExecute($brandPayload,$this->createBrandApi,$this->token);
                 }
              
         }catch(Exception $e){
            $this->addLog('Exception on category save - ' . $e->getMessage());
        }
        return $data;
    }

    public function brandPayload($brand){ 
         $brandId = $this->getBrandId($brand['category_id']);
         if($brandId != ''){
            $this->brandData['brand_id'] = $brandId;
         } 
         $this->brandData['brand_code'] = $brand['category_id']; 
         $this->brandData['brand_name'] = $brand['name'];
         $this->brandData['description'] = (isset($brand['description'])) ? trim(strip_tags($brand['description'])) : '';
         $this->brandData['seo_info']['slug'] = (isset($brand['url_key'])) ? $brand['url_key'] : "";
         $this->brandData['seo_info']['keyword'] = (isset($brand['meta_keywords'])) ? $brand['meta_keywords'] : '';
         $this->brandData['seo_info']['meta']['title'] = (isset($brand['meta_title'])) ? $brand['meta_title'] : '';
         $this->brandData['seo_info']['meta']['description'] = (isset($brand['meta_description'])) ? $brand['meta_description'] : '';

         $this->brandData['is_active'] = (isset($brand['is_active'])) ? true :false;
         $this->brandData['is_published'] = (isset($brand['include_in_menu'])) ? true : false;
         $this->brandData['catalog_version'] = "ONLINE";

         return json_encode($this->brandData);
    }

    public function getAuthToken()
    {
        $this->_coreSession->start();
        $this->generateTokenApi = trim($this->scopeConfig->getValue("core_media/service/generate_token_api"));
        $this->xAuthToken     = trim($this->scopeConfig->getValue("core_media/service/x_auth_token"));
        if($this->_coreSession->getIboAuthToken() != ""){
            return $this->_coreSession->getIboAuthToken();
        }
        $tokenResult  = '';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_HTTPGET, true);
        $headers = ["x-auth-token" => $this->xAuthToken];
        $this->addLog(print_r($headers,true));
        $this->curl->setHeaders($headers);
        try {
            $this->addLog('Media Auth Curl Initiated');
            $this->curl->get($this->generateTokenApi);
            $this->addLog($this->generateTokenApi);
            $authresult = $this->curl->getBody();
            $authresultData = json_decode($authresult, true);
            $this->addLog('Media Auth Curl response');
            $this->addLog(json_encode($authresultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {           
            $this->addLog("Media Auth Curl There is some error"."=======".$e->getMessage());
        }

        if ($authresultData) {
            $this->addLog('Media Curl Executed');

            if (isset($authresultData['errors'])) {
                $this->addLog(print_r($authresultData['errors'],true));
            }

            if (isset($authresultData['token'])) {
                $tokenResult  = $authresultData['token'];

            }            
            $this->_coreSession->setIboAuthToken($tokenResult);
            $this->addLog("_coreSession Set"."=======".$this->_coreSession->getIboAuthToken());
            return $tokenResult;
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
            $this->brandData = [];
            if (isset($resultData['errors'])) {
                $this->addLog(print_r($resultData['errors'],true));
                $returnResult = "There is some error";
            }
        }

        if ($returnResult != '') {
            $data['msg'] = 'error';
        } else {
            $data['msg'] = 'success';
            $data['brand_id'] = $resultData['brand_id'];
        }
        return $data;
    }

    public function getBrandIdCollection() 
    {
        $attributeCode = 'brand_Id';
        $entityType = 'catalog_product';
        $attributeInfo = $this->eavAttribute->loadByCode($entityType, $attributeCode);

        $attributeId = $attributeInfo->getAttributeId(); 

        $attributeOptionAll = $this->eavCollection
                                    ->setPositionOrder('asc')
                                    ->setAttributeFilter($attributeId)                                             
                                    ->setStoreFilter()
                                    ->load();
        foreach($attributeOptionAll->getData() as $data) {
            $this->brandPushIds[$data['option_id']] = $data['default_value'];
        }
    }

    public function getBrandId($catName) {
        $brandIdValue = array_search(trim($catName),$this->brandPushIds,true);
        if($brandIdValue != '') {
            return (string) $brandIdValue;
        } else {
            $this->addLog("Error : Brand Id not found for the brand name :".$catName);  
            return '';
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
            $this->isLogEnable =1;
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/brandpush_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}