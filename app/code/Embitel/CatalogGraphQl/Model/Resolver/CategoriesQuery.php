<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Category\CategoryFilter;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\CategoryTree;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ExtractDataFromCategoryTree;
use Magento\Framework\Exception\InputException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Embitel\CatalogGraphQl\Model\Resolver\DataProvider\HomePage as HomePageDataProvider;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
/**
 * Categories resolver, used for GraphQL category data request processing.
 */
class CategoriesQuery implements ResolverInterface
{
    /**
     * @var CategoryTree
     */
    private $categoryTree;

    /**
     * @var CategoryFilter
     */
    private $categoryFilter;

    /**
     * @var ExtractDataFromCategoryTree
     */
    private $extractDataFromCategoryTree;

    /**
     * @var ArgumentsProcessorInterface
     */
    private $argsSelection;

    private $categoryCollection;

    private $homePageDataProvider;

    protected $_customerGroupCollection;

    /**
     * @param CategoryTree $categoryTree
     * @param ExtractDataFromCategoryTree $extractDataFromCategoryTree
     * @param CategoryFilter $categoryFilter
     * @param ArgumentsProcessorInterface $argsSelection
     */
    public function __construct(
        Collection $categoryCollection,
        Group $customerGroupCollection,
        CategoryTree $categoryTree,
        ExtractDataFromCategoryTree $extractDataFromCategoryTree,
        CategoryFilter $categoryFilter,
        HomePageDataProvider $homePageDataProvider,
        ArgumentsProcessorInterface $argsSelection,
        CollectionFactory $collectionFactory
    ) {
        $this->categoryTree = $categoryTree;
        $this->extractDataFromCategoryTree = $extractDataFromCategoryTree;
        $this->categoryFilter = $categoryFilter;
        $this->argsSelection = $argsSelection;
        $this->categoryCollection = $categoryCollection;
        $this->homePageDataProvider = $homePageDataProvider;
        $this->_customerGroupCollection = $customerGroupCollection;
        $this->groupCollection = $collectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $store = $context->getExtensionAttributes()->getStore();
        $argCustomerGroup = !empty($args['customer_group_id']) ? $args['customer_group_id'] : null;
        if(empty($argCustomerGroup)) {
            $currentGroupId = $context->getExtensionAttributes()->getCustomerGroupId();
            $groupType = $this->getCustomerGroup($currentGroupId);
        } else {
            if(!$this->validateCustomerGroup($argCustomerGroup)){
                throw new GraphQlInputException(__('Please enter valid customer group.'));
            }
            $groupType = $argCustomerGroup;
        }

        $displayZone = !empty($args['display_zone']) ? $args['display_zone'] : null;

        if (isset($args['currentPage']) && $args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if (isset($args['pageSize']) && $args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }
        if (!isset($args['type'])) {
            throw new GraphQlInputException(__('"Type" value must be specified'));
        }
        $catIds = [];
        if ($args['type']=='top_categories') {
            $catIds = $this->homePageDataProvider->getHomePageData($args['type'], $groupType, $displayZone);
        }
        if ($args['type'] == 'top_brands') {
            $catIds = $this->homePageDataProvider->getHomePageData($args['type'], $groupType, $displayZone);
        }
        if(empty($catIds)){
            return;
        }
        $args['filters']['category_uid'] = ['in' => $catIds];
        try {
            $processedArgs = $this->argsSelection->process($info->fieldName, $args);
            $filterResult = $this->categoryFilter->getResult($processedArgs, $store, [], $context);
        } catch (InputException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        $rootCategoryIds = $filterResult['category_ids'];
        $filterResult['items'] = $this->fetchCategories($rootCategoryIds, $info);
        return $filterResult;
    }

    /**
     * Fetch category tree data
     *
     * @param array $categoryIds
     * @param ResolveInfo $info
     * @return array
     */
    private function fetchCategories(array $categoryIds, ResolveInfo $info)
    {
        $fetchedCategories = [];
        foreach ($categoryIds as $categoryId) {
            $categoryTree = $this->categoryTree->getTree($info, $categoryId);
            if (empty($categoryTree)) {
                continue;
            }
            $fetchedCategories[] = current($this->extractDataFromCategoryTree->execute($categoryTree));
        }

        return $fetchedCategories;
    }

    /**
     * Get current customer group name
     */
    public function getCustomerGroup($currentGroupId)
    {
            $collection = $this->_customerGroupCollection->load($currentGroupId);
            return $collection->getCustomerGroupCode();
    }

    /**
     * @desc validate customer group by name
     * @param $groupName
     * @return bool
     */
    public function validateCustomerGroup($groupName){
        $collection = $this->groupCollection->create()
            ->addFieldToSelect('customer_group_id')
            ->addFieldToFilter('customer_group_code', $groupName);
        $collection->getSelect();

        if($collection->getSize() > 0){
            return true;
        }

        return false;
    }
}
