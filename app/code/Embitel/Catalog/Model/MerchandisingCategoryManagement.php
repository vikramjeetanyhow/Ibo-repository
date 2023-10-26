<?php
/**
 * @category   Embitel
 * @package    Embitel_Catalog
 * @author     Hitendra Badiani <hitendra.badiani@embitel.com>
 */

namespace Embitel\Catalog\Model;

use Exception;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory; 
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Embitel\ProductImport\Model\Import\CsvProcessor;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as AttributeGroupCollection;

/**
 * Handles the merchandising category tree.
 */
class MerchandisingCategoryManagement
{
    private $category = null;
    private $categoryData = [];
    private $attributeData = [];
    private $coreAttributesData = [];
    private $defaultVariantAttributes = [];
    private $facetAttributes = [];
    protected $curl;
    protected $storeManager;
    protected $categoryFactory;
    protected $scopeConfig;
    protected $logger;
    protected $generateTokenApi;
    protected $xAuthToken;
    protected $createMerchCategoryApi;
    protected $xChannelId;
    protected $token = '';
    protected $configurableMethod = null;
    protected $attributeSetRepository;    
    private $attributeGroupCollection;
    private $productAttributeCollection;
    protected $csvProcessor;
    protected $merchRootId = '';
    protected $path = ["brand_color" => "/brand_info/brand_color","ebo_color" => "/ebo_color","pack_of" => "/pack_of"];
    protected $excludeAttributes = [];
    protected $coreAttributes = [];

