<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Catalog\Model;

use Exception;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\FilterBuilder;
use Embitel\Catalog\Model\MerchandisingCategoryManagement;

/**
 * Handles the category tree.
 */
class CategoryManagement implements \Embitel\Catalog\Api\CategoryManagementInterface
{
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var \Magento\Catalog\Model\Category\Tree
     */
    protected $categoryTree;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoriesFactory;
    private $categoryFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
 
    /**
     * @var CategoryListInterface
     */
    private $categoryList;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @param \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository
     * @param Category\Tree $categoryTree
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoriesFactory
     */
    public function __construct(
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\Category\Tree $categoryTree,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoriesFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory, 
        \Magento\Framework\Webapi\Rest\Request $request,
        MerchandisingCategoryManagement $MerchandisingCategoryManagement,
        FilterGroupBuilder $filterGroupBuilder,
        FilterBuilder $filterBuilder,
        CategoryListInterface $categoryList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryTree = $categoryTree;
        $this->categoriesFactory = $categoriesFactory;
        $this->categoryFactory = $categoryFactory;
        $this->categoryList = $categoryList;
        $this->_merchandisingCategoryManagement = $MerchandisingCategoryManagement;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder; 
        $this->_storeManager = $storeManager;
        $this->request = $request;

    }

    /**
     * Fetch all Category list
     *
     * @return CategorySearchResultsInterface
     */
    public function getAllSystemCategory()
    {
        //$this->filterBuilder->create(); 
        
        $filter_last_updated = $this->request->getParam('filter_last_updated');
        $filter_date_from = $this->request->getParam('filter_date_from');
        $filter_date_to = $this->request->getParam('filter_date_to');

        $collection = $this->categoryFactory->create()->getCollection()
                ->addFieldToFilter('name','Merchandising Category');
       
        if ($collection->getSize()) {
            $categoryId = $collection->getFirstItem()->getId();           
        }

        $catcollection = $this->categoryFactory->create()->load($categoryId);

        $childActiveCategory = $catcollection->getAllChildren(true);

        $filter2 = $this->filterBuilder
            ->setField("entity_id")
            ->setValue($childActiveCategory)
            ->setConditionType("in")->create();
        $filterGroup2 = $this->filterGroupBuilder
            ->addFilter($filter2)
            ->create(); 
        $finalFilterList[] = $filterGroup2; 

        if($filter_last_updated == 1){ 
            //To filter categories updated last 1hr
            $updated_at = date('Y-m-d h:i:s', strtotime('-1 day')); 
            $filter3 = $this->filterBuilder
                ->setField("updated_at")
                ->setValue($updated_at)
                ->setConditionType("gt")->create(); 
            $filterGroup3 = $this->filterGroupBuilder
                ->addFilter($filter3)
                ->create(); 
            $finalFilterList[] = $filterGroup3; 
        }
        if($filter_date_from && $filter_date_to){ 
            //Filter categories by given date
            $filter_date_from = date("Y-m-d 00:00:00",strtotime($filter_date_from)); 
            $filter_date_to = date("Y-m-d 11:59:59",strtotime($filter_date_to));
            
            $filter_from = $this->filterBuilder
                ->setField("updated_at")
                ->setValue($filter_date_from)
                ->setConditionType("gt")->create();      

            $filter_to = $this->filterBuilder
                ->setField("updated_at")
                ->setValue($filter_date_to)
                ->setConditionType("lt")->create(); 

            $filterGroup4 = $this->filterGroupBuilder
                ->addFilter($filter_from)->create();
            $filterGroup5 = $this->filterGroupBuilder
                ->addFilter($filter_to)->create();

            $finalFilterList[] = $filterGroup4;                 
            $finalFilterList[] = $filterGroup5;                 

        }
       /* $filter3 = $this->filterBuilder
            ->setField("is_active")
            ->setValue("1")
            ->setConditionType("eq")->create();
        $filterGroup3 = $this->filterGroupBuilder
            ->addFilter($filter3)
            ->create();*/
        /*$filter4 = $this->filterBuilder
            ->setField("name")
            ->setValue(["%Navigation Category%"])
            ->setConditionType("like")->create();
        $filterGroup4 = $this->filterGroupBuilder
            ->addFilter($filter4)
            ->create();*/
            
        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups($finalFilterList)
            ->create();

        $categoryList = [];
        try {
           // $searchCriteria = $this->searchCriteriaBuilder->create();
            
            $categoryList = $this->categoryList->getList($searchCriteria);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
 
        return $categoryList;
    }   

    /**
     * @param $rootCategoryId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTree($rootCategoryId = null, $depth = null)
    {   
        $categoryData = [];
        $categoryList = $this->getAllSystemCategory();
        if ($categoryList->getTotalCount()) {
            foreach ($categoryList->getItems() as $category){
                $catImgUrl = ($category->getImageUrl())?$this->_storeManager->getStore()->getBaseUrl().$category->getImageUrl():"";
                $categoryData[$category->getId()] = [            
                    'id' => $category->getCategoryId(),
                    'parent_id'=> $category->getParentCategoryId(),
                    'name' => $category->getName(),
                    'position' => $category->getPosition(),
                    'level' => $category->getLevel(),
                    'is_active' => $category->getIsActive(),
                    'least_child' => ($category->getChildrenCount() == 0) ? true : false,
                    'category_id' => $category->getCategoryId(),
                    'hierarchy_type' => $category->getHierarchyType(), 
                    'category_type' => $category->getCategoryType(), 
                    'parent_category_id' => $category->getParentCategoryId(), 
                    'is_partial_fulfilment_allowed' => null, 
                    'allowed_hsn' => null, 
                    'keywords' => $category->getMetaKeywords(), 
                    'meta_title' => $category->getMetaTitle(),
                    'meta_description' => $category->getMetaDescription(), 
                    'categoryImageURL' => str_replace("//media", "/media", $catImgUrl),
                    'variant_attribute' => $category->getVariantAttribute(),
                    'title_name_rule' => $category->getTitleNameRule(),

                    
                ];
            }
        }
        return $categoryData;        
    } 

    /**
     * @inheritdoc
     */
    public function getCount()
    {
        $categories = $this->categoriesFactory->create();
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categories */
        $categories->addAttributeToFilter('parent_id', ['gt' => 0]);
        return $categories->getSize();
    }

    /**
     * @get categories postman data
     */
    public function getMerchandiseCategoriesIds($ibo_category_ids){
      
        try{

            if(count($ibo_category_ids) > 0){

                $collection = $this->categoriesFactory->create()
                    ->addAttributeToFilter('category_id',array($ibo_category_ids))
                    ->addAttributeToFilter('category_type','MERCHANDISING');

                foreach($collection->getData() as $iboId){
                    $this->_merchandisingCategoryManagement->syncMerchandisingCat($iboId['entity_id']);
                }
            }else{
                throw new Exception("Categories Array should not be blank");
            }

        }catch(Exception $e) {
            echo "There is some error: " . $e->getMessage();
        }
    }
}