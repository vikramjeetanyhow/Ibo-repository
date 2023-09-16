<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductFilter\Model\Resolver;

use Magento\CatalogGraphQl\Model\Resolver\Products\Query\ProductQueryInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Config;
use Magento\CatalogSearch\Helper\Data as SearchHelper;
use Embitel\ProductFilter\Model\QueryFactory;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

/**
 * Products field resolver, used for GraphQL request processing.
 */
class Products extends \Magento\CatalogGraphQl\Model\Resolver\Products
{
    /**
     * @var ProductQueryInterface
     */
    private $searchQuery;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchApiCriteriaBuilder;
    /**
     * @var Registry
     */

    protected $_registry;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var Config
     */
    private $catalogConfig;

    /**
     * @var SearchHelper
     */
    private $searchHelper;

    /**
     * @var QueryFactory
     */
    private $queryFactory;

    /** @var Uid */
    private $uidEncoder;

    /**
     *
     * @param ProductQueryInterface $searchQuery
     * @param Registry $registry
     * @param CategoryRepository $categoryRepository
     * @param Config $catalogConfig
     * @param SearchHelper $searchHelper
     * @param QueryFactory $queryFactory
     * @param Uid $uidEncoder
     * @param SearchCriteriaBuilder $searchApiCriteriaBuilder
     */
    public function __construct(
        ProductQueryInterface $searchQuery,
        Registry $registry,
        CategoryRepository $categoryRepository,
        Config $catalogConfig,
        SearchHelper $searchHelper,
        QueryFactory $queryFactory,
        Uid $uidEncoder,
        HttpContext $httpContext,
        CollectionFactory $collectionFactory,
        SearchCriteriaBuilder $searchApiCriteriaBuilder = null
    ) {
        $this->searchQuery = $searchQuery;
        $this->searchApiCriteriaBuilder = $searchApiCriteriaBuilder ??
            \Magento\Framework\App\ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
        $this->_registry = $registry;
        $this->categoryRepository = $categoryRepository;
        $this->catalogConfig = $catalogConfig;
        $this->searchHelper = $searchHelper;
        $this->queryFactory = $queryFactory;
        $this->uidEncoder = $uidEncoder;
        $this->httpContext = $httpContext;
        $this->groupCollection = $collectionFactory;
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
        $storeId = $context->getExtensionAttributes()->getStore()->getStoreId();
        if(isset($args['search'])){
            $this->_registry->register('filtered_search', $args['search']);
        }else{
            $this->_registry->register('filtered_search', []);
        }
        /* If customer group id pass in pdp request body */
        if(isset($args['customer_group_id']) && !empty($args['customer_group_id'])) {
            $iboCustomerGroupId = $this->getCustomerGroupId($args['customer_group_id']);
            if(!empty($iboCustomerGroupId)) {
                $this->updateContext($iboCustomerGroupId);
            }
        } else {
            $iboCustomerGroupId = $this->getCustomerGroupId("B2C");
            $this->updateContext($iboCustomerGroupId);
        }
        if ((!isset($args['sort']) || empty($args['sort']))
                && (!isset($args['filter']['sku']) && !isset($args['search']))
            ) {
            $defaultSort = $this->catalogConfig->getProductListDefaultSortBy($storeId);
            $args['sort'][$defaultSort] = 'ASC';
        }
        //for duplicate product issue on plp and search
        /* 2nd condition is for do not sort by entity id if filter is for sku */
        // if (!isset($args['search']) && !isset($args['filter']['sku'])) {
        //     $args['sort']['entity_id'] = 'ASC';
        // }

        /* start add custom code to return selected attribute in response */
        if (isset($args['filter'])) {
            $this->_registry->register('filtered_attributes', $args['filter']);
            if ((isset($args['filter']['sku']['in']))
                    && (!isset($args['sort']) || empty($args['sort']))
             ) {
                $this->_registry->register('filtered_skus', $args['filter']['sku']['in']);
            }
        }

        //hide products if category display is static block only
        if (isset($args['filter']['category_uid']['eq'])) {
                $catId = $this->uidEncoder->decode((string) $args['filter']['category_uid']['eq']);
                $category = $this->categoryRepository->get($catId);
                $this->_registry->register('hide_product', false);
                /* this code is supposed to work for display mode in category */
            // if ($category->getDisplayMode() == Category::DM_PAGE) {
            //     $this->_registry->register('hide_product', true);
            // }
        }
        /* unsetting the parent category filter to filter only child category product */
        if (isset($args['filter']['category_id']['in'])) {
                if(count($args['filter']['category_id']['in']) > 0 && !isset($args['search'])) {
                    unset($args['filter']['category_id']['in'][0]);
                }
        }
        //end

        $searchResult = $this->searchQuery->getResult($args, $info, $context);

        if ($searchResult->getCurrentPage() > $searchResult->getTotalPages() && $searchResult->getTotalCount() > 0) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$searchResult->getCurrentPage(), $searchResult->getTotalPages()]
                )
            );
        }

        /* start add custom code to return selected sort in response */
        if (isset($args['sort'])) {
            $this->_registry->register('current_sort', $args['sort']);
        }

        $data = [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $searchResult->getProductsSearchResult(),
            'page_info' => [
                'page_size' => $searchResult->getPageSize(),
                'current_page' => $searchResult->getCurrentPage(),
                'total_pages' => $searchResult->getTotalPages()
            ],
            'search_result' => $searchResult,
            'layer_type' => isset($args['search']) ? Resolver::CATALOG_LAYER_SEARCH : Resolver::CATALOG_LAYER_CATEGORY
        ];

        if (isset($args['filter']['category_id'])) {
            if (empty($args['filter']['category_id'])) {
                $data['categories'] = [];
            } else {
                $data['categories'] = $args['filter']['category_id']['eq'] ?? $args['filter']['category_id']['in'];
                $data['categories'] = is_array($data['categories']) ? $data['categories'] : [$data['categories']];
            }
            $this->_registry->register('filtered_category_ids', $data['categories']);
        }

        return $data;
    }

    /**
     * Add search terms.
     *
     * @param type $text
     * @param type $count
     */
    protected function addSearchTearm($text, $count, $storeId)
    {
        try {
            $query = $this->queryFactory->get($text);
            $query->setStoreId($storeId);
            $query->saveNumResults($count);
            $query->saveIncrementalPopularity();
        } catch (Exception $ex) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/searchtearm.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info($ex->getMessage());
        }
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
        if (!isset($args['search']) && !isset($args['filter'])) {
            throw new GraphQlInputException(
                __("'search' or 'filter' input argument is required.")
            );
        }
    }

    private function updateContext(string $iboCustomerGroupId) : void
    {
        $this->httpContext->setValue(
            'ibo_customer_group_id',
            $iboCustomerGroupId,
            ""
        );
    }

    public function getCustomerGroupId($groupName){
        $collection = $this->groupCollection->create()
            ->addFieldToSelect('customer_group_id')
            ->addFieldToFilter('customer_group_code', $groupName);
        $collection->getSelect();

        if($collection->getSize() > 0) {
            return $collection->getFirstItem()->getData()['customer_group_id'];
        }
    }
}
