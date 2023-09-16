<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductFilter\DataProvider\Product\LayeredNavigation\Builder;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\AggregationValueInterface;
use Magento\Framework\Api\Search\BucketInterface;

class Category extends \Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Builder\Category
{
    /**
     * @var string
     */
    private const CATEGORY_BUCKET = 'category_bucket';

    /**
     * @var array
     */
    private static $bucketMap = [
        self::CATEGORY_BUCKET => [
            'request_name' => 'category_id',
            'label' => 'Category'
        ],
    ];

    /**
     * @var CategoryAttributeQuery
     */
    private $categoryAttributeQuery;

    /**
     * @var CategoryAttributesMapper
     */
    private $attributesMapper;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var RootCategoryProvider
     */
    private $rootCategoryProvider;

    /**
     * @var LayerFormatter
     */
    private $layerFormatter;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /** @var Uid */
    private $uidEncoder;

    public function __construct(
        \Magento\CatalogGraphQl\DataProvider\Category\Query\CategoryAttributeQuery $categoryAttributeQuery,
        \Magento\CatalogGraphQl\DataProvider\CategoryAttributesMapper $attributesMapper,
        \Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\RootCategoryProvider $rootCategoryProvider,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\Formatter\LayerFormatter $layerFormatter,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Framework\GraphQl\Query\Uid $uidEncoder
    ) {
        $this->categoryAttributeQuery = $categoryAttributeQuery;
        $this->attributesMapper = $attributesMapper;
        $this->resourceConnection = $resourceConnection;
        $this->rootCategoryProvider = $rootCategoryProvider;
        $this->layerFormatter = $layerFormatter;
        $this->registry = $registry;
        $this->categoryRepository = $categoryRepository;
        $this->uidEncoder = $uidEncoder;
        parent::__construct($categoryAttributeQuery, $attributesMapper, $rootCategoryProvider, $resourceConnection, $layerFormatter);
    }

    /**
     * @inheritdoc
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        //Start custom code to get category id and sub category id in products graphql query
        $filteredAttrs = $this->registry->registry('filtered_attributes');
        $filteredAttributes = $filteredAttrs ? $filteredAttrs : [];
        
        if(in_array('category_id',array_keys($filteredAttributes) )) {
            $inoreq = array_keys($filteredAttributes['category_id']);
        }
        $params = array_keys($filteredAttributes);
        
        if (in_array('category_uid', $params) && !empty($filteredAttributes['category_uid'])) {
            if ($inoreq[0] == 'in') {
                $inCategory = $filteredAttributes['category_uid']['in'];
                foreach ($inCategory as $incat) {
                    $cat_id[] = $this->uidEncoder->decode((string) $incat);
                }
            } else {
                $attOptId = $filteredAttributes['category_uid']['eq'];
                $cat_id = $this->uidEncoder->decode((string) $attOptId);
            }
            
        } elseif (in_array('category_id', $params) && !empty($filteredAttributes['category_id'])) {
            if ($inoreq[0] == 'in') {
                $cat_id = $filteredAttributes['category_id']['in'];
            } else {
                $cat_id = $filteredAttributes['category_id']['eq'];
            }
        } else {
            $cat_id = '';
        }
        if (is_array($cat_id)) {
            $cat_Arr = [];
            foreach($cat_id as $cat) {
                $category = $this->categoryRepository->get($cat);
                $subCategories = $category->getChildrenCategories();
                $cat_Arr[] = $cat;
                if ($subCategories) {
                    foreach ($subCategories as $subCategory) {
                        $cat_Arr[] = $subCategory->getId();
                        $subcat = $this->categoryRepository->get($subCategory->getId());
                        $subChilds = $subcat->getChildrenCategories();
                        if($subChilds) {
                            foreach ($subChilds as $subchild) {
                                $cat_Arr[] = $subchild->getId();
                            }
                        }
                    }
                }
            }
        } else {
            if ($cat_id != '') {
                $category = $this->categoryRepository->get($cat_id);
                $subCategories = $category->getChildrenCategories();
                $cat_Arr = [];
                $cat_Arr[] = $cat_id;
                if ($subCategories) {
                    foreach ($subCategories as $subCategory) {
                        $cat_Arr[] = $subCategory->getId();
                        $subcat = $this->categoryRepository->get($subCategory->getId());
                        $subChilds = $subcat->getChildrenCategories();
                        if($subChilds) {
                            foreach ($subChilds as $subchild) {
                                $cat_Arr[] = $subchild->getId();
                            }
                        }
                    }
                }
            }
        }
        //end

        $bucket = $aggregation->getBucket(self::CATEGORY_BUCKET);
        if ($this->isBucketEmpty($bucket)) {
            return [];
        }
        $categoryIds = \array_map(
            function (AggregationValueInterface $value) {
                return (int)$value->getValue();
            },
            $bucket->getValues()
        );
        //Start custom code to get category id and sub category id in products graphql query
        if (!empty($cat_Arr)) {
            $categoryIds = array_intersect($categoryIds, $cat_Arr);
        }
        //end

        $categoryIds = \array_diff($categoryIds, [$this->rootCategoryProvider->getRootCategory($storeId)]);
        $categoryLabels = \array_column(
            $this->attributesMapper->getAttributesValues(
                $this->resourceConnection->getConnection()->fetchAll(
                    /* adding level condition to filter only l3 data - adding 4 as level 1 is root category */
                    $this->categoryAttributeQuery->getQuery($categoryIds, ['name'], $storeId).' AND e.level = 4'
                )
            ),
            'name',
            'entity_id'
        );

        if (!$categoryLabels) {
            return [];
        }

        $result = $this->layerFormatter->buildLayer(
            self::$bucketMap[self::CATEGORY_BUCKET]['label'],
            \count($categoryIds),
            self::$bucketMap[self::CATEGORY_BUCKET]['request_name']
        );

        foreach ($bucket->getValues() as $value) {
            $categoryId = $value->getValue();
            if (!\in_array($categoryId, $categoryIds, true)) {
                continue ;
            }
            /* showing only l3 level category if not then skip */
            if (!isset($categoryLabels[$categoryId])){
                continue;
            }
            $result['options'][] = $this->layerFormatter->buildItem(
                $categoryLabels[$categoryId] ?? $categoryId,
                $categoryId,
                $value->getMetrics()['count']
            );
        }

        return [$result];
    }

    /**
     * Check that bucket contains data
     *
     * @param BucketInterface|null $bucket
     * @return bool
     */
    private function isBucketEmpty(?BucketInterface $bucket): bool
    {
        return null === $bucket || !$bucket->getValues();
    }
}
