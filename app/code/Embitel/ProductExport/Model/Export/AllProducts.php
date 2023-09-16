<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\ProductExport\Model\Export;

use Embitel\ProductExport\Model\ProductExportHelper;
use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\ResourceModel\Iterator;

/**
 * This classs is to handle product export
 */
class AllProducts
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollection;

    /**
     * @var CsvDownload
     */
    protected $csvDownload;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;
    protected array $header;
    protected array $attributeCodes;
    protected array $data;
    private Collection $attributeCollection;
    private array $attributeSetList;
    private ResourceConnection $resourceConnection;
    private array $productLinkData;
    private array $attributeCodeData;
    private ProductExportHelper $productExportHelper;
    private array $attributeOptionData;
    private array $stockData;
    private array $systemAttributes;

    /**
     * @param ProductFactory $productFactory
     * @param ProductCollectionFactory $productCollection
     * @param CsvDownload $csvDownload
     * @param ScopeConfigInterface $scopeConfig
     * @param Iterator $iterator
     * @param AttributeRepositoryInterface $attributeRepository
     * @param Collection $attributeCollection
     * @param ResourceConnection $resourceConnection
     * @param ProductExportHelper $productExportHelper
     */
    public function __construct(ProductFactory $productFactory, ProductCollectionFactory $productCollection, CsvDownload $csvDownload, ScopeConfigInterface $scopeConfig, Iterator $iterator, AttributeRepositoryInterface $attributeRepository, Collection $attributeCollection, ResourceConnection $resourceConnection, ProductExportHelper $productExportHelper)
    {
        $this->productFactory = $productFactory;
        $this->productCollection = $productCollection;
        $this->csvDownload = $csvDownload;
        $this->attributeRepository = $attributeRepository;
        $this->scopeConfig = $scopeConfig;
        $this->iterator = $iterator;
        $this->attributeCollection = $attributeCollection;
        $this->resourceConnection = $resourceConnection;
        $this->productExportHelper = $productExportHelper;
    }

    /**
     * Export Products to CSV file
     *
     * @throws LocalizedException
     */
    public function process($filepath)
    {
        $this->getProductData($filepath);
    }

    /**
     * Prepare product data.
     *
     * @return void
     **/
    public function getProductData($filepath): void
    {

        /** Load Attribute Set Data **/
        $attributeSetCollection = $this->attributeCollection->getItems();
        foreach ($attributeSetCollection as $attributeSet) {
            $this->attributeSetList[$attributeSet->getAttributeSetId()] = $attributeSet->getAttributeSetName();
        }

        /** Load Production Relation Data */
        $this->productLinkData = $this->resourceConnection->getConnection()->fetchAll("select product_id, sku from catalog_product_super_link cpsl left join catalog_product_entity cpe on cpsl.parent_id = cpe.entity_id");
        if (count($this->productLinkData)) {
            $this->productLinkData = array_column($this->productLinkData, "sku", "product_id");
        }

        $this->attributeCodes = $this->getAttributeCode();
        $trimAttributeCodes = array_map('trim', $this->attributeCodes);
        $attributeCodeTemp = implode('","', $trimAttributeCodes);

        /** Load Attribute Type */
        $sql = ' select attribute_code, frontend_input, is_user_defined  from eav_attribute where attribute_code in ("' . $attributeCodeTemp . '") ';
        $attributeCodeType = $this->resourceConnection->getConnection()->fetchAll($sql);
        $this->attributeCodeData = array_column($attributeCodeType, "frontend_input", "attribute_code");
        $this->systemAttributes = array_column(array_filter($attributeCodeType, function ($data) {
            return !$data['is_user_defined'] && $data["frontend_input"] == "select";
        }), "attribute_code");

        /** Load Option Data */
        $selectTypeAttributeList = array_filter($this->attributeCodeData, function ($v, $k) {
            return $v == "select";
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($selectTypeAttributeList as $attributeCode => $attributeType) {
            try {
                $attributeOptions = $this->attributeRepository->get("catalog_product", $attributeCode);
                foreach ($attributeOptions->getOptions() as $attributeOption) {
                    $this->attributeOptionData[$attributeCode][$attributeOption->getValue()] = $attributeOption->getLabel();
                }
            } catch (NoSuchEntityException $e) {
                $this->productExportHelper->addLog($e->getMessage());
            }
        }

        /** Load Stock Data **/
        $stockList = $this->resourceConnection->getConnection()->fetchAll("select product_id, qty, is_in_stock from cataloginventory_stock_item");
        foreach ($stockList as $stock) {
            $this->stockData[$stock['product_id']]['qty'] = $stock['qty'];
            $this->stockData[$stock['product_id']]['is_in_stock'] = $stock['is_in_stock'];
        }


        if (in_array("media_gallery", $trimAttributeCodes)){
            $sql = "select entity_id, value from catalog_product_entity_media_gallery_value_to_entity cpeve left join catalog_product_entity_media_gallery cpemg on cpeve.value_id = cpemg.value_id where value is not null";
            $mediaList = $this->resourceConnection->getConnection()->fetchAll($sql);
            foreach ($mediaList as $media) {
                $this->mediaData[$media['entity_id']][] = $media['value'];
            }
        }

        $this->header = array_combine($trimAttributeCodes, $trimAttributeCodes);
        $this->header['product_id'] = 'product_id';
        $this->header["configurable_sku"] = "configurable_sku";
        $this->header["attribute_set"] = "attribute_set";
        $this->data[] = $this->header;
        $pageNum = 1;

        do {
            $products = $this->getProductsWithAttributes($trimAttributeCodes, $pageNum);
            foreach ($products as $product) {
                $this->callback($product, $trimAttributeCodes);
            }

            if (!empty($this->data) && count($this->data) > 1) {
                $this->csvDownload->writeNewCSV($this->data, $pageNum, $filepath);
            }

            $this->data = [];

            $pageNum++;
        } while ($pageNum <= $products->getLastPageNumber());
    }

    /**
     * Get Core attrubute .
     *
     * @return array
     */
    public function getAttributeCode(): array
    {
        $coreAttributeCodes = $this->scopeConfig->getValue("eboexport/ebo_config/core_attributes");
        if (trim($coreAttributeCodes) == '') {
            throw new LocalizedException(__('Please add core attributes in system configuration.'));
        }
        return explode(',', $coreAttributeCodes);
    }

    public function getProductsWithAttributes(array $attributes, $pageNum = 1): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        return $this->productCollection->create()->addAttributeToSelect($attributes)->addAttributeToFilter('type_id', ['eq' => 'simple'])->setCurPage($pageNum)->setPageSize($this->productExportHelper->getConfigs()->getFullDumpPageSize());
    }

    /** @var Product $product * */
    public function callback(Product $product, array $attributes)
    {
        $formatedFields = $this->getProductFields($product, $attributes);
        $this->data[] = array_intersect_key($formatedFields, $this->header);
    }

    /**
     * Get all attirubte fields of product
     *
     * @param Product $product
     * @return array
     */
    public function getProductFields(Product $product, array $attributes): array
    {
        $field = [];

        try {
            $field['quantity'] = $this->stockData[$product->getId()]['qty'] ? $this->stockData[$product->getId()]['qty'] : 0;
            $field['is_in_stock'] = $this->stockData[$product->getId()]['is_in_stock'] ? "IN STOCK" : "OUT OF STOCK";
        } catch (Exception $e) {
            $this->productExportHelper->addLog("Product Id: " . $product->getId() . "Error: " . $e->getMessage());
        }

        $field['product_id'] = $product->getId();

        /** Add Configurable sku  **/
        try {
            if (count($this->productLinkData)) {
                $field['configurable_sku'] = $this->productLinkData[$product->getId()];
            } else {
                $field['configurable_sku'] = " ";
            }
        } catch (Exception $e) {
            $field['configurable_sku'] = " ";
        }

        /** Add Attribute sku  **/
        try {
            $field['attribute_set'] = $this->attributeSetList[$product->getAttributeSetId()];
        } catch (Exception $e) {
            $field['attribute_set'] = " ";
        }

        try {
            foreach ($attributes as $attribute) {
                $attributeCode = trim($attribute);
                if (in_array($attributeCode, ['product_id', 'quantity', 'is_in_stock'])) {
                    continue;
                }
                //Value is object, then convert to String.

                $attributeValue = $product->getData($attributeCode);

                if (is_null($attributeValue) && in_array($attributeCode, $this->systemAttributes)) {
                    $attributeValue = $product->getAttributeText($attributeCode);
                }

                if (is_object($attributeValue)) {
                    $attributeValue = (string)$attributeValue;
                }

                //Specific attribute value and attribute frontend input values.
                if ($attributeCode == 'media_gallery') {
                    if (isset($this->mediaData[$product->getId()])) {
                        $field[$attributeCode] = implode(",", $this->mediaData[$product->getId()]);
                    }
                } elseif ($attributeCode == 'tier_price') {
                    $field[$attributeCode] = '';
                } elseif ($attributeCode == 'category_ids') {
                    $field[$attributeCode] = '';
                } elseif (isset($this->attributeCodeData[$attributeCode])) {
                    if ($this->attributeCodeData[$attributeCode] == "select") {
                        try {
                            $attributeValue = $this->attributeOptionData[$attributeCode][$attributeValue];
                            if (is_object($attributeValue)) {
                                $attributeValue = (string)$attributeValue;
                            }
                        } catch (Exception $e) {
                            $attributeValue = "";
                            $this->productExportHelper->addLog("File: " . $e->getFile() . "line number: " . $e->getLine() . "message: " . $e->getMessage());
                        }

                        if ($attributeCode == 'custom_design') {
                            $attributeValue = ($attributeValue != '-- Please Select --') ? $attributeValue : '';
                        }
                        $field[$attributeCode] = $attributeValue;
                    } else if ($this->attributeCodeData[$attributeCode] == 'boolean') {
                        $field[$attributeCode] = ($attributeValue) ? 'Yes' : 'No';
                    } else {
                        $field[$attributeCode] = $attributeValue;
                    }
                } else {
                    $field[$attributeCode] = $attributeValue;
                }
                if (empty($field[$attributeCode])) {
                    $field[$attributeCode] = " ";
                }
            }
        } catch (NoSuchEntityException $e) {
            $this->productExportHelper->addLog($e->getMessage());
        }


        $formattedFields = [];
        foreach ($this->header as $key) {
            $formattedFields[$key] = isset($field[$key]) ? $field[$key] : "";
        }
        return $formattedFields;
    }

    /**
     * Get product collection.
     *
     */
    public function getProducts(): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        return $this->productCollection->create()->addAttributeToFilter('type_id', 'simple')->setOrder('entity_id', 'ASC');
    }
}
