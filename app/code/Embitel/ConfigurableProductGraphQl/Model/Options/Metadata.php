<?php

declare(strict_types=1);

namespace Embitel\ConfigurableProductGraphQl\Model\Options;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\ConfigurableProduct\Helper\Data;
use Magento\ConfigurableProductGraphQl\Model\Options\SelectionUidFormatter;

/**
 * Retrieve metadata for configurable option selection.
 */
class Metadata
{

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CollectionFactory
     */
    private $childCollectionFactory;

    /**
     * @var Data
     */
    private $configurableProductHelper;

    /**
     * @param Data $configurableProductHelper
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $childCollectionFactory
     */
    public function __construct(
        Data $configurableProductHelper,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $childCollectionFactory,
        SelectionUidFormatter $selectionUidFormatter
    ) {
        $this->configurableProductHelper = $configurableProductHelper;
        $this->productRepository = $productRepository;
        $this->childCollectionFactory = $childCollectionFactory;
        $this->selectionUidFormatter = $selectionUidFormatter;
    }

    /**
     * Load available selections from configurable options.
     *
     * @param ProductInterface $product
     * @param string $esin
     * @return array
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getAvailableSelections(
        ProductInterface $product,
        string $esin
    ): array {

        /** fetching child product data **/
        $childCollection = $this->childCollectionFactory->create();
        $childCollection->setProductFilter($product);
        $childCollection->addAttributeToFilter('esin',$esin);
        $childCollection->load();
        $childData = $childCollection->getData();
        $childId = $childData[0]['entity_id'];
        $variantProduct = $this->productRepository->getById($childId);
        $variantData = $variantProduct->getData();
        $variantData['model'] = $variantProduct;
        
        /** fetching child product attributes **/
        $options = $this->configurableProductHelper->getOptions($product, [$variantProduct]);
        $attributeCodes = $this->getAttributeCodes($product);
        $availableSelections = $availableProducts = [];
        if (isset($options['index']) && $options['index']) {
            foreach ($options['index'] as $productId => $productOptions) {
                $availableProducts[] = $productId;
                foreach ($productOptions as $attributeId => $optionIndex) {
                    $uid = $this->selectionUidFormatter->encode($attributeId, (int)$optionIndex);

                    if (isset($availableSelections[$attributeId]['option_value_uids'])
                        && in_array($uid, $availableSelections[$attributeId]['option_value_uids'])
                    ) {
                        continue;
                    }
                    $availableSelections[$attributeId]['option_value_uids'][] = $uid;
                    $availableSelections[$attributeId]['attribute_code'] = $attributeCodes[$attributeId];
                }
            }
        }

        return [
            'attributes' => $availableSelections,
            'variant' => $variantData
        ];
    }

    /**
     * Retrieve attribute codes
     *
     * @param ProductInterface $product
     * @return string[]
     */
    private function getAttributeCodes(ProductInterface $product): array
    {
        $allowedAttributes = $this->configurableProductHelper->getAllowAttributes($product);
        $attributeCodes = [];
        foreach ($allowedAttributes as $attribute) {
            $attributeCodes[$attribute->getAttributeId()] = $attribute->getProductAttribute()->getAttributeCode();
        }

        return $attributeCodes;
    }
}
