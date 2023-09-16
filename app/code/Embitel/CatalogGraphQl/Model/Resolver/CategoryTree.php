<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Model\Category;
use Magento\CatalogGraphQl\Model\Resolver\Category\CheckCategoryIsActive;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree as CategoryTreeDataProvider;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollecionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Category tree field resolver, used for GraphQL request processing.
 */
class CategoryTree implements ResolverInterface
{
    /**
     * Name of type in GraphQL
     */
    const CATEGORY_INTERFACE = 'CategoryInterface';

    /**
     * @var CategoryTreeDataProvider
     */
    private $categoryTree;

    /**
     * @var ExtractDataFromCategoryTree
     */
    private $extractDataFromCategoryTree;

    /**
     * @var CheckCategoryIsActive
     */
    private $checkCategoryIsActive;

    /**
     * @var CategoryCollecionFactory
     */
    private $categoryCollecionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $_storeManager;


    /**
     * @param CategoryTreeDataProvider $categoryTree
     * @param ExtractDataFromCategoryTree $extractDataFromCategoryTree
     * @param CheckCategoryIsActive $checkCategoryIsActive
     * @param CategoryCollecionFactory $categoryCollecionFactory
     */
    public function __construct(
        CategoryTreeDataProvider $categoryTree,
        ExtractDataFromCategoryTree $extractDataFromCategoryTree,
        CheckCategoryIsActive $checkCategoryIsActive,
        CategoryCollecionFactory $categoryCollecionFactory,
        StoreManagerInterface $storeManager
    )
    {
        $this->categoryTree = $categoryTree;
        $this->extractDataFromCategoryTree = $extractDataFromCategoryTree;
        $this->checkCategoryIsActive = $checkCategoryIsActive;
        $this->categoryCollecionFactory = $categoryCollecionFactory;
        $this->_storeManager = $storeManager;
    }


    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (isset($value[$field->getName()])) {
            return $value[$field->getName()];
        }
        
        if(isset($args['category_code']))
        {
            $rootId = $this->_storeManager->getStore()->getRootCategoryId();
            $iboCategoryId = $args['category_code'];

            $categoryId = '';
            $collection = $this->categoryCollecionFactory
                    ->create()
                    ->addFieldToFilter('path', array('like'=> "1/$rootId/%"))
                    ->addAttributeToFilter('category_id',$iboCategoryId)
                    ->setPageSize(1);
            if ($collection->getSize()) { 
                $categoryId = $collection->getFirstItem()->getId();
            }
            $rootCategoryId = (int)$categoryId;

        } else {  
            $rootCategoryId = isset($args['id']) ? (int)$args['id'] :
                (int)$context->getExtensionAttributes()->getStore()->getRootCategoryId();

            if ($rootCategoryId !== Category::TREE_ROOT_ID) {
                $this->checkCategoryIsActive->execute($rootCategoryId);
            }
        }

        $categoriesTree = $this->categoryTree->getTree($info, $rootCategoryId);

        if (empty($categoriesTree) || ($categoriesTree->count() == 0)) {
            throw new GraphQlNoSuchEntityException(__('Category doesn\'t exist'));
        }


        $result = $this->extractDataFromCategoryTree->execute($categoriesTree);
        $sortedResult = $this->array_sort($result);
        return current($sortedResult);
    }

    function array_sort(&$array, $on = "position")
    {

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v["children"])) {
                    $array[$k]["children"] =  $this->sortByPositon($v["children"]);
                    $this->array_sort($array[$k]["children"]);
                }

            }
        }
        return $array;
    }

    /**
     * @param $children
     * @return array
     */
    protected function sortByPositon($children): array
    {
        $sortable_array = array_values($children);
        $position = array_column($sortable_array, 'position');
        array_multisort($position, SORT_ASC, $sortable_array);
        return $sortable_array;
    }

}