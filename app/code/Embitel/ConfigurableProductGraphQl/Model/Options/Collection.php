<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ConfigurableProductGraphQl\Model\Options;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Attribute\Collection
    as AttributeCollection;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Attribute\CollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\ConfigurableProduct\Helper\Data;
use Magento\ConfigurableProductGraphQl\Model\Options\Collection as CoreOptionCollection;
use Magento\ConfigurableProductGraphQl\Model\Options\DataProvider\Variant;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Collection for fetching options for all configurable options pulled back in result set.
 */
class Collection extends CoreOptionCollection
{
    /**
     * Option type name
     */
    private const OPTION_TYPE = 'configurable';

    /**
     * @var CollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var int[]
     */
    private $productIds = [];

    /**
     * @var array
     */
    private $attributeMap = [];

    /** @var Uid */
    private $uidEncoder;

    /**
     * @param CollectionFactory $attributeCollectionFactory
     * @param ProductFactory $productFactory
     * @param MetadataPool $metadataPool
     * @param Data $configurableProductHelper
     * @param Uid|null $uidEncoder
     */
    public function __construct(
        CollectionFactory $attributeCollectionFactory,
        ProductFactory $productFactory,
        MetadataPool $metadataPool,
        Data $configurableProductHelper,
        Variant $variant,
        ProductRepositoryInterface $productRepository,
        Uid $uidEncoder = null        
    ) {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->productFactory = $productFactory;
        $this->metadataPool = $metadataPool;
        $this->configurableProductHelper = $configurableProductHelper;
        $this->variant = $variant;
        $this->productRepository = $productRepository;
        $this->uidEncoder = $uidEncoder ?: ObjectManager::getInstance()
            ->get(Uid::class);
    }

    /**
     * Add product id to attribute collection filter.
     *
     * @param int $productId
     */
    public function addProductId(int $productId): void
    {
        if (!in_array($productId, $this->productIds)) {
            $this->productIds[] = $productId;
        }
    }

    /**
     * Retrieve attributes for given product id or empty array
     *
     * @param int $productId
     * @return array
     */
    public function getAttributesByProductId(int $productId): array
    {
        $attributes = $this->fetch();

        if (!isset($attributes[$productId])) {
            return [];
        }

        return $attributes[$productId];
    }

    /**
     * Fetch attribute data
     *
     * @return array
     */
    private function fetch(): array
    {
        if (empty($this->productIds) || !empty($this->attributeMap)) {
            return $this->attributeMap;
        }

        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        /** @var AttributeCollection $attributeCollection */
        $attributeCollection = $this->attributeCollectionFactory->create();
        /** custom code -Empty array declared **/
        $options = [];
        $filteredAttributes = [];
        /** custom code - ends here **/
        foreach ($this->productIds as $id) {
            /** @var Product $product */
            $product = $this->productFactory->create();
            $product->setData($linkField, $id);            
            /**custom code to filter the available options -mohit **/
            $productRepo = $this->productRepository->getById($id);
            $options = $this->configurableProductHelper->getOptions($productRepo, $this->getAllowProducts($productRepo));
            unset($options['index']);
            foreach ($options as $optionKey => $option) {
                foreach ($option as $key => $value) {
                    $filteredAttributes[$optionKey][] = $key;
                }
            }
            /** custom code ends here **/
            $attributeCollection->setProductFilter($product);
        }

        /** @var Attribute $attribute */
        foreach ($attributeCollection->getItems() as $attribute) {
            $productId = (int)$attribute->getProductId();
            if (!isset($this->attributeMap[$productId])) {
                $this->attributeMap[$productId] = [];
            }

            $attributeData = $attribute->getData();
            
            /** custom code to unset unavailable attribute options **/
            $attrId = $attribute->getAttributeId();
            foreach ($attributeData['options'] as $key => $value) {
                    if(isset($filteredAttributes[$attrId])) {
                        if (!in_array($value['value_index'], $filteredAttributes[$attrId]))
                        {
                            unset($attributeData['options'][$key]);
                        }
                }
            }
            /** custom code ends here **/

            $this->attributeMap[$productId][$attribute->getId()] = $attribute->getData();
            $this->attributeMap[$productId][$attribute->getId()]['id'] = $attribute->getId();
            $this->attributeMap[$productId][$attribute->getId()]['uid'] = $this->uidEncoder->encode(
                self::OPTION_TYPE . '/' . $productId . '/' . $attribute->getAttributeId()
            );
            $this->attributeMap[$productId][$attribute->getId()]['attribute_id_v2'] =
                $attribute->getProductAttribute()->getAttributeId();
            $this->attributeMap[$productId][$attribute->getId()]['attribute_uid'] =
                $this->uidEncoder->encode((string) $attribute->getProductAttribute()->getAttributeId());
            $this->attributeMap[$productId][$attribute->getId()]['product_uid'] =
                $this->uidEncoder->encode((string) $attribute->getProductId());
            $this->attributeMap[$productId][$attribute->getId()]['attribute_code'] =
                $attribute->getProductAttribute()->getAttributeCode();
            $this->attributeMap[$productId][$attribute->getId()]['values'] = array_map(
                function ($value) use ($attribute) {
                    $value['attribute_id'] = $attribute->getAttributeId();
                    return $value;
                },
                $attributeData['options']
            );
            $this->attributeMap[$productId][$attribute->getId()]['label']
                = $attribute->getProductAttribute()->getStoreLabel();
        }
        return $this->attributeMap;
    }

    /**
     * Get allowed products.
     *
     * @param ProductInterface $product
     * @return ProductInterface[]
     */
    public function getAllowProducts(ProductInterface $product): array
    {
        return $this->variant->getSalableVariantsByParent($product) ?? [];
    }
}