    /**
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(        
        CategoryFactory $categoryFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        CsvProcessor $csvProcessor,
        AttributeSetRepositoryInterface $attributeSetRepository,        
        AttributeGroupCollection $attributeGroupCollection,
        ProductAttributeCollection $productAttributeCollection,
        Curl $curl        
    ) {        
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->csvProcessor = $csvProcessor;
        $this->attributeSetRepository = $attributeSetRepository;          
        $this->attributeGroupCollection = $attributeGroupCollection;  
        $this->productAttributeCollection = $productAttributeCollection;  
        $this->curl = $curl;
    }

    public function getMerchandisingRootId()
    {
        $this->merchRootId = "";
        $collection = $this->categoryFactory->create()->getCollection()
                ->addFieldToFilter('name','Merchandising Category');       
        if ($collection->getSize()) {
            $this->merchRootId = $collection->getFirstItem()->getId();                  
        }
    }

    public function syncAllMerchandisingCat()
    {
        $this->getMerchandisingRootId();
        $catcollection = $this->categoryCollectionFactory
                    ->create()                   
                    ->addAttributeToSelect('category_id')                    
                    ->addFieldToFilter('path', array('like'=> "1/$this->merchRootId/%"));
          
        foreach ($catcollection->getData() as $merchCatId) {
            $this->syncMerchandisingCat($merchCatId['entity_id']);
        }
    }

    public function syncMerchandisingCat($catId)
    {   
        if(empty($catId)){
            return;
        }
        $this->getMerchandisingRootId();
        $this->generateTokenApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/generate_token_api"));
        $this->xAuthToken = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_auth_token"));
        $this->createMerchCategoryApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/create_merch_category_api"));        
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));

        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->createMerchCategoryApi) || empty($this->xChannelId)){
             $this->addLog('IBO merchandising category sync configuration missing.');
             return;
        }

        try{
             $this->category = '';
             $this->category = $this->categoryFactory->create()->load($catId);
             $this->addLog('<=request===magecatId===='.$this->category->getData('entity_id').'===ibocatId===='.$this->category->getData('category_id') );
             if($this->token == ''){ 
                $this->token = $this->getAuthToken();
             }  
             //!empty($this->category->getData('category_type')) && $this->category->getData('category_type') == 'MERCHANDISING' && 
             if((string)$this->category->getId() != $this->merchRootId){                
                 $payload = $this->merchCategoryPayload();
                 
                 if($this->token != ''){
                     $this->addLog('==============request==========================');
                     $this->addLog(json_encode($this->categoryData,JSON_PRETTY_PRINT));
                     $this->addLog('==============response==========================');
                     //$this->addLog('<=response===magecategoryId===='.$this->category->getData('entity_id').'===ibocategoryId===='.$this->category->getData('category_id') );
                     $this->CurlExecute($payload,$this->createMerchCategoryApi,$this->token);
                 }
                                  
              }else{
                $this->addLog('Requested merchandising categoryId'.$catId.' does not exists.');
                return;
              }
              

         }catch(Exception $e){
            $this->addLog('Exception on category sync - ' . $e->getMessage());
        }
    }

    public function merchCategoryPayload(){
         $this->getExcludeAttributes();
         $this->getCoreAttributes();
         $this->categoryData = [];
         $this->categoryData['category_id'] = $this->category->getData('category_id'); 
         $this->categoryData['category_type'] = ($this->category->getData('category_type')) ? $this->category->getData('category_type') : 'MERCHANDISING';
         $this->categoryData['category_name'] = $this->category->getData('name');
         $this->categoryData['description'] = (trim(strip_tags($this->category->getData('description')))) ? trim(strip_tags($this->category->getData('description'))) : ' ';

         if($this->category->getData('level') != 2){            
         $this->categoryData['parent_category_id'] = (string) $this->category->getData('parent_category_id');
         }

        if($this->category->getData('level') == 4){            
            $this->categoryData['fulfillment_class'] = (string) $this->category->getData('category_fulfillment_class');
        }
        
         $this->categoryData['category_level'] = (int) $this->category->getData('level');
         $this->categoryData['title_name_rule'] = ($this->category->getData('title_name_rule')) ? $this->category->getData('title_name_rule') : '';
         $this->categoryData['product_type'] = 'REGULAR';
         $this->categoryData['allowed_channel'] = "OMNI";
         
         $this->defaultVariantAttributes = $this->facetAttributes = $this->attributeData = $this->coreAttributesData = $this->selPrintingAttibutes = [];
         if($this->category->getData('attribute_set') != '' && $this->category->getData('attribute_set') != null && $this->category->getData('attribute_set') > 0)
         {
            $navCategoryId = $attributeSet = $attributeSetName = '';
            $attributeSet = $this->attributeSetRepository->get($this->category->getData('attribute_set'));
            $attributeSetName = $attributeSet->getAttributeSetName();
            $this->loadConfigFields($attributeSetName);
            $navCategoryId = $this->getNavigationCatId($this->category->getData('category_id'));
            
            $this->getAttributeListBySetId($this->category->getData('attribute_set'),$navCategoryId);
            ksort($this->defaultVariantAttributes);
            $this->categoryData['default_variant_attributes'] = $this->defaultVariantAttributes;
            ksort($this->selPrintingAttibutes);
           // $this->selPrintingAttibutes = array_merge($this->selPrintingAttibutes);
            $this->categoryData['sel_printing_attributes'] = $this->selPrintingAttibutes;
            $this->categoryData['attributes'] = $this->attributeData;
            $this->categoryData['store_info'] = ["store_fulfilment_modes" => ["CNC","DWH"],
                                                 "default_sel_type" => "TYPE-1"
                                                ];
            $this->categoryData['core_attributes'] = $this->coreAttributesData;
            $this->categoryData['facet_attributes'] = $this->facetAttributes;            
         }
         if($this->category->getData('service_category') != ''){            
            $this->categoryData['service_category'] = $this->category->getData('service_category');
         }
         $this->categoryData['is_active'] = ($this->category->getData('is_active')) ? true :false;
         $this->categoryData['is_published'] = true;
         $this->categoryData['media'] = [];

         if(!empty($this->category->getData('base_image_custom'))){
             
             $image['url'] = $this->category->getData('base_image_custom');
             $image['media_entity'] = 'TAXONOMY';
             $image['media_type'] = 'IMAGE';
             $image['media_extension'] = (string) pathinfo($this->category->getData('base_image_custom'),PATHINFO_EXTENSION);
             $image['alt_text'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['position'] = 0;
             $image['title'] = ($this->category->getData('name')) ? $this->category->getData('name') :'';
             $image['is_primary_for_store'] = true;
             $image['is_primary_for_scm'] = false;
             $image['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $image['is_external'] = false;
             $this->categoryData['media'][] = $image;            
         }
         if(!empty($this->category->getData('primary_banner_image_custom'))){
             
             $primaryBanner['url'] = $this->category->getData('primary_banner_image_custom');
             $primaryBanner['media_entity'] = 'CATEGORY';
             $primaryBanner['media_type'] = 'IMAGE';
             $primaryBanner['media_extension'] = (string) pathinfo($this->category->getData('primary_banner_image_custom'),PATHINFO_EXTENSION);
             $primaryBanner['alt_text'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['position'] = 0;
             $primaryBanner['title'] = ($this->category->getData('primary_banner_title')) ? $this->category->getData('primary_banner_title') : '';
             $primaryBanner['is_primary_for_store'] = false;
             $primaryBanner['is_primary_for_scm'] = false;
             $primaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $primaryBanner['is_external'] = false;
             $this->categoryData['media'][] = $primaryBanner;            
         }
         if(!empty($this->category->getData('secondary_banner_image_custom'))){
             
             $secondaryBanner['url'] = $this->category->getData('secondary_banner_image_custom');
             $secondaryBanner['media_entity'] = 'CATEGORY';
             $secondaryBanner['media_type'] = 'IMAGE';
             $secondaryBanner['media_extension'] = (string) pathinfo($this->category->getData('secondary_banner_image_custom'),PATHINFO_EXTENSION);
             $secondaryBanner['alt_text'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['position'] = 0;
             $secondaryBanner['title'] = ($this->category->getData('secondary_banner_title')) ? $this->category->getData('secondary_banner_title') : '';
             $secondaryBanner['is_primary_for_store'] = false;
             $secondaryBanner['is_primary_for_scm'] = false;
             $secondaryBanner['target_url'] = $this->storeManager->getStore()->getBaseUrl();
             $secondaryBanner['is_external'] = false;
             $this->categoryData['media'][] = $secondaryBanner;            
         }
         if(count($this->categoryData['media'])==0){
            unset($this->categoryData['media']);
         }
         if(isset($this->categoryData['default_variant_attributes']) && count($this->categoryData['default_variant_attributes'])==0){
            unset($this->categoryData['default_variant_attributes']);
         }
         if(isset($this->categoryData['facet_attributes']) && count($this->categoryData['facet_attributes'])==0){
            unset($this->categoryData['facet_attributes']);
         } 
         if(isset($this->categoryData['core_attributes']) && count($this->categoryData['core_attributes'])==0){
            unset($this->categoryData['core_attributes']);
         }  
         if(isset($this->categoryData['sel_printing_attributes']) && count($this->categoryData['sel_printing_attributes'])==0){
            unset($this->categoryData['sel_printing_attributes']);
         }       
         $this->categoryData['catalog_version'] = "ONLINE";
         return json_encode($this->categoryData);
    }

    public function getNavigationCatId($iboCategoryId)
    {
        $categoryId = '';
        $rootId = $this->storeManager->getStore()->getRootCategoryId();

        $collection = $this->categoryFactory->create()->getCollection()
                    ->addAttributeToFilter('category_id',$iboCategoryId)
                    ->addFieldToFilter('path', array('like'=> "1/$rootId/%"));
       
        if ($collection->getSize()) {
            $categoryId = $collection->getFirstItem()->getId();
        }
        return $categoryId;
    }

    public function getExcludeAttributes()
    {
         $excludeAttributeslist = trim($this->scopeConfig->getValue("catalog_service/general/exclude_attribute_list"));        
        if($excludeAttributeslist != '') {
            $this->excludeAttributes = explode(',',$excludeAttributeslist);
        }

        $selMatchArrays = array_intersect($this->getCoreAttributesData(),$this->getSelAttributesData());

        foreach ($selMatchArrays as $seldata) {
            $key = array_search($seldata, $this->excludeAttributes);
            if (false !== $key) {
                $this->addLog($seldata.' attribute removed');
                unset($this->excludeAttributes[$key]);
            }
        }
    }

    public function getCoreAttributes()
    {
        $coreAttributeslist = trim($this->scopeConfig->getValue("catalog_service/general/core_attribute_list"));        
        if($coreAttributeslist != '') {
            $this->coreAttributes = explode(',',$coreAttributeslist);
        }
    }

    public function getCoreAttributesData()
    {
        $coreAttributes = [];
        $coreAttributeslist = trim($this->scopeConfig->getValue("catalog_service/general/core_attribute_list"));        
        if($coreAttributeslist != '') {
            $coreAttributes = explode(',',$coreAttributeslist);
        }
        return $coreAttributes;
    }

    public function getAttributeListBySetId($attributeSetId, $navCategoryId)
    {        
        $groupIds = $this->defaultVariantAttributes = $this->facetAttributes = $this->attributeData = $this->coreAttributesData = $this->selPrintingAttibutes = [];
        try {
            
            $groupCollection = $this->attributeGroupCollection->create()
                ->setAttributeSetFilter($attributeSetId)
                ->load(); // product attribute group collection
            $i = $position = 0;
            foreach ($groupCollection as $group) {
                
                $groupIds[$group->getData('attribute_group_id')] = $group->getData('attribute_group_name');                
                
                $groupAttributesCollection = $this->productAttributeCollection->create()
                    ->setAttributeGroupFilter($group->getId())
                    ->addVisibleFilter()
                    ->load(); 

                $attPayload = $coreAttPayload = [];
                $isFilterable = false;                
                
                foreach ($groupAttributesCollection->getItems() as $attribute) {
                  if((count($this->getDefaultVariantAttributes()) > 0 && in_array($attribute->getData('attribute_code'), $this->getDefaultVariantAttributes())) || !in_array($attribute->getData('attribute_code'), $this->excludeAttributes))
                  {
                    
                    if(count($this->getDefaultVariantAttributes()) > 0 && in_array($attribute->getData('attribute_code'), $this->getDefaultVariantAttributes())){
                        $position = array_search($attribute->getData('attribute_code'), $this->getDefaultVariantAttributes());
                        $this->defaultVariantAttributes[$position] = [
                            "group" => $groupIds[$attribute->getData('attribute_group_id')],
                            "code" => $attribute->getData('attribute_code'),
                            "position" => $position,
                            "type" => in_array($attribute->getData('attribute_code'), $this->coreAttributes) ? "CORE_ATTRIBUTES" : "ATTRIBUTES"                            
                        ];
                        $i++;
                        $isMandatory = true;
                    }else{
                        $isMandatory = false;
                    }

                    if(count($this->getSelAttributesData()) > 0 && in_array($attribute->getData('attribute_code'), $this->getSelAttributesData())){
                        $position = array_search($attribute->getData('attribute_code'), $this->getSelAttributesData());
                        $this->selPrintingAttibutes[$position] = [
                            "group" => $groupIds[$attribute->getData('attribute_group_id')],
                            "code" => $attribute->getData('attribute_code'),
                            "position" => $position,
                            "type" => in_array($attribute->getData('attribute_code'), $this->coreAttributes) ? "CORE_ATTRIBUTES" : "ATTRIBUTES"                            
                        ];
                        $i++;
                    }

                    if(in_array($attribute->getData('attribute_code'), ['price','brand_Id'])){
                        $this->facetAttributes[] = [
                                "group" => $groupIds[$attribute->getData('attribute_group_id')],
                                "position" => 0,
                                "code" => $attribute->getData('attribute_code')
                            ];
                        $isFilterable = true;
                    }elseif($navCategoryId != '' && $attribute->getData('attribute_category_ids') != '')
                    {
                        if(in_array($navCategoryId, json_decode($attribute->getData('attribute_category_ids'))))
                        {
                            $this->facetAttributes[] = [
                                "group" => $groupIds[$attribute->getData('attribute_group_id')],
                                "position" => 0,
                                "code" => $attribute->getData('attribute_code')
                            ];
                            $isFilterable = true;                            
                        }
                        
                    }else{
                        $isFilterable = false;
                    }

                    if(!$isMandatory){
                       $isMandatory = ($attribute->getData('is_required')) ? true : false;
                    }

                    if(in_array($attribute->getData('attribute_code'),$this->coreAttributes))
                    {
                        $coreAttPayload = [
                        "group" => $groupIds[$attribute->getData('attribute_group_id')],
                        "code" => $attribute->getData('attribute_code'),
                        "path" => (array_key_exists($attribute->getData('attribute_code'), $this->path)) ? $this->path[$attribute->getData('attribute_code')] : "/attributes",
                        "display_name" => $attribute->getData('frontend_label'),
                        "values_allowed" => (in_array($attribute->getData('frontend_input'), ["select", "multiselect"])) ? $this->getAttributeOptions($attribute) : [],                 
                        "metadata" => ["type"=> "string",
                                      "is_mandatory" => $isMandatory,
                                      "is_displayable" => ($attribute->getData('used_in_product_specs')) ? true : false,
                                      "is_searchable" => ($attribute->getData('is_searchable')) ? true : false,
                                      "is_facetable" => $isFilterable
                                  ]
                        ];                   
                        $this->coreAttributesData[] = $coreAttPayload;
                    }else{
                        $attPayload = [
                        "group" => $groupIds[$attribute->getData('attribute_group_id')],
                        "position" => (int) $attribute->getData('product_specs_position'),
                        "code" => $attribute->getData('attribute_code'),
                        "display_name" => $attribute->getData('frontend_label'),
                        "values_allowed" => (in_array($attribute->getData('frontend_input'), ["select", "multiselect"])) ? $this->getAttributeOptions($attribute) : [],                 
                        "metadata" => ["type"=> "string",
                                      "is_mandatory" => $isMandatory,
                                      "is_displayable" => ($attribute->getData('used_in_product_specs')) ? true : false,
                                      "is_searchable" => ($attribute->getData('is_searchable')) ? true : false,
                                      "is_facetable" => $isFilterable
                                  ]
                        ];                   
                        $this->attributeData[] = $attPayload;                        
                    }

                }
              }
            }
        
        } catch (NoSuchEntityException $exception) {
            throw new NoSuchEntityException(__($exception->getMessage()));
        }        
        return $this->attributeData;
    }

    public function getAttributeOptions($attribute)
    {   
        $attibuteOptions = [];
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            $label = (string) $option->getLabel();
            $optionLabel = trim($label);
            if ($optionLabel != "" && $optionLabel != null && !in_array($optionLabel, $attibuteOptions)) {
                $attibuteOptions[] = $optionLabel;
            }
        }
        return $attibuteOptions;
    }

    public function getDefaultVariantAttributes()
    {
        $defaultVariant = [];
        if(!is_null($this->configurableMethod)){
            foreach (explode(",", $this->configurableMethod['ConfigurableAttributes']) as $attributeCode) {
                $defaultVariant[] = $attributeCode;            
            }            
        }
        return $defaultVariant;
    }  

    public function getSelAttributesData() {
        $selData = $this->category->getData('sel_specification_rule');
        $dataG = [];
        if($selData != ''){
            $dataArray = explode('|',$selData);
            foreach($dataArray as $key => $value) {
                $datad = explode(',',$value);
                foreach($datad as $dataE) {
                    if (!(strpos($dataE,'[') !== false)) { 
                        if(trim($dataE) != '') {
                            $dataG[] = trim($dataE);
                        }
                     } 
                   }
            }
        }
        return $dataG;
    }

    public function loadConfigFields($attributeSetCode)
    {   
        $this->configurableMethod = null;
        if (!$this->configurableMethod) {
            $this->configurableMethod = $this->csvProcessor->getEvalFormula($attributeSetCode);
        }
    }

    /**
     * Read CSV data
     *
     * @param type $fileName
     * @return type
     */
    public function getCsvData($fileName)
    {
        return $this->csv->getData($fileName);
    }

    public function getAuthToken()
    {
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
            return $tokenResult;
        }       

    }

    public function CurlExecute($payload,$url,$token)
    {
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
            if (isset($resultData['errors'])) {
                $this->addLog('<=response===magecategoryId===='.$this->category->getData('entity_id').'===ibocategoryId===='.$this->category->getData('category_id') );
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
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/merch_category_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }    
}