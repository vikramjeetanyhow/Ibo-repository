<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class AllCategories implements \Anyhow\SupermaxPos\Api\Supermax\AllCategoriesInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Block\Adminhtml\Category\Tree $adminCategoryTree,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory
    ){
        $this->helper = $helper;
        $this->adminCategoryTree = $adminCategoryTree;
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
 
    public function getAllCategories()
    {
        $result = array();
        $error = false;
        try{
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $categoryTrees = $this->adminCategoryTree->getTree();
                $categoryTreeData = array();
                $categoryTreeResult = array();
                $categoryProductsData = array();

                if(!empty($categoryTrees)) {
                    foreach($categoryTrees as $categoryTree) {
                        $categoryTData = $this->categoryFactory->create()->load($categoryTree['id']);
                        $categoryTreeData[] = array(
                            'category_name' => html_entity_decode($categoryTData->getName()),
                            'category_id' => (int)$categoryTree['id'],
                            'children' => $this->getChildrenCategories($categoryTree['children'])
                        );
                    }
                }

                $categoryCollection = $this->categoryFactory->create()->setStoreId(1)->getCollection();
                $categories = $categoryCollection->getData();

                if(!empty($categories)) {
                    foreach($categories as $category) {
                        $categoryData = $this->categoryFactory->create()->load($category['entity_id']);
                        $categoryProducts = $categoryData->getProductCollection()->getData();

                        if(!empty($categoryProducts)) {
                            $productData = array();
                            foreach($categoryProducts as $categoryProduct) {
                                $productData[] =  (int)$categoryProduct['entity_id'];
                            }
                        } else {
                            $productData = [];
                        }
                        
                        $categoryProductsData[(int)$category['entity_id']] = $productData;
                    }
                }
                if(count($categoryTreeData[0]['children']) !=0){
                    $categoryTreeResult = $categoryTreeData[0]['children'];
                }

                $result = array(
                    'categories' => $categoryTreeResult,
                    'products' => $categoryProductsData
                );

            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    public function getChildrenCategories($children)
    {
        $childData = array();
        
        if(!empty($children)) {
            foreach($children as $child) {
                $categoryTData = $this->categoryFactory->create()->load($child['id']);
                $childData[] = array(
                    'category_name' => html_entity_decode($categoryTData->getName()),
                    'category_id' => (int)$child['id'],
                    'children' =>(!empty($child['children']) ? $this->getChildrenCategories($child['children']) : [])
                );
            }
        } 

        return $childData;
    }
}