<?php

namespace Embitel\Catalog\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Ibo\CoreMedia\Helper\Data;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Magento\Cms\Model\BlockFactory;
use Magento\Catalog\Model\CategoryRepository;

class CategorySaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    private $category = null;
    private $categoryData = [];
    private $brandData = [];
    protected $curl;
    protected $storeManager;
    protected $scopeConfig;
    protected $logger;
    protected $generateTokenApi;
    protected $xAuthToken;
    protected $createCategoryApi;
    protected $xChannelId;
    protected $token = '';
    protected $rootId = '';
    protected $brandrootId = '';
    protected $_coreSession;

    public function __construct(
        Curl $curl,
        CoreSession $coreSession,
        Data $coreMediaHelper,
        CategoryFactory $categoryFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        BlockFactory $blockFactory,
        CategoryRepository $CategoryRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->coreMediaHelpers = $coreMediaHelper;
        $this->categoryFactory = $categoryFactory;
        $this->_coreSession = $coreSession;
        $this->blockFactory = $blockFactory;
        $this->CategoryRepository = $CategoryRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $isCronEnable = $this->scopeConfig->getValue("cate/ibo_cat_config/cron_status");
        if (!$isCronEnable) {
            $this->addLog('IBO category service sync not enable.');
            return;
        }
        $websiteId =1;
        $storeId = $this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $this->rootId = $this->storeManager->getStore($storeId)->getRootCategoryId();
        $this->brandrootId = $this->scopeConfig->getValue("banner_cate/cat_config/banner_parent_cagegory");

        $this->generateTokenApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/generate_token_api"));
        $this->xAuthToken = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_auth_token"));
        $this->createCategoryApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/create_category_api"));
        $this->createBrandApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/create_brand_api"));
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->createCategoryApi) || empty($this->createBrandApi) || empty($this->xChannelId)){
             $this->addLog('IBO category sync configuration missing.');
             return;
        }

        try{
             $this->category = $this->categoryFactory->create()->load($observer->getEvent()->getCategory()->getId());

            // update category meta data store 1 (frontend)
            $storeId = 0;
            $category = $this->CategoryRepository->get($observer->getEvent()->getCategory()->getId(),$storeId);
            $categoryFrontend = $category->getData();

            $this->category->setMetaTitle($categoryFrontend['meta_title']);
            $this->category->setMetaDescription($categoryFrontend['meta_description']);
            $this->category->setMetaKeywords($categoryFrontend['meta_keywords']);
            $this->category->save();
            // End update category meta data store 1 (frontend)


             /*if($this->token == ''){
                $this->token = $this->getAuthToken();
             }
             if(!empty($this->category->getData('image'))){
                $imagefullPath = $catImgUrl = '';
                $catImgUrl = $this->storeManager->getStore()->getBaseUrl().$this->category->getData('image');
                $imagefullPath = str_replace("//media", "/media", $catImgUrl);

                if($imagefullPath != '' && @fopen($imagefullPath, 'r')){
                  $image_custom = $this->coreMediaHelpers->getCoreMediaImageUrl($imagefullPath);
                  if($image_custom != 'Error thrown' && $image_custom != ''){
                    $this->updateCoreMediaAttribute('base_image_custom',$image_custom);
                   }
                }

             }

             if(!empty($this->category->getData('primary_banner_image'))){
                $imagefullPath = $catImgUrl = '';
                $catImgUrl = $this->storeManager->getStore()->getBaseUrl().$this->category->getData('primary_banner_image');
                $imagefullPath = str_replace("//media", "/media", $catImgUrl);

                if($imagefullPath != '' && @fopen($imagefullPath, 'r')){
                  $pbimagecustom = $this->coreMediaHelpers->getCoreMediaImageUrl($imagefullPath);
                   if($pbimagecustom != 'Error thrown' && $pbimagecustom != ''){
                        $this->updateCoreMediaAttribute('primary_banner_image_custom',$pbimagecustom);
                    }
                }

             }

             if(!empty($this->category->getData('secondary_banner_image'))){
                $imagefullPath = $catImgUrl = '';
                $catImgUrl = $this->storeManager->getStore()->getBaseUrl().$this->category->getData('secondary_banner_image');

                $imagefullPath = str_replace("//media", "/media", $catImgUrl);

                if($imagefullPath != '' && @fopen($imagefullPath, 'r')){
                  $sbimagecustom = $this->coreMediaHelpers->getCoreMediaImageUrl($imagefullPath);
                  if($sbimagecustom != 'Error thrown' && $sbimagecustom != ''){
                    $this->updateCoreMediaAttribute('secondary_banner_image_custom',$sbimagecustom);
                  }
                }

             }*/

             if(!empty($this->category->getData('category_type')) && $this->category->getData('category_type') == 'NAVIGATION' && $this->validateCategory($this->category->getData('path')) == 0 && (string)$this->category->getData('category_id') != $this->rootId){
                 $payload = $this->categoryPayload();
                 if($this->token == ''){
                    $this->token = $this->getAuthToken();
                  }
                 if($this->token != ''){
                     $this->addLog('==============request==========================');
                     $this->addLog(json_encode($this->categoryData,JSON_UNESCAPED_SLASHES));
                     $this->CurlExecute($payload,$this->createCategoryApi,$this->token);
                 }
              }
              /*if(!empty($this->category->getData('category_type')) && $this->category->getData('category_type') == 'BRAND' && $this->category->getData('parent_id') == $this->brandrootId && (string)$this->category->getData('category_id') != $this->brandrootId){
                 $brandPayload = $this->brandPayload();
                 if($this->token == ''){
                    $this->token = $this->getAuthToken();
                  }
                 if($this->token != ''){
                     $this->addLog('==============request==========================');
                     $this->addLog(json_encode($this->brandData, JSON_UNESCAPED_SLASHES));
                     $this->CurlExecute($brandPayload,$this->createBrandApi,$this->token);
                 }
              }*/


         }catch(Exception $e){
            $this->addLog('Exception on category save - ' . $e->getMessage());
        }
    }

    /**
     * Update core media.
     *
     * @param type $categoryId
     */
    protected function updateCoreMediaAttribute($attribute,$attributeValue)
    {   $this->addLog($attribute.' updateCoreMediaAttribute save - ' . $attributeValue);

        $this->category->setData($attribute, $attributeValue);
        $this->category->setData('store_id', 0);
        $this->category->getResource()->saveAttribute($this->category, $attribute);
    }

    public function brandPayload(){
         $image = [];
         $primaryBanner = [];
         $secondaryBanner = [];
         $this->brandData['brand_id'] = $this->category->getData('entity_id');
         $this->brandData['brand_name'] = $this->category->getData('name');
         $this->brandData['is_active'] = ($this->category->getData('is_active')) ? true :false;
         $this->brandData['is_published'] = ($this->category->getData('include_in_menu')) ? true : false;
         $this->brandData['description'] = (trim(strip_tags($this->category->getData('description')))) ? trim(strip_tags($this->category->getData('description'))) : ' ';

         $this->brandData['seo_info']['slug'] = $this->category->getData('url_key');
         $this->brandData['seo_info']['keyword'] = ($this->category->getData('meta_keywords')) ? $this->category->getData('meta_keywords') : '';
         $this->brandData['seo_info']['meta']['title'] = ($this->category->getData('meta_title')) ? $this->category->getData('meta_title') : '';
         $this->brandData['seo_info']['meta']['description'] = ($this->category->getData('meta_description')) ? $this->category->getData('meta_description') : '';
         $this->brandData['media'] = [];

         if(!empty($this->category->getData('image'))){
             $catImgUrl = ($this->category->getData('image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('image'):"";
             $image['url'] = str_replace("//media", "/media", $catImgUrl);
             $image['media_entity'] = 'BRAND';
             $image['media_type'] = 'IMAGE';
             $image['media_extension'] = (string) pathinfo($this->category->getData('image'),PATHINFO_EXTENSION);
             $image['alt_text'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['position'] = 0;
             $image['title'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['is_primary_for_store'] = true;
             $image['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->brandData['media'][] = $image;
         }

         if(!empty($this->category->getData('primary_banner_image'))){
             $pbImgUrl = ($this->category->getData('primary_banner_image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('primary_banner_image'):"";
             $primaryBanner['url'] = str_replace("//media", "/media", $pbImgUrl);
             $primaryBanner['media_entity'] = 'CATEGORY';
             $primaryBanner['media_type'] = 'IMAGE';
             $primaryBanner['media_extension'] = (string) pathinfo($this->category->getData('primary_banner_image'),PATHINFO_EXTENSION);
             $primaryBanner['alt_text'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['position'] = 0;
             $primaryBanner['title'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['is_primary_for_store'] = false;
             $primaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->brandData['media'][] = $primaryBanner;
         }

         if(!empty($this->category->getData('secondary_banner_image'))){
             $sbImgUrl = ($this->category->getData('secondary_banner_image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('secondary_banner_image'):"";
             $secondaryBanner['url'] = str_replace("//media", "/media", $sbImgUrl);
             $secondaryBanner['media_entity'] = 'CATEGORY';
             $secondaryBanner['media_type'] = 'IMAGE';
             $secondaryBanner['media_extension'] = (string) pathinfo($this->category->getData('secondary_banner_image'),PATHINFO_EXTENSION);
             $secondaryBanner['alt_text'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['position'] = 0;
             $secondaryBanner['title'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['is_primary_for_store'] = false;
             $secondaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->brandData['media'][] = $secondaryBanner;
         }
         if(count($this->brandData['media'])==0){
            unset($this->brandData['media']);
         }
         $this->brandData['catalog_version'] = "ONLINE";
         return json_encode($this->brandData);
    }

    public function categoryPayload(){
         $image = [];
         $primaryBanner = [];
         $secondaryBanner = [];
         $displayModeArr = [
             'PRODUCTS' => 'PRODUCTS',
             'PAGE' => 'STATIC_BLOCK',
             'PRODUCTS_AND_PAGE' => 'STATIC_BLOCK_N_PRODUCTS'
         ];
         $this->categoryData['category_id'] = $this->category->getData('entity_id');
         if((string)$this->category->getData('parent_id') != $this->rootId){
            $this->categoryData['parent_category_id'] = (string) $this->category->getData('parent_id');
         }
         $this->categoryData['category_name'] = $this->category->getData('name');
         $this->categoryData['category_level_order'] = (int) $this->category->getData('position');
         $this->categoryData['is_active'] = ($this->category->getData('is_active')) ? true :false;
         $this->categoryData['is_published'] = ($this->category->getData('include_in_menu')) ? true : false;
         $this->categoryData['description'] = (trim(strip_tags($this->category->getData('description')))) ? trim(strip_tags($this->category->getData('description'))) : ' ';
         $this->categoryData['category_level'] = (int) $this->category->getData('level');
         $this->categoryData['service_category'] = ($this->category->getData('service_category')) ? $this->category->getData('service_category') : 'LOCAL';
         $this->categoryData['is_promotional'] = false;
         $this->categoryData['category_type'] = ($this->category->getData('category_type')) ? $this->category->getData('category_type') : '';
         $this->categoryData['allowed_channel'] = "ONLINE";
         $this->categoryData['display_mode'] = ($this->category->getData('display_mode')) ?
             $displayModeArr[$this->category->getData('display_mode')] : 'PRODUCTS';
         $this->categoryData['seo_info']['slug'] = $this->category->getData('url_key');
         $this->categoryData['seo_info']['keyword'] = ($this->category->getData('meta_keywords')) ? $this->category->getData('meta_keywords') : '';
         $this->categoryData['seo_info']['meta']['title'] = ($this->category->getData('meta_title')) ? $this->category->getData('meta_title') : '';
         $this->categoryData['seo_info']['meta']['description'] = ($this->category->getData('meta_description')) ? $this->category->getData('meta_description') : '';
         $this->categoryData['seo_info']['cms_block_ids'] = $this->getCmsBlogIdentifier($this->category->getData('plp_content'));
         $this->categoryData['media'] = [];

         if(!empty($this->category->getData('image'))){
             $catImgUrl = ($this->category->getData('image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('image'):"";
             $image['url'] = str_replace("//media", "/media", $catImgUrl);
             $image['media_entity'] = 'TAXONOMY';
             $image['media_type'] = 'IMAGE';
             $image['media_extension'] = (string) pathinfo($this->category->getData('image'),PATHINFO_EXTENSION);
             $image['alt_text'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['position'] = 0;
             $image['title'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['is_primary_for_store'] = true;
             $image['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->categoryData['media'][] = $image;
         }
         if(!empty($this->category->getData('primary_banner_image'))){
             $pbImgUrl = ($this->category->getData('primary_banner_image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('primary_banner_image'):"";
             $primaryBanner['url'] = str_replace("//media", "/media", $pbImgUrl);
             $primaryBanner['media_entity'] = 'CATEGORY';
             $primaryBanner['media_type'] = 'IMAGE';
             $primaryBanner['media_extension'] = (string) pathinfo($this->category->getData('primary_banner_image'),PATHINFO_EXTENSION);
             $primaryBanner['alt_text'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['position'] = 0;
             $primaryBanner['title'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['is_primary_for_store'] = false;
             $primaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->categoryData['media'][] = $primaryBanner;
         }
         if(!empty($this->category->getData('secondary_banner_image'))){
             $sbImgUrl = ($this->category->getData('secondary_banner_image'))?$this->storeManager->getStore()->getBaseUrl().$this->category->getData('secondary_banner_image'):"";
             $secondaryBanner['url'] = str_replace("//media", "/media", $sbImgUrl);
             $secondaryBanner['media_entity'] = 'CATEGORY';
             $secondaryBanner['media_type'] = 'IMAGE';
             $secondaryBanner['media_extension'] = (string) pathinfo($this->category->getData('secondary_banner_image'),PATHINFO_EXTENSION);
             $secondaryBanner['alt_text'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['position'] = 0;
             $secondaryBanner['title'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['is_primary_for_store'] = false;
             $secondaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $this->categoryData['media'][] = $secondaryBanner;
         }
         if(count($this->categoryData['media'])==0){
            unset($this->categoryData['media']);
         }
         $this->categoryData['catalog_version'] = "ONLINE";
         return json_encode($this->categoryData);
    }

    public function validateCategory($path)
    {
        $excludeCategories = $this->scopeConfig->getValue("cate/cat_config/exclude_cagegory");
        $flag = 0;
        if ($excludeCategories!='') {
            $fields =  explode(',', $excludeCategories);
            $categoryPath = explode("/", $path);
            foreach ($fields as $field) {
                if (in_array($field, $categoryPath)) {
                    $flag = 1;
                }
            }
        }
        return $flag;
    }
    public function getAuthToken()
    {
        $this->_coreSession->start();
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
            $this->addLog('Category Auth Curl Initiated');
            $this->addLog($this->generateTokenApi);
            $this->curl->get($this->generateTokenApi);
            $authresult = $this->curl->getBody();
            $authresultData = json_decode($authresult, true);
            $this->addLog('Category Auth Curl response');
            $this->addLog(json_encode($authresultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->addLog("Category Auth Curl There is some error"."=======".$e->getMessage());
        }

        if ($authresultData) {
            $this->addLog('Category Curl Executed');

            if (isset($authresultData['errors'])) {
                $this->addLog(print_r($authresultData['errors'],true));
            }

            if (isset($authresultData['token'])) {
                $tokenResult  = $authresultData['token'];

            }
            $this->_coreSession->setIboAuthToken($tokenResult);
            return $tokenResult;
        }

    }

    public function CurlExecute($payload,$url,$token)
    {
        $returnResult = '';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["x-channel-id" => $this->xChannelId,"Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->addLog(print_r($headers,true));
        $this->curl->setHeaders($headers);

        try {
            $this->addLog('Category Curl Initiated');
            $this->curl->post($url,$payload);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
            $this->addLog('==============response==========================');
            $this->addLog(json_encode($resultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        if ($resultData) {
            $this->addLog('Category Curl Executed');
            $this->categoryData = [];
            $this->brandData = [];
            if (isset($resultData['errors'])) {
                $this->addLog(print_r($resultData['errors'],true));
                $returnResult = "There is some error";
            }
        }

        /*if ($returnResult != '') {
            return 'Error thrown';
        } else {
            return "success";//category_id
        }*/
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
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/catupdate_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

    public function getCmsBlogIdentifier($blogId) {
        try{
            $identifier = [];
            $block = $this->blockFactory->create()->load($blogId);
            if(count($block->getData()) > 0) {
                $identifier = [$block->getIdentifier()];
            }
            return $identifier;
        } catch(Exception $ex) {
            $this->addLog('Exception on Blog identifier - ' . $ex->getMessage());
        }
    }
}
