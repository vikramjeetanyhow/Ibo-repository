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
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * This classs is to handle product export
 */
class Products
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
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var AttributeSetRepositoryInterface
     */
    protected $attributeSetRepository;
    private Configurable $productLinkManagement;
    private ProductExportHelper $productExportHelper;
    private array $attributeCodeData;
    private ResourceConnection $resourceConnection;
    private array $stockData;
    private array $productLinkData;
    private array $data;
    private array $header;
    private array $attributeOptionData;
    private AttributeRepository $attributeRepository;
    private array $systemAttributes;
    private array $mediaData;

    /**
     * @param ProductFactory $productFactory
     * @param ProductCollectionFactory $productCollection
     * @param CsvDownload $csvDownload
     * @param Attribute $attribute
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param Configurable $productLinkManagement
     * @param ProductExportHelper $productExportHelper
     * @param ResourceConnection $resourceConnection
     * @param AttributeRepository $attributeRepository
     */
    public function __construct(ProductFactory $productFactory, ProductCollectionFactory $productCollection, CsvDownload $csvDownload, Attribute $attribute, AttributeSetRepositoryInterface $attributeSetRepository, Configurable $productLinkManagement, ProductExportHelper $productExportHelper, ResourceConnection $resourceConnection, AttributeRepository $attributeRepository)
    {
        $this->productFactory = $productFactory;
        $this->productCollection = $productCollection;
        $this->csvDownload = $csvDownload;
        $this->attribute = $attribute;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->productLinkManagement = $productLinkManagement;
        $this->productExportHelper = $productExportHelper;
        $this->resourceConnection = $resourceConnection;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * Export Products to CSV file
     *
     * @param int $attributeSetId
     * @throws LocalizedException
     */
    public function process(int $attributeSetId, $filepath)
    {
        if (!$attributeSetId || $attributeSetId == 0) {
            $this->productExportHelper->addLog("Product export Attribute Set Id not found");
        }
        if (!$this->isAttributeSetExist($attributeSetId)) {
            $this->productExportHelper->addLog("Product export Attribute Set Id not exist");
        }
        $this->getProductData($attributeSetId, $filepath);
    }

    /**
     * Check if attribute set exist
     * @param int $attributeSetId
     * @return bool
     */
    public function isAttributeSetExist(int $attributeSetId): bool
    {
        return (bool)$this->getAttributeSet($attributeSetId)->getAttributeSetId();
    }

    /**
     * Get attribute set id by attribute set name.
     *
     * @param int $attributeSetId
     */
    public function getAttributeSet(int $attributeSetId)
    {
        try {
            return $this->attributeSetRepository->get($attributeSetId);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Prepare product data.
     *
     * @param int $attributeSetId
     * @param string $filepath
     * @return void
     */
    public function getProductData(int $attributeSetId, $filepath): void
    {
        $attributes = $this->getAttributes($attributeSetId);
        $attributeCodes = array_column($attributes->getData(), 'attribute_code');
        $this->attributeCodeData = array_column($attributes->getData(), 'frontend_input', 'attribute_code');

        /** Load Production Relation Data */
        $this->productLinkData = $this->resourceConnection->getConnection()->fetchAll("select product_id, sku from catalog_product_super_link cpsl left join catalog_product_entity cpe on cpsl.parent_id = cpe.entity_id");
        if (count($this->productLinkData)) {
            $this->productLinkData = array_column($this->productLinkData, "sku", "product_id");
        }


        $attributeCodeTemp = implode('","', $attributeCodes);

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


        if (in_array('quantity_and_stock_status', $attributeCodes)) {
            $key = array_search('quantity_and_stock_status', $attributeCodes);
            if (false !== $key) {

                /** Load Stock Data **/
                $stockList = $this->resourceConnection->getConnection()->fetchAll("select product_id, qty, is_in_stock from cataloginventory_stock_item");
                foreach ($stockList as $stock) {
                    $this->stockData[$stock['product_id']]['qty'] = $stock['qty'];
                    $this->stockData[$stock['product_id']]['is_in_stock'] = $stock['is_in_stock'];
                }

                unset($attributeCodes[$key]);
                $attributeCodes[] = 'quantity';
                $attributeCodes[] = 'is_in_stock';
            }
        }

        if (in_array("media_gallery", $attributeCodes)){
            $sql = "select entity_id, value from catalog_product_entity_media_gallery_value_to_entity cpeve left join catalog_product_entity_media_gallery cpemg on cpeve.value_id = cpemg.value_id where value is not null";
            $mediaList = $this->resourceConnection->getConnection()->fetchAll($sql);
            foreach ($mediaList as $media) {
                $this->mediaData[$media['entity_id']][] = $media['value'];
            }
        }


        $this->header = array_combine($attributeCodes, $attributeCodes);
        $this->header["configurable_sku"] = "configurable_sku";
        $this->data[] = $this->header;

        $pageNum = 1;

        do {
            $products = $this->getProducts($attributeSetId, $attributeCodes, $pageNum);
            foreach ($products as $product) {
                $this->callback($product);
            }
            if (!empty($this->data) && count($this->data) > 1) {
                $this->csvDownload->writeNewCSV($this->data, $pageNum, $filepath);
            }

            $this->data = [];
            $pageNum++;
        } while ($pageNum <= $products->getLastPageNumber());

    }

    /**
     * Get attrubutes by attribute set.
     *
     * @param int $attributeSetId
     * @return AbstractCollection
     */
    public function getAttributes(int $attributeSetId): AbstractCollection
    {
        $attributeCollection = $this->attribute->getCollection()->addFieldToSelect("attribute_code")->addFieldToSelect("frontend_input")->addFieldToFilter("main_table.entity_type_id", 4);
        $attributeCollection->setOrder('attribute_id', 'ASC');

        $jointable = 'eav_entity_attribute';
        $attributeCollection->getSelect()->join(['ot' => $jointable], "main_table.attribute_id = ot.attribute_id AND ot.attribute_set_id = " . $attributeSetId);
        return $attributeCollection;
    }

    /**
     * Get product collection for attribute set.
     *
     * @param int $attributeSetId
     * @return Collection
     */
    public function getProducts(int $attributeSetId, array $attributes, $pageNum = 1): Collection
    {
        return $this->productCollection->create()->addAttributeToSelect($attributes)->addAttributeToFilter('type_id', 'simple')->addAttributeToFilter('attribute_set_id', $attributeSetId)->setCurPage($pageNum)->setPageSize($this->productExportHelper->getConfigs()->getFullDumpPageSize());
    }

    public function callback($product)
    {
        $formatedFields = $this->getProductFields($product);
        $this->data[] = array_intersect_key($formatedFields, $this->header);
    }

    /**
     * Get all attirubte fields of product
     *
     * @param Product $product
     * @return array
     */
    public function getProductFields(Product $product): array
    {
        $field = [];

        if (count($this->stockData)) {
            try {
                $field['quantity'] = $this->stockData[$product->getId()]['qty'] ?  $this->stockData[$product->getId()]['qty'] : 0 ;
                $field['is_in_stock'] = $this->stockData[$product->getId()]['is_in_stock'] ? "IN STOCK" : "OUT OF STOCK";
            } catch (Exception $e) {
                $this->productExportHelper->addLog("Product Id: " . $product->getId() . "Error: " . $e->getMessage());
            }
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


        try {
            foreach ($product->getData() as $attribute => $attributeValue) {

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
        } catch (Exception $exception) {
            $this->productExportHelper->addLog("Product Id: " . $product->getId() . "Error: " . $e->getMessage());
        }

        $formattedFields = [];
        foreach ($this->header as $key) {
            $formattedFields[$key] = isset($field[$key]) ? $field[$key] : " ";
        }
        return $formattedFields;
    }

}
