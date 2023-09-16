<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products\Query\ProductQueryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\Customer\Model\Visitor;
use Magento\Customer\Model\Group;

/**
 * Products field resolver, used for GraphQL request processing.
 */
class Products implements ResolverInterface
{
    /**
     * @var ProductQueryInterface
     */
    private $searchQuery;
    private $bestdealProducts;
    private $bannerProducts;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchApiCriteriaBuilder;

    protected $_customerGroupCollection;

    /**
     * @param ProductQueryInterface $searchQuery
     * @param SearchCriteriaBuilder|null $searchApiCriteriaBuilder
     */
    public function __construct(
        ProductQueryInterface $searchQuery,
        Group $customerGroupCollection,
        SearchCriteriaBuilder $searchApiCriteriaBuilder = null,
        DataProvider\RecentProducts $recentlyViewed,
        DataProvider\BestdealProducts $bestdealProducts,
        DataProvider\ProductsByDate $productsByDate,
        DataProvider\BannerProducts $bannerProducts,
        Visitor $customerVisitor,
        \Magento\Framework\Session\SessionManagerInterface $session
    ) {
        $this->searchQuery = $searchQuery;
        $this->searchApiCriteriaBuilder = $searchApiCriteriaBuilder ??
            \Magento\Framework\App\ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
        $this->recentlyViewed = $recentlyViewed;
        $this->bestdealProducts = $bestdealProducts;
        $this->productsByDate = $productsByDate;
        $this->bannerProducts = $bannerProducts;
        $this->_customerVisitor = $customerVisitor;
        $this->session = $session;
        $this->productsByDate = $productsByDate;
        $this->_customerGroupCollection = $customerGroupCollection;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $this->validateInput($args);
        
        $customerId = null;
        $currentGroupId = $context->getExtensionAttributes()->getCustomerGroupId();
        $groupType = $this->getCustomerGroup($currentGroupId);
        $allowedChannels = !empty($args['allowed_channels']) ? $args['allowed_channels'] : null;
        $isPublished = !empty($args['is_published']) ? $args['is_published'] : null;
        $serviceCategory = !empty($args['service_category']) ? $args['service_category'] : null;

        if ($context->getExtensionAttributes()->getIsCustomer()) {
            $customerId = $context->getUserId();
        }

        if ($args['type'] == 'best_deal') {
           $skus = $this->bestdealProducts->getBestDealSkus($groupType);
           if(empty($skus)) {
                return;
           }
           $args['filter']['sku']['in'] = $skus;
        }

        if ($args['type'] == 'banner' && isset($args['id'])) {           
           $skus = $this->bannerProducts->getProductsData($args['id']); 
           if(empty($skus)) {
                return;
           }
           $args['filter']['sku']['in'] = $skus;
        }

        if(!is_null($allowedChannels)){
            if(!empty($allowedChannels['in'])){
                $args['filter']['allowed_channels']['in'] = $allowedChannels['in'];
            }else if($allowedChannels['eq']){
                $args['filter']['allowed_channels']['eq'] = $allowedChannels['eq'];
            }
        }

        if(!is_null($isPublished)){
            $args['filter']['is_published']['eq'] = $isPublished['eq'];
        }

        if(!is_null($serviceCategory)){
            if(!empty($serviceCategory['in'])){
                $args['filter']['service_category']['in'] = $serviceCategory['in'];
            }else if($serviceCategory['eq']){
                $args['filter']['service_category']['eq'] = $serviceCategory['eq'];
            }
        }

        $searchResult = $this->searchQuery->getResult($args, $info, $context);
        if ($args['type'] == 'products_by_date') {
            $searchResult = $this->productsByDate->getProductsData($args);
            if(empty($searchResult)) {
                 return;
            }
        }

        if ($args['type'] == 'recent') {
            $searchResult = $this->recentlyViewed->getRecentlyViewData($customerId,$args); 
            if(empty($searchResult)) {
                 return;
            }
        }
        
        if ($searchResult->getCurrentPage() > $searchResult->getTotalPages() && $searchResult->getTotalCount() > 0) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$searchResult->getCurrentPage(), $searchResult->getTotalPages()]
                )
            );
        }

        $data = [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $searchResult->getProductsSearchResult(),
            'page_info' => [
                'page_size' => $searchResult->getPageSize(),
                'current_page' => $searchResult->getCurrentPage(),
                'total_pages' => $searchResult->getTotalPages()
            ],
            'search_result' => $searchResult
        ];

        return $data;
    }

    /**
     * Validate input arguments
     *
     * @param array $args
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    private function validateInput(array $args)
    {
        if (isset($args['searchAllowed']) && $args['searchAllowed'] === false) {
            throw new GraphQlAuthorizationException(__('Product search has been disabled.'));
        }
        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }
        if (!isset($args['type'])) {
            throw new GraphQlInputException(
                __("'Type' input argument is required.")
            );
        }
        if(isset($args['type'])){
            if ($args['type']=='banner' && !isset($args['id'])) {
                throw new GraphQlInputException(
                    __("'id' input argument is required.")
                );
            } 
        }
    }

    /**
     * Get current customer group name
     */
    public function getCustomerGroup($currentGroupId)
    {
            $collection = $this->_customerGroupCollection->load($currentGroupId);
            return $collection->getCustomerGroupCode(); 
    }
}