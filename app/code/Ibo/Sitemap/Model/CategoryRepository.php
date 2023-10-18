<?php

namespace Ibo\Sitemap\Model;

use Ibo\Sitemap\Api\CategoryRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Webapi\Rest\Request; 
use Embitel\Catalog\Model\BrandCatalogServicePush;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * @var categoriesSeoData
     */
    public $categoriesSeoData;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Catalog\Api\CategoryManagementInterface $categoryManagement,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        BrandCatalogServicePush $brandCatalogServicePush,
        Request $request
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->categoryManagement = $categoryManagement;
        $this->helper = $helper;
        $this->categoryFactory = $categoryFactory;
        $this->eventManager = $eventManager;
        $this->categoriesSeoData = [];
        $this->brandCatalogServicePush = $brandCatalogServicePush;
        $this->request = $request;
    }

    /**
     * GET API
     * @api
     * 
     * @return array
     */
    public function get() {
        $this->addLog("==========================================");
        $this->addLog("Start navigation categories meta-details get process");
        $error = "";
        try {
            $rootCategoryId = 2;
            $categoryTreeList = $this->getChildCategories($rootCategoryId);
            if (count($categoryTreeList->getChildrenData()) > 0) {
                $this->getCategoryList($categoryTreeList->getChildrenData());
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
       
        $data = array();
        if(!empty($error)) {
            $data['error'] = true;
            $data['message'] = $error;
            $this->addLog($error);
        } else {
            $data['error'] = false;
            $data['categories'] = json_decode(json_encode($this->categoriesSeoData, JSON_INVALID_UTF8_SUBSTITUTE), true);
            $this->addLog("success");
        }
        $this->addLog("End navigation categories meta-details get process");
        $this->addLog("==========================================");
        return [$data];
    }

    /**
     * Get API Brand Root Id 
     * @return array
     */
    public function getBrand(){

        
        $this->addLog("==========================================");
        $this->addLog("Start Brand categories meta-details get process");
        
        $responce = ["success"=>"false"];
        try {
            $iboCatId = $this->request->getParam('ibo_category_id');
            $rootCategoryId = 1334;

            if($iboCatId){
                $this->getCategoryBrandList_withFilter($iboCatId);
            }else{

                $categoryTreeList = $this->getChildCategories($rootCategoryId);
                if (count($categoryTreeList->getChildrenData()) > 0) {
                    $this->getCategoryBrandList($categoryTreeList->getChildrenData());
                }
            }

            $responce = ["success"=>"true", "categories"=>json_decode(json_encode($this->categoriesSeoData, JSON_INVALID_UTF8_SUBSTITUTE), true)];
            $this->addLog("success");
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $responce = ["success"=>"false", "error"=>$error];
        }

        $this->addLog("End Brand categories meta-details get process");
        $this->addLog("==========================================");
        return [$responce];
    }

    private function getCategoryList($categoryList) {
        if(!empty($categoryList)) {
            foreach ($categoryList as $categoryTreeData) {
                $category = $this->categoryFactory->create()->load($categoryTreeData->getId());
                $this->categoriesSeoData[] = array(
                    "id" => (int)$category->getId(),
                    "parent_id" => $category->getParentId(),
                    "name" => html_entity_decode($category->getName()),
                    "url" => html_entity_decode(strtolower($category->getName()) . "/c/" . $category->getId()),
                    "meta_title" => html_entity_decode($category->getMetaTitle()),
                    "meta_description" => html_entity_decode($category->getMetaDescription()),
                    "meta_keywords" => html_entity_decode($category->getMetaKeywords())
                    
                );

                $categoryChildTreeList = $this->getChildCategories($category->getId());
                if (count($categoryChildTreeList->getChildrenData()) > 0) {
                    $this->getCategoryList($categoryChildTreeList->getChildrenData());
                }
            }
        }
    }
    /**
     * For brand category List
     */
    private function getCategoryBrandList($categoryList) {
        if(!empty($categoryList)) {
            foreach ($categoryList as $categoryTreeData) {
                $category = $this->categoryFactory->create()->load($categoryTreeData->getId());
                $this->categoriesSeoData[] = array(
                    "id" => (int)$category->getId(),
                    "parent_id" => $category->getParentId(),
                    "brand_id" => $category->getData('ibo_brand_id'),
                    "name" => html_entity_decode($category->getName()),
                    "url" => html_entity_decode(strtolower($category->getName()) . "/b/" . $category->getData('ibo_brand_id')),
                    "meta_title" => html_entity_decode($category->getMetaTitle()),
                    "meta_description" => html_entity_decode($category->getMetaDescription()),
                    "meta_keywords" => html_entity_decode($category->getMetaKeywords())
                    
                );

                $categoryChildTreeList = $this->getChildCategories($category->getId());
                if (count($categoryChildTreeList->getChildrenData()) > 0) {
                    $this->getCategoryList($categoryChildTreeList->getChildrenData());
                }
            }
        }
    }

        /**
     * For brand category List with IBO category filter
     */
    private function getCategoryBrandList_withFilter($categoryList) {
        if(!empty($categoryList)) {
            
            $category = $this->categoryFactory->create()->getCollection()
                 ->addAttributeTofilter('ibo_brand_id',array((int)$categoryList));

            $brandCatId  = $category->getData()[0]['entity_id'];
            $category = $this->categoryFactory->create()->load($brandCatId);
            
            $this->categoriesSeoData[] = array(
                "id" => (int)$category->getId(),
                "parent_id" => $category->getParentId(),
                "brand_id" => $category->getData('ibo_brand_id'),
                "name" => html_entity_decode($category->getName()),
                "url" => html_entity_decode(strtolower($category->getName()) . "/b/" . $category->getData('ibo_brand_id')),
                "meta_title" => html_entity_decode($category->getMetaTitle()),
                "meta_description" => html_entity_decode($category->getMetaDescription()),
                "meta_keywords" => html_entity_decode($category->getMetaKeywords())
                
            );
            
        }
    }

    private function getChildCategories($parent) {
        return $this->categoryManagement->getTree($parent);
    }

     /**
     * GET for Post api
     * @api
     * 
     * @return array
     */
    public function update() {
        $this->addLog("==========================================");
        $this->addLog("Start navigation categories meta-details update process");
        $error = "";
        try {
            $params = $this->helper->getParams();
            if(isset($params['categories']) && !empty($params['categories'])) {
                $categoriesToBeUpdated = $params['categories'];
                foreach ($categoriesToBeUpdated as $category) {
                    if(isset($category['id']) && !empty($category['id'])) {
                        $this->addLog("Start meta-details updation for category ID: " . $category['id']);
                        $categoryData = $this->categoryFactory->create()->setStoreId(0)->load($category['id']);
                        if(!empty($categoryData->getId())) {
                            if(isset($category['meta_title']) && !empty($category['meta_title'])) {
                                $categoryData->setMetaTitle($category['meta_title']);
                                $this->addLog("Meta Title: " . $category['meta_title']);
                            }
                            if(isset($category['meta_description']) && !empty($category['meta_description'])) {
                                $categoryData->setMetaDescription($category['meta_description']);
                                $this->addLog("Meta Description: " . $category['meta_description']);
                            }
                            if(isset($category['meta_keywords']) && !empty($category['meta_keywords'])) {
                                $categoryData->setMetaKeywords($category['meta_keywords']);
                                $this->addLog("Meta Keywords: " . $category['meta_keywords']);
                            }
                            $categoryData->save();
                            $this->addLog("End meta-details updation for category ID: " . $category['id']);
                            $this->addLog("Start meta-details synching to catalog for category ID: " . $category['id']);
                            $this->eventManager->dispatch('catalog_category_meta_save_after',
                                [
                                    'category' => new DataObject(['id' => $category['id']])
                                ]
                            );
                            $this->addLog("End meta-details synching to catalog for category ID: " . $category['id']);
                        } else {
                            $this->addLog("Category does not exist with the category-ID: " . $category['id']);
                        }
                    } else {
                        $this->addLog("Category-ID is not present in payload", "");
                    }
                }
            } else {
                $error = "payload is empty or incorrect";
                $this->addLog($error);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->addLog($error);
        }
        $this->addLog("End navigation categories meta-details update process");
        $this->addLog("==========================================");
        $data = array("error" => !empty($error) ? true : false, "message" => !empty($error) ? $error : "Categories SEO details are updated Successfully.");
        return [$data];
    }

    /**
     * update data Brand Root 
     * 
     * @return array
     * 
     */
    public function updateBrand(){
        $this->addLog("==========================================");
        $this->addLog("Start Brand categories meta-details update process");
        $error = "";
        try {
            $params = $this->helper->getParams();
            if(isset($params['categories']) && !empty($params['categories'])) {
                $categoriesToBeUpdated = $params['categories'];
                foreach ($categoriesToBeUpdated as $category) {
                    if(isset($category['id']) && !empty($category['id'])) {
                        $this->addLog("Start meta-details updation for category ID: " . $category['id']);
                        $categoryData = $this->categoryFactory->create()->setStoreId(0)->load($category['id']);
                        if(!empty($categoryData->getId())) {
                            if(isset($category['meta_title']) && !empty($category['meta_title'])) {
                                $categoryData->setMetaTitle($category['meta_title']);
                                $this->addLog("Meta Title: " . $category['meta_title']);
                            }
                            if(isset($category['meta_description']) && !empty($category['meta_description'])) {
                                $categoryData->setMetaDescription($category['meta_description']);
                                $this->addLog("Meta Description: " . $category['meta_description']);
                            }
                            if(isset($category['meta_keywords']) && !empty($category['meta_keywords'])) {
                                $categoryData->setMetaKeywords($category['meta_keywords']);
                                $this->addLog("Meta Keywords: " . $category['meta_keywords']);
                            }
                            $categoryData->save();
                            $this->addLog("End meta-details updation for category ID: " . $category['id']);
                            $this->addLog("Start meta-details synching to catalog Brand for category ID: " . $category['id']);
                            //Call sync function IBO brand category ids replaces Event
                            $this->brandCatalogServicePush->brandPush($categoryData->getData());
                           /* $this->eventManager->dispatch('catalog_category_meta_save_after',
                                [
                                    'category' => new DataObject(['id' => $category['id']])
                                ]
                            ); */
                            $this->addLog("End meta-details synching to catalog Brand for category ID: " . $category['id']);
                        } else {
                            $this->addLog("Category does not exist with the category-ID: " . $category['id']);
                        }
                    } else {
                        $this->addLog("Ibo brand Category-ID is not present in payload", "");
                    }
                }
            } else {
                $error = "payload is empty or incorrect";
                $this->addLog($error);
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->addLog($error);
        }
        $this->addLog("End Brand categories meta-details update process");
        $this->addLog("==========================================");
        $data = array("error" => !empty($error) ? true : false, "message" => !empty($error) ? $error : "Categories SEO details are updated Successfully.");
        return [$data]; 
    }

    public function addLog($logData) {
        $fileName = "navigationCategoryMetaUpdate.log";
        if ($this->canWriteLog($fileName)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename) {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $logEnable;
    }

}
