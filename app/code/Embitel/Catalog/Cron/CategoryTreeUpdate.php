<?php

namespace Embitel\Catalog\Cron;

use Magento\Store\Model\StoreRepository;
use Magento\Catalog\Api\CategoryManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;

class CategoryTreeUpdate
{
    protected $categoryManagement;

    protected $scopeConfig;   

    protected $storeRepository;

    protected $resources;

    protected $includeInMenu = [];

    protected $notIncludeInMenu = [];

    protected $excludeCategoryIds = [];

    protected $categoryParentIds = [];

    protected $localCategoryIds = [];

    protected $regionalCategoryIds = [];

    protected $nationalCategoryIds = [];

    protected $brandCategoryIds = [];

    protected $connection;

    private $categoryData = [];

    private $brandData = [];

    protected $generateTokenApi;

    protected $xAuthToken;

    protected $updatePublishStatusApi;

    protected $xChannelId;

    protected $token = '';

    protected $curl;

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CategoryManagementInterface $categoryManagement
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreRepository $storeRepository,               
        ResourceConnection $resources,
        Curl $curl,        
        CategoryManagementInterface $categoryManagement        
    ) {
       
        $this->storeRepository = $storeRepository;
        $this->scopeConfig = $scopeConfig;      
        $this->resources = $resources;       
        $this->categoryManagement = $categoryManagement;
        $this->connection = $this->resources->getConnection();
        $this->curl = $curl;
    }
    
    /**
     * import source price
     * @return void
     */
    public function execute()
    {   
        $storeList = $this->storeRepository->getList();
        foreach ($storeList as $store) {
            $rootCategoryId = $store->getRootCategoryId();
            if($rootCategoryId > 0){
                $categoryTreeList = $this->categoryManagement->getTree($rootCategoryId);
                $this->addLog("<==============START TREE Update Cron============>");
                $this->addLog("<==============START updateCategoryTree validation=========>");
                $this->updateCategoryTree($categoryTreeList);
                $this->addLog("<==============END updateCategoryTree validation=========>");
                //$this->addLog("<==============START updateBrandTree validation=========>");
                //$this->updateBrandTree($categoryTreeList);
                //$this->addLog("<==============END updateBrandTree validation=========>");
                $this->addLog("<==============START updateData=========>");
                $this->updateData($this->includeInMenu,$this->notIncludeInMenu,$this->localCategoryIds,$this->regionalCategoryIds,$this->nationalCategoryIds);
                $this->addLog("<==============END updateData=========>");
                $this->addLog("<==============START IBO catalog updateData=========>");
                $this->updateIboCategoryNavigation($this->includeInMenu,$this->notIncludeInMenu,$this->localCategoryIds,$this->regionalCategoryIds,$this->nationalCategoryIds,$this->brandCategoryIds);
                $this->addLog("<==============END IBO catalog updateData=========>");
                // $this->addLog("<==============START IBO brand updateData=========>");
                // $this->updateIboBrands($this->includeInMenu,$this->notIncludeInMenu,$this->brandCategoryIds);
                // $this->addLog("<==============END IBO brand updateData=========>");
            }
        }
    }
    
    public function updateBrandTree($categoryTreeList)
    {
        $isCronEnable = $this->scopeConfig->getValue("cate/cat_config/cron_status");
        if (!$isCronEnable) {
            return;
        }
        $this->brandrootId = $this->scopeConfig->getValue("banner_cate/cat_config/banner_parent_cagegory");
        try {

            if (count($categoryTreeList->getChildrenData())>0) {
                foreach ($categoryTreeList->getChildrenData() as $category) {
                   $catId = $category->getId();
                   if($category->getId() != $this->brandrootId && $category->getParentId() != $this->brandrootId)
                   { 
                        continue;
                   }
                   if($category->getId() == $this->brandrootId) {
                    $this->updateBrandTree($category);
                   }
                   $this->brandCategoryIds[] = $catId;                   
                   $catProdCount = $category->getProductCount();
                   if ($catProdCount<=0) {
                        $this->notIncludeInMenu[] = $catId;
                   } else {
                        if($category->getLevel() == 3 && $category->getIsActive() == 1){
                            $categoryProduct = $this->getCategoryProductCount($catId, $catId, $catId);
                            if($categoryProduct > 0){
                                $this->includeInMenu[] = $catId;                                
                            }else{
                                $this->notIncludeInMenu[] = $catId;
                                if(!in_array($category->getParentId(), $this->includeInMenu)){
                                    $this->notIncludeInMenu[] = $category->getParentId();
                                }
                            }
                        }
                   }                   
                }                
            }else{                
                return $categoryTreeList;
            }
        } catch (LocalizedException $e) {           
            $this->addLog($e->getMessage());
        } catch (\Exception $e) {            
            $this->addLog($e->getMessage());
        }
    }
    public function updateCategoryTree($categoryTreeList)
    {
        $isCronEnable = $this->scopeConfig->getValue("cate/cat_config/cron_status");
        if (!$isCronEnable) {
            return;
        }

        $excludeCategories = $this->scopeConfig->getValue("cate/cat_config/exclude_cagegory");
        if ($excludeCategories!='') {
           $this->excludeCategoryIds =  explode(',', $excludeCategories);
        }
                
        try {

            if (count($categoryTreeList->getChildrenData())>0) {
                foreach ($categoryTreeList->getChildrenData() as $category) {
                   $catId = $category->getId(); 
                   if(in_array($category->getId(),$this->excludeCategoryIds)){ 
                        continue;
                   }                     
                   $catProdCount = $category->getProductCount();
                   if ($catProdCount<=0) {
                        $this->notIncludeInMenu[$catId] = $catId;
                   } else {                    
                        if($category->getLevel() == 4 && $category->getIsActive() == 1){
                            $parentCategoryParentId = $this->getCategoryParentId($category->getParentId());
                            $categoryProduct = $this->getCategoryProductCount($catId, $category->getParentId(),$parentCategoryParentId);
                            if($categoryProduct > 0){
                                $this->includeInMenu[$catId] = $catId;
                                if(in_array($category->getParentId(), $this->notIncludeInMenu)){
                                     $this->addLog("<==============notIncludeInMenuCatId=========>".$category->getParentId());
                                    unset($this->notIncludeInMenu[$category->getParentId()]);
                                }
                                $this->includeInMenu[$category->getParentId()] = $category->getParentId();
                                if(in_array($parentCategoryParentId, $this->notIncludeInMenu)){
                                     $this->addLog("<==============notIncludeInMenuCatId=========>".$parentCategoryParentId);
                                    unset($this->notIncludeInMenu[$parentCategoryParentId]);
                                }
                                $this->includeInMenu[$parentCategoryParentId] = $parentCategoryParentId;
                            }else{
                                $this->notIncludeInMenu[$catId] = $catId;
                                if(!in_array($category->getParentId(), $this->includeInMenu)){
                                    $this->notIncludeInMenu[$category->getParentId()] = $category->getParentId();
                                }                                
                                if(!in_array($parentCategoryParentId, $this->includeInMenu))
                                {
                                    $this->notIncludeInMenu[$parentCategoryParentId] = $parentCategoryParentId;
                                }
                            }
                        }/*else{
                            $this->includeInMenu[] = $catId;
                        }*/
                   }
                   if (count($category->getChildrenData())>0) {
                    $this->updateCategoryTree($category);
                   }
                }
                
            }else{                
                return $categoryTreeList;
            }
        } catch (LocalizedException $e) {
            $this->addLog($e->getMessage());
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
        }
    }

    public function getCategoryParentId($categoryId){
        if(empty($this->categoryParentIds[$categoryId])){
            $categoryQuery = "SELECT parent_id FROM `catalog_category_entity` WHERE `entity_id` = '$categoryId'";
            $catParentId = $this->connection->fetchOne($categoryQuery);
            $this->categoryParentIds[$categoryId] = $catParentId;
        }else{
            $catParentId = $this->categoryParentIds[$categoryId];
        }
        return $catParentId;
    }
    public function getCategoryProductCount($categoryId, $categoryParentId, $parentcatParentId){
        $serviceCategory = [];
        $connection= $this->resources->getConnection();
        $categorySql = "SELECT `e`.*,cpei.value as service_category,cpes.value as is_published, `cat_index`.`position` AS `cat_index_position` FROM `catalog_product_entity` AS `e` INNER JOIN `catalog_category_product_index_store1` AS `cat_index` ON cat_index.product_id=e.entity_id AND cat_index.store_id=1 AND cat_index.visibility IN(".\Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG.",". \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH.",". \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH.") AND cat_index.category_id = ".$categoryId." INNER JOIN `cataloginventory_stock_status` AS `stock_status_index` ON e.entity_id = stock_status_index.product_id AND stock_status_index.website_id = 0 AND stock_status_index.stock_id = 1 ";
        $categorySql .=" LEFT JOIN catalog_product_entity_varchar cpei ON 
                         e.entity_id = cpei.entity_id &&  cpei.attribute_id = 
                        (SELECT attribute_id FROM eav_attribute WHERE  entity_type_id =
                        (SELECT entity_type_id FROM   eav_entity_type
                        WHERE  entity_type_code = 'catalog_product') && attribute_code = 'service_category')";
        $categorySql .=" LEFT JOIN catalog_product_entity_int cpes ON 
                        e.entity_id = cpes.entity_id && cpes.attribute_id =
                        (SELECT attribute_id FROM   eav_attribute WHERE  entity_type_id =
                        (SELECT entity_type_id FROM   eav_entity_type
                        WHERE  entity_type_code = 'catalog_product') && attribute_code = 'is_published')";
        $categorySql .= " WHERE ((stock_status_index.stock_status=1)) AND cpes.value = 1 AND cpei.value != ''";
        $result = $connection->fetchAll($categorySql);

        if(count($result) > 0){
            $serviceCategory = array_unique(array_column($result, 'service_category'));
            if(in_array('NATIONAL', $serviceCategory)){              
              $this->nationalCategoryIds[$categoryId] = $categoryId; 
              $this->nationalCategoryIds[$categoryParentId] = $categoryParentId; 
              $this->nationalCategoryIds[$parentcatParentId] = $parentcatParentId; 
              if(in_array($categoryParentId, $this->localCategoryIds)){
                unset($this->localCategoryIds[$categoryParentId]);
              }
              if(in_array($categoryParentId, $this->regionalCategoryIds)){
                unset($this->regionalCategoryIds[$categoryParentId]);
              }
              if(in_array($parentcatParentId, $this->localCategoryIds)){
                unset($this->localCategoryIds[$parentcatParentId]);
              }
              if(in_array($parentcatParentId, $this->regionalCategoryIds)){
                unset($this->regionalCategoryIds[$parentcatParentId]);
              }
            }elseif (in_array('REGIONAL', $serviceCategory)) {
                $this->regionalCategoryIds[$categoryId] = $categoryId;
                if(!in_array($categoryParentId, $this->nationalCategoryIds)){
                    if(in_array($categoryParentId, $this->localCategoryIds)){
                       unset($this->localCategoryIds[$categoryParentId]);
                    }
                    $this->regionalCategoryIds[$categoryParentId] = $categoryParentId;
                }
                if(!in_array($parentcatParentId, $this->nationalCategoryIds)){
                    if(in_array($parentcatParentId, $this->localCategoryIds)){
                       unset($this->localCategoryIds[$parentcatParentId]);
                    }
                    $this->regionalCategoryIds[$parentcatParentId] = $parentcatParentId;
                }
            }else {
              $this->localCategoryIds[$categoryId] = $categoryId;
              if(!in_array($categoryParentId, $this->nationalCategoryIds) && !in_array($categoryParentId, $this->regionalCategoryIds)){
                $this->localCategoryIds[$categoryParentId] = $categoryParentId;
              }
              if(!in_array($parentcatParentId, $this->nationalCategoryIds) && !in_array($parentcatParentId, $this->regionalCategoryIds)){
                $this->localCategoryIds[$parentcatParentId] = $parentcatParentId;
              }
          }
      }
        return count($result);
    }

    public function updateData($includeCatIds,$notincludeCatIds,$localCategoryIds,$regionalCategoryIds,$nationalCategoryIds){

        $connection= $this->resources->getConnection();

        if (count($notincludeCatIds)>0) {
            $setNotIncludeInMenu = "UPDATE catalog_category_entity_int as cci
            INNER JOIN eav_attribute as a ON a.attribute_id = cci.attribute_id
            INNER JOIN catalog_category_entity as cce ON cce.entity_id = cci.entity_id
            SET cci.value = 0 WHERE a.attribute_code = 'include_in_menu' AND cci.store_id = 0 
            AND cce.entity_id IN (".implode(',', $notincludeCatIds).")";
            $this->addLog("<==============notIncludeInMenu=========>".$setNotIncludeInMenu);
            $connection->query($setNotIncludeInMenu);
        }
        if (count($includeCatIds)>0) {
            $setIncludeInMenu = "UPDATE catalog_category_entity_int as cci 
            INNER JOIN eav_attribute as a ON a.attribute_id = cci.attribute_id 
            INNER JOIN catalog_category_entity as cce ON cce.entity_id = cci.entity_id 
            SET cci.value = 1 
            WHERE (a.attribute_code = 'include_in_menu' OR a.attribute_code = 'is_anchor') AND cci.store_id = 0 
            AND cce.entity_id IN (".implode(',', $includeCatIds).")";
            $this->addLog("<==============includeInMenu============>".$setIncludeInMenu);
            $connection->query($setIncludeInMenu);
        }
        if (count($localCategoryIds)>0) {
            $setLocalCategoryIds = "UPDATE catalog_category_entity_varchar as ccev 
            INNER JOIN eav_attribute as a ON a.attribute_id = ccev.attribute_id 
            INNER JOIN catalog_category_entity as cce ON cce.entity_id = ccev.entity_id 
            SET ccev.value = 'LOCAL' 
            WHERE (a.attribute_code = 'service_category' and a.entity_type_id = 3) AND ccev.store_id = 0 
            AND cce.entity_id IN (".implode(',', $localCategoryIds).")";
            $this->addLog("<==============localCategoryIds============>".$setLocalCategoryIds);
            $connection->query($setLocalCategoryIds);
        }
        if (count($regionalCategoryIds)>0) {
            $setRegionalCategoryIds = "UPDATE catalog_category_entity_varchar as ccev 
            INNER JOIN eav_attribute as a ON a.attribute_id = ccev.attribute_id 
            INNER JOIN catalog_category_entity as cce ON cce.entity_id = ccev.entity_id 
            SET ccev.value = 'REGIONAL' 
            WHERE (a.attribute_code = 'service_category') AND ccev.store_id = 0 
            AND cce.entity_id IN (".implode(',', $regionalCategoryIds).")";
            $this->addLog("<==============regionalCategoryIds============>".$setRegionalCategoryIds);
            $connection->query($setRegionalCategoryIds);
        }
        if (count($nationalCategoryIds)>0) {
            $setNationalCategoryIds = "UPDATE catalog_category_entity_varchar as ccev 
            INNER JOIN eav_attribute as a ON a.attribute_id = ccev.attribute_id 
            INNER JOIN catalog_category_entity as cce ON cce.entity_id = ccev.entity_id 
            SET ccev.value = 'NATIONAL' 
            WHERE (a.attribute_code = 'service_category') AND ccev.store_id = 0 
            AND cce.entity_id IN (".implode(',', $nationalCategoryIds).")";
            $this->addLog("<==============nationalCategoryIds============>".$setNationalCategoryIds);
            $connection->query($setNationalCategoryIds);
        }
        $this->addLog("<==============END TREE Update Cron============>");
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
                "cate/cat_config/catupdate_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/catupdate_tree.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

    public function updateIboBrands($includeCatIds, $notincludeCatIds, $brandCategoryIds)
    {
        $isEnable = $this->scopeConfig->getValue("cate/ibo_cat_config/cron_status");
        if (!$isEnable) {
            $this->addLog('IBO category service sync not enable.');
            return;
        }

        $this->generateTokenApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/generate_token_api"));
        $this->xAuthToken = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_auth_token"));
        $this->updateBrandPublishStatusApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/update_brand_publish_status_api"));
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->updateBrandPublishStatusApi) || empty($this->xChannelId)){
             $this->addLog('IBO category sync configuration missing.');
             return;
        }
        try{
            if (count($includeCatIds)>0) {
                foreach (array_unique($includeCatIds) as $inckey => $incvalue) {
                  if(in_array($incvalue, $brandCategoryIds)){
                    $this->brandData[] = ["brand_id" => $incvalue,"is_active" => true, "is_published" => true];
                    }  
                }
            }
            if (count($notincludeCatIds)>0) {
                foreach (array_unique($notincludeCatIds) as $notinckey => $notincvalue) {
                  if(in_array($notincvalue, $brandCategoryIds)){
                    $this->brandData[] = ["brand_id" => $notincvalue,"is_active" =>true,"is_published" => false];
                    }
                }
            }
            $brandPayload = json_encode($this->brandData);
            if($this->token == ''){ 
                $this->token = $this->getAuthToken();
            }
            if($this->token != ''){
                $this->addLog('==============request==========================');
                $this->addLog(json_encode($this->brandData,JSON_PRETTY_PRINT));
                $this->addLog('==============response==========================');
                $this->curlExecute($brandPayload,$this->updateBrandPublishStatusApi,$this->token);
            }
        }catch(Exception $e){
            $this->addLog('Exception on ibo brand category publish - ' . $e->getMessage());
        }
    }

    public function updateIboCategoryNavigation($includeCatIds, $notincludeCatIds, $localCategoryIds, $regionalCategoryIds, $nationalCategoryIds, $brandCategoryIds)
    {
        $isEnable = $this->scopeConfig->getValue("cate/ibo_cat_config/cron_status");
        if (!$isEnable) {
            $this->addLog('IBO category service sync not enable.');
            return;
        }
        $this->categoryData["catalog_version"] = "ONLINE";
        $this->generateTokenApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/generate_token_api"));
        $this->xAuthToken = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_auth_token"));
        $this->updatePublishStatusApi = trim($this->scopeConfig->getValue("cate/ibo_cat_config/update_publish_status_api"));
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->updatePublishStatusApi) || empty($this->xChannelId)){
             $this->addLog('IBO category sync configuration missing.');
             return;
        }
        try{
            if (count($includeCatIds)>0) {
                $incServiceCategory = "";
                foreach (array_unique($includeCatIds) as $inckey => $incvalue) {
                  if(!in_array($incvalue, $brandCategoryIds)){
                      if(in_array($incvalue, $localCategoryIds)){
                        $incServiceCategory = "LOCAL";
                      }else if(in_array($incvalue, $regionalCategoryIds)){
                        $incServiceCategory = "REGIONAL";
                      }else if(in_array($incvalue, $nationalCategoryIds)){
                        $incServiceCategory = "NATIONAL";
                      }
                      $this->categoryData['categories'][] = ["category_id" => $incvalue,"service_category" => $incServiceCategory, "is_active" => true, "is_published" => true];                   
                  }  
                }
            }

            if (count($notincludeCatIds)>0) {
                $notIncServiceCategory = "LOCAL";
                foreach (array_unique($notincludeCatIds) as $notinckey => $notincvalue) {
                  if(!in_array($notincvalue, $brandCategoryIds)){
                      if(in_array($notincvalue, $localCategoryIds)){
                        $notIncServiceCategory = "LOCAL";
                      }else if(in_array($notincvalue, $regionalCategoryIds)){
                        $notIncServiceCategory = "REGIONAL";
                      }else if(in_array($notincvalue, $nationalCategoryIds)){
                        $notIncServiceCategory = "NATIONAL";
                      }
                      $this->categoryData['categories'][] = ["category_id" => $notincvalue,"service_category" => $notIncServiceCategory, "is_active" => true, "is_published" => false];
                    }
                }
            }
            $payload = json_encode($this->categoryData);
            if($this->token == ''){ 
                $this->token = $this->getAuthToken();
            }
            if($this->token != ''){
                $this->addLog('==============request==========================');
                $this->addLog(json_encode($this->categoryData,JSON_PRETTY_PRINT));
                $this->addLog('==============response==========================');
                $this->curlExecute($payload,$this->updatePublishStatusApi,$this->token);                
            }
        }catch(Exception $e){
            $this->addLog('Exception on ibo category publish - ' . $e->getMessage());
        }
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

    public function curlExecute($payload,$url,$token)
    {
        $returnResult = '';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $this->curl->setOption(CURLOPT_TIMEOUT, 10);
        
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
            
            if (isset($resultData['errors'])) {
                $this->addLog(print_r($resultData['errors'],true));
                $returnResult = "There is some error";
            }
        }

        if ($returnResult != '') {
            return 'Error thrown';
        } else {
            return "success";
        }
    }
}