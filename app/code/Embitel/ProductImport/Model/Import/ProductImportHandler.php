<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Import;

use Embitel\ProductImport\Helper\ProductImportApiHelper;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Framework\App\State;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\Catalog\Api\SpecialPriceInterface;
use Magento\Catalog\Api\Data\SpecialPriceInterfaceFactory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Embitel\ProductImport\Model\ProductName;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Exception\NoSuchEntityException;
use Embitel\Catalog\Helper\Data as CatalogHelper;
use PHPUnit\Exception;

/**
 * This classs is to handle product import
 */
class ProductImportHandler
{
    public const TABLE_NAME_EMBITEL_TAX_MASTER = 'embitel_tax_master';
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRepository;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var StoreWebsiteRelationInterface
     */
    protected $storeWebsiteRelation;

    /**
     * @var SpecialPriceInterface
     */
    protected $specialPrice;

    /**
     * @var SpecialPriceInterfaceFactory
     */
    protected $specialPriceFactory;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var ProductFieldProcessor
     */
    protected $productFieldProcessor;

    /**
     * @var CsvProcessor
     */
    protected $csvProcessor;

    /**
     * @var ProductName
     */
    protected $productName;

    /**
     * @var CategoryLinkManagementInterface
     */
    protected $categoryLinkManagement;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection = null;

    protected $attibuteOptions = [];

    protected $products = [];

    protected $configurableMethod = null;
    protected $productModel;

    protected $imageProcessor;
    private State $state;

    /**
     * @var int[]|string[]
     */
    private array $avalibleOfferIds;
    private $saleUomOptions;
    private \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection;
    private array $productUomValidation;
    private ProductImportApiHelper $productImportApiHelper;
    private StockRegistryInterface $stockRegistry;

    /**
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRepository
     * @param AttributeRepository $attributeRepository
     * @param StoreWebsiteRelationInterface $storeWebsiteRelation
     * @param SpecialPriceInterface $specialPrice
     * @param SpecialPriceInterfaceFactory $specialPriceFactory
     * @param DirectoryList $directoryList
     * @param Attribute $attribute
     * @param ProductFieldProcessor $productFieldProcessor
     * @param CsvProcessor $csvProcessor
     * @param ProductName $productName
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Model\Product\Gallery\Processor $imageProcessor
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     * @param ResourceConnection $resourceConnection
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRepository,
        AttributeRepository $attributeRepository,
        StoreWebsiteRelationInterface $storeWebsiteRelation,
        SpecialPriceInterface $specialPrice,
        SpecialPriceInterfaceFactory $specialPriceFactory,
        DirectoryList $directoryList,
        Attribute $attribute,
        ProductFieldProcessor $productFieldProcessor,
        CsvProcessor $csvProcessor,
        ProductName $productName,
        CategoryLinkManagementInterface $categoryLinkManagement,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Model\Product\Gallery\Processor $imageProcessor,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        ResourceConnection $resourceConnection,
        State $state,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        ProductImportApiHelper $productImportApiHelper,
        StockRegistryInterface $stockRegistry,
        CatalogHelper $catalogHelper
    ) {
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->stockRepository = $stockRepository;
        $this->attributeRepository = $attributeRepository;
        $this->storeWebsiteRelation = $storeWebsiteRelation;
        $this->specialPrice = $specialPrice;
        $this->specialPriceFactory = $specialPriceFactory;
        $this->directoryList = $directoryList;
        $this->attribute = $attribute;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->csvProcessor = $csvProcessor;
        $this->productName = $productName;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->resourceConnection = $resourceConnection;
        $this->productModel = $productModel;
        $this->imageProcessor = $imageProcessor;
        $this->_fileDriver = $fileDriver;
        $this->connection = $this->resourceConnection->getConnection();
        $this->state = $state;
        $this->productCollection = $productCollection;
        $this->productImportApiHelper = $productImportApiHelper;
        $this->stockRegistry = $stockRegistry;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Import Products from CSV file
     *
     * @param array $file file info retrieved from $_FILES array
     * @return array
     * @throws LocalizedException
     */
    public function importFromCsvFile($file, $bulkId = null)
    {
        $pathinfo = pathinfo($file['name']);
        if (!isset($file['tmp_name'])) {
            throw new LocalizedException(__('Invalid file upload attempt.'));
        }
        if (!in_array($pathinfo['extension'], ['csv'])) {
            throw new LocalizedException(__('Please upload CSV file.'));
        }
        $csvData = $this->csvProcessor->getCsvData($file['tmp_name']);
        $filteredCsvData = $this->productFieldProcessor->getFilteredCsvData($csvData);
        $this->loadAttributeAllOptions();
        if (isset($filteredCsvData['failure'])) {
            $this->products['failure'] = $filteredCsvData['failure'];
        }
        if (isset($filteredCsvData['success'])) {
            $this->processProducts($filteredCsvData['success']);
        }
        if (isset($filteredCsvData['update'])) {
            $this->validateIfTinted($filteredCsvData['update']);
            $this->updateProducts($filteredCsvData['update']);
            unset($this->productUomValidation);
            unset($this->avalibleOfferIds);
        }

        return $this->csvProcessor->prepareResponse($this->products, $bulkId);
    }

    /**
     * @param $csvData
     */
    public function validateIfTinted($csvData)
    {
        $baseOfferIdList = [];
        foreach ($csvData as $rowIndex => $keyValue) {

            if (isset($keyValue['is_bom']) && $keyValue['is_bom'] == 1) {
                $baseOfferIdList[] = $keyValue['base_offer_id'];
                if (isset($keyValue['secondary_offer_id'])) {
                    try {
                        $secondaryOfferId = json_decode($keyValue['secondary_offer_id'], true);
                        foreach ($secondaryOfferId as $secondaryOfferObject) {
                            $baseOfferIdList[] = $secondaryOfferObject['offer_id'];
                        }
                    } catch (\Exception $exception) {

                    }

                }
            }
        }
        if (count($baseOfferIdList)) {
            $baseOfferIdList = array_unique($baseOfferIdList);
            $baseSkus = implode(',', $baseOfferIdList);
            $uomSql = "select sku, cpei.value as `sale_uom` from catalog_product_entity cpe left join catalog_product_entity_int cpei on cpe.entity_id = cpei.entity_id && cpei.store_id = 0 && cpei.attribute_id =(select attribute_id from eav_attribute where attribute_code = 'sale_uom' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product')) where cpe.type_id = 'simple' && cpe.attribute_set_id in (select attribute_set_id from eav_attribute_set where cpe.sku in ($baseSkus))";
            $this->productUomValidation = array_column($this->resourceConnection->getConnection()->fetchAll($uomSql), 'sale_uom', 'sku');
            $this->avalibleOfferIds = array_keys($this->productUomValidation);
            $attribute = $this->attributeRepository->get("sale_uom");
            foreach ($attribute->getOptions() as $option) {
                $this->saleUomOptions[$option->getLabel()] = $option->getValue();
            }
        } else {
            $this->avalibleOfferIds = [];
            $this->productUomValidation = [];
            $this->saleUomOptions[] = [];
        }
        unset($tintedCheckHeader);
        unset($baseOfferIdList);
        unset($baseSkus);
        unset($uomSql);
        unset($attribute);
    }

    /**
     * Load all options of the select attributes.
     */
    public function loadAttributeAllOptions()
    {
        $attributeCollection = $this->attribute->getCollection()
                ->addFieldToFilter("entity_type_id", 4)
                ->addFieldToFilter("frontend_input", ['in' => ["select", "multiselect"]]);
        foreach ($attributeCollection as $attribute) {
            $this->loadAttributeOptions($attribute->getAttributeCode());
        }
    }

    /**
     * Get attribute options of dropdown attribute.
     *
     * @param type $attributeCode
     */
    public function getAttributeOptionsValue($attributeCode, $attributelabel)
    {
        $attribute = $this->getAttribute($attributeCode);
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if (trim($option->getLabel()) == trim($attributelabel)) {
                return $option->getValue();
            }
        }
    }

    /**
     * Get attribute options of dropdown attribute.
     *
     * @param type $attributeCode
     */
    public function loadAttributeOptions($attributeCode)
    {
        $attribute = $this->getAttribute($attributeCode);
        if (!$attribute) {
            return;
        }
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            $label = (string) $option->getLabel();
            $optionLabel = trim($label);
            if ($optionLabel != "" && $optionLabel != null) {
                $this->attibuteOptions[$attributeCode][$optionLabel] = $option->getValue();
            }
        }

        $this->attibuteOptions[$attributeCode]['swatchtype'] = $attribute->getSwatchInputType();
        $this->attibuteOptions[$attributeCode]['attribute_type'] = $attribute->getAttributeType();
    }

    /**
     * Get attribute by code
     *
     * @param type $attributeCode
     * @return type
     */
    public function getAttribute($attributeCode)
    {
        try {
            return $this->attributeRepository->get($attributeCode);
        } catch (\Exception $ex) {
            //$this->productFieldProcessor->log($attributeCode . ": Attribute not exist. Error:" . $ex->getMessage());
            return null;
        }
    }

    public function loadConfigFields($attributeSetCode)
    {
        if (!$this->configurableMethod) {
            $this->configurableMethod = $this->csvProcessor->getEvalFormula($attributeSetCode);
        }
    }

    /**
     * Check for duplicate variant values while product import.
     *
     * @param type $groupData
     * @return boolean
     */
    public function isDuplocateVariant($groupData)
    {
        $duplicateVariant = false;
        $groupRawVariants = [];

        //Check for duplicate variant in same unique group ids.
        foreach ($groupData as $groupRaw) {
            if (!$this->productFieldProcessor->isConfigurableRow($groupRaw)) {
                continue;
            }
            if ($this->isDuplicateVariantInExistingProducts($groupRaw)) {
                $duplicateVariant = true;
            } else {
                $variantCombination = '';
                foreach ($this->productFieldProcessor->getVariantAttributes() as $variantAttribute) {
                    $variantCombination .= ($variantCombination) ? '_' : '';
                    $variantCombination .= $groupRaw[$variantAttribute];
                }
                if (!in_array($variantCombination, $groupRawVariants)) {
                    $groupRawVariants[] = $variantCombination;
                } else {
                    $duplicateVariant = true;
                }
            }
        }

        if ($duplicateVariant) {
            foreach ($groupData as $groupRaw) {
                unset($groupRaw['attribute_set_id']);
                unset($groupRaw['category_ids']);
                $groupRaw['error'] = "Duplicate variant values for same unique_group_id";
                $this->products['failure'][] = $groupRaw;
            }
            return true;
        }
        return false;
    }

    /**
     * Check while creating simple product under existing configurable product.
     *
     * @param type $groupRaw
     * @return type
     */
    public function isDuplicateVariantInExistingProducts($groupRaw)
    {
        $products = $this->productFactory->create()->getCollection()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('unique_group_id', $groupRaw['unique_group_id']);
        foreach ($this->productFieldProcessor->getVariantAttributes() as $variantAttribute) {
            $optionId = $this->productFieldProcessor->getOptionIdbyAttributeCodeandLabel($variantAttribute, $groupRaw[$variantAttribute]);
            $products->addAttributeToFilter($variantAttribute, $optionId);
        }

        return ($products->getSize() > 0) ? true : false;
    }

    /**
     * @param type $filteredCsvData
     */
    protected function processProducts($filteredCsvData)
    {
        foreach ($filteredCsvData as $groupData) {
            if ($this->isDuplocateVariant($groupData)) {
                continue;
            }

            /* Is Default Product - Start */
            $isDefaultArraykey = array_search('1', array_column($groupData, 'is_default'));
            $isDefaultSimpleProductArray = isset($groupData[$isDefaultArraykey]) ? $groupData[$isDefaultArraykey] : 0;
            $isDefaultSimpleID = '';
            /* Is Default Product - End */

            $simpleProductIds = [];
            foreach ($groupData as $groupRaw) {
                $this->loadConfigFields($groupRaw['attribute_set_code']);
                /*if (!$this->productFieldProcessor->isDataUnique($groupRaw)) {
                    unset($groupRaw['attribute_set_id']);
                    unset($groupRaw['category_ids']);
                    $groupRaw['error'] = "Row is not unique.";
                    $this->products['failure'][] = $groupRaw;
                    $this->productFieldProcessor->log("Row is not unique.----------");
                    continue;
                }
                $this->productFieldProcessor->log("-------unique row------.");*/
                $simplePrdID = $this->importProduct($groupRaw);
                if ($simplePrdID) {
                    $simpleProductIds[] = $simplePrdID;
                }
                if (isset($groupRaw['is_default']) && $groupRaw['is_default'] && $simplePrdID) {
                    $isDefaultSimpleID = $simplePrdID;
                }
            }
            if (!empty($simpleProductIds)) {
                $this->createConfigurableProduct($simpleProductIds, $isDefaultSimpleProductArray, $isDefaultSimpleID);
            }
        }
    }

    /**
     * Import single product
     *
     * @param array $rawData
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function importProduct($rawData)
    {
        /* SKU Rule - Start */
        $sku = $this->genSku();
        $rawData['sku'] = $sku;
        /* SKU Rule - End */

        /* ESIN Rule - Start */
        $rawData['esin'] = $this->genEsin();
        /* ESIN Rule - End */

        /* Product Name -  Start */
        $rawData['name'] = $this->evalTitle($rawData);
        /* Product Name -  End */

        $rawData['production_start_date'] =  date("m/d/Y");

        /* Slug - Start */
        //$rawData['slug'] = $this->genSlug($rawData['esin']);
        $rawData['slug'] = $this->genSlug($rawData['name']);
        /* Slug - End */
        $product = $this->productFactory->create();
        try {
            $productRepo = $this->productRepository->get($sku);
            $product->load($productRepo->getId());
        } catch (\Exception $ex) {
            //$this->productFieldProcessor->log("product not exist so not loading");
        }

        $simpleProductId = null;
        try {
            $data = [
                'type_id' => 'simple',
                'status' => ProductStatus::STATUS_DISABLED,
                'attribute_set_id' => $rawData['attribute_set_id'],
                'name' => $rawData['name'],
                'sku' => $sku,
                'price' => (isset($rawData['mrp'])) ? $rawData['mrp'] : '',
                'tax_class_id' => $rawData['tax_class_id'],
                'url_key' => $sku,
                'meta_title' => $rawData['name'],
                'website_ids' => [1],
                'store_id' => 0,
                'is_product_having_manual_title' => $this->getIsProductHavingManualTitle($rawData),
                'allow_simple_product_to_buy' => $this->getSimpleProductAllowToBuy($rawData),
                'visibility' => $this->getProductVisibility($rawData),
                'offerid' => $sku,
                'barcode' => $sku,
                'ebo_title' => $rawData['name'],
                'seller_id' => (isset($rawData['seller_id']) && $rawData['seller_id'] != '') ? $rawData['seller_id'] : 1,
                'approval' => '2',
                'facade_sync_count' => 0,
                'seller_sku_id' => $sku,
                'vendor_id' => (isset($rawData['vendor_id']) && $rawData['vendor_id'] != '') ? $rawData['vendor_id'] : 1
                ];
                //@@@@@ harcoded- swarch, RMA is return etc attribute @@@@@@
            $error = [];
            $newAttributes = array_diff_key($rawData, $data);
            foreach ($newAttributes as $newAttributeCode => $newAttributeValue) {
                if (!is_array($newAttributeValue)) {
                    $newAttributeValue = trim($newAttributeValue);
                }
                $attribute = $this->getAttribute($newAttributeCode);
                if (!$attribute || $newAttributeValue == '') {
                    continue;
                }

                $attributeValue = $this->getAttributeValue($attribute->getFrontendInput(), $newAttributeCode, $newAttributeValue);
                if ($attributeValue !== '') {
                    $data[$newAttributeCode] = $attributeValue;
                } else {
                    $error[] = $newAttributeCode;
                }
            }

            if (empty($error)) {
                $product->addData($data);
                $product->save();

                if (isset($data['allowed_channels']) && $data['allowed_channels'] == 'STORE') {
                    $stockItem = $this->stockRegistry->getStockItem($product->getId());
                    $stockItem->setData('is_in_stock', 1);
                    $stockItem->setData('qty', 1);
                    $stockItem->save();
                    $product->save();
                }

                unset($rawData['attribute_set_id']);
                unset($rawData['category_ids']);
                $rawData['ebo_title'] = $rawData['name'];
                $rawData['seller_sku_id'] = $sku;
                $rawData['offerid'] = $sku;
                $rawData['barcode'] = $sku;

                $this->products['success'][] = $rawData;
                $this->products['odoo'][] = $this->productFieldProcessor->getOdooFields($rawData);
                if ($this->productFieldProcessor->isConfigurableRow($rawData)) {
                    $simpleProductId = $this->assignVarient($product->getId(), $rawData['unique_group_id']);
                }
            } else {
                $rawData['error'] = "Invalid fields: ";
                $rawData['error'] .= implode(',', $error);
                $rawData['sku'] = '';
                unset($rawData['name']);
                unset($rawData['esin']);
                unset($rawData['slug']);
                unset($rawData['production_start_date']);
                unset($rawData['attribute_set_id']);
                unset($rawData['category_ids']);
                unset($rawData['ibo_category_id']);
                unset($rawData['tax_class_id']);
                $this->products['failure'][] = $rawData;
                return $simpleProductId;
            }
        } catch (\Exception $e) {
            $rawData['error'] = $e->getMessage();
            $rawData['sku'] = '';
            unset($rawData['name']);
            unset($rawData['esin']);
            unset($rawData['slug']);
            unset($rawData['production_start_date']);
            unset($rawData['attribute_set_id']);
            unset($rawData['category_ids']);
            unset($rawData['ibo_category_id']);
            unset($rawData['tax_class_id']);
            $this->products['failure'][] = $rawData;
            return $simpleProductId;
        }
       //  $this->addImages($rawData, $product, true, false);
        //$this->setStock($sku, 1000, 1);
        //$this->setSpecialPrice($rawData['sku'], $rawData['special_price']);
        //$this->setCommonAttribute($product,$rawData['slug'],$simpleProductId);        //OfferID and Slug Update
        return $simpleProductId;
    }

    /**
     * Assign simple product to existing configurable product if exist with same unique group id
     *
     * @param type $simpleProductId
     * @param type $uniqueGroupId
     * @return boolean
     */
    private function assignVarient($simpleProductId, $uniqueGroupId)
    {
        try {
            $configurableProductLoad = $this->getConfigurableProduct($uniqueGroupId);
            if (!$configurableProductLoad) {
                return $simpleProductId;
            }
            $associateProducts = $configurableProductLoad->getTypeInstance()->getUsedProducts($configurableProductLoad);
            $associateProductIds = [];
            $associateProductIds[] = $simpleProductId;
            foreach ($associateProducts as $associateProduct) {
                $associateProductIds[] = $associateProduct->getId();
            }

            $configurableProductLoad->setAssociatedProductIds($associateProductIds);
            $configurableProductLoad->setCanSaveConfigurableAttributes(true);
            $configurableProductLoad->setStoreId(0);
            $configurableProductLoad->save();
        } catch (\Exception $e) {
            $this->productFieldProcessor->log("varient assign error:" . $e->getMessage());
        }
        return false;
    }

    /**
     * Get product visibility
     *
     * @param type $rawData
     */
    public function getProductVisibility($rawData)
    {
        $type = $this->productFieldProcessor->getProductTypeField($rawData);
        return (in_array($type, ['simple']))
            ? \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
            : \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH;
    }

    /**
     * Check if simple product is allow to buy.
     *
     * @param type $rawData
     */
    public function getSimpleProductAllowToBuy($rawData)
    {
        $type = $this->productFieldProcessor->getProductTypeField($rawData);
        return (in_array($type, ['simple'])) ? 1 : 0;
    }

    /**
     * Check if simple product is allow to buy.
     *
     * @param type $rawData
     */
    public function getIsProductHavingManualTitle($rawData)
    {
        if (isset($rawData['manual_title']) && trim($rawData['manual_title']) != '') {
            return 1;
        }
        return 0;
    }

    /**
     * Get configurable products assigned to group id.
     *
     * @param type $uniqueGroupId
     * @return type
     */
    private function getConfigurableProduct($uniqueGroupId)
    {
        $products = $this->productFactory->create()->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'configurable')
            ->addAttributeToFilter('unique_group_id', $uniqueGroupId);

        return ($products->getSize() > 0) ? $products->getFirstItem() : false;
    }

    /**
     * Create configurable product
     *
     * @param type $simpleProductIds
     */
    public function createConfigurableProduct($simpleProductIds, $isDefaultSimpleProductArray = null, $isDefaultSimpleID)
    {
        $data = [];
        foreach ($isDefaultSimpleProductArray as $newAttributeCode => $newAttributeValue) {
            $attribute = $this->getAttribute($newAttributeCode);
            if (!is_array($newAttributeValue)) {
                $newAttributeValue = trim($newAttributeValue);
            }
            if (!$attribute || $newAttributeValue == '') {
                continue;
            }
            $data[$newAttributeCode] = $this->getAttributeValue($attribute->getFrontendInput(), $newAttributeCode, $newAttributeValue);
        }

        /* ESIN Rule - Start */
        $data['esin'] = $this->genEsin();
        /* ESIN Rule - End */

        /* Slug - Start */
        //$data['slug'] = $this->genSlug($data['esin']);
        /* Slug - End */

        $data['is_product_having_manual_title'] = $this->getIsProductHavingManualTitle($isDefaultSimpleProductArray);

        $data['production_start_date'] =  date("m/d/Y");

        $configurableproduct = $this->productFactory->create();
        $configurableproduct->addData($data);

        /* Is Default Product - Start */
        $configPrdSKU = $configName = $categoryName = $shortDec = $Desc = $slug = '';
        $Desc = (isset($isDefaultSimpleProductArray['description'])) ? $isDefaultSimpleProductArray['description'] : '';
        $shortDec = (isset($isDefaultSimpleProductArray['short_description'])) ? $isDefaultSimpleProductArray['short_description'] : '';
        if ($isDefaultSimpleProductArray!=null) {
            $configName =  $this->evalTitle($isDefaultSimpleProductArray);
            $configPrdSKU = $this->genSku();
            $slug = $this->genSlug($configName);
            $Desc = (isset($isDefaultSimpleProductArray['description'])) ? $isDefaultSimpleProductArray['description'] : '';
        }
        /* Is Default Product - End */

        try {
            $configurableproductRepo = $this->productRepository->get($configPrdSKU);
            $configurableproduct->load($configurableproductRepo->getId());
        } catch (\Exception $ex) {
            //$this->productFieldProcessor->log("configurable product not exist so not loading");
        }

        $sellerId = (isset($isDefaultSimpleProductArray['seller_id']) && $isDefaultSimpleProductArray['seller_id'] != '')
            ? $isDefaultSimpleProductArray['seller_id']
            : 1;
        $vendorId = (isset($isDefaultSimpleProductArray['vendor_id']) && $isDefaultSimpleProductArray['vendor_id'] != '')
            ? $isDefaultSimpleProductArray['vendor_id']
            : 1;
        $configurableproduct->setSku($configPrdSKU);
        $configurableproduct->setName($configName);
        $configurableproduct->setSlug($slug);
        $configurableproduct->setEboTitle($configName);
        $configurableproduct->setMetaTitle($configName);
        $configurableproduct->setAttributeSetId($isDefaultSimpleProductArray['attribute_set_id']);
        $configurableproduct->setDescription($Desc);
        $configurableproduct->setShortDescription($shortDec);
        $configurableproduct->setUrlKey($configPrdSKU);
        $configurableproduct->setOfferid($configPrdSKU);
        $configurableproduct->setBarcode($configPrdSKU);
        $configurableproduct->setStatus(1);
        $configurableproduct->setTypeId('configurable');
        $configurableproduct->setVisibility(4);
        $configurableproduct->setPrice(0);
        $configurableproduct->setTaxClassId(6);
        $configurableproduct->setWebsiteIds([1]);
        $configurableproduct->setStoreId(0);
        $configurableproduct->setApproval('2');
        $configurableproduct->setVendorId($vendorId);
        $configurableproduct->setSellerId($sellerId);

        $configurableproduct->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 1,
            'is_in_stock' => 1
        ]);

        $attributeIds = [];
        foreach (explode(",", $this->configurableMethod['ConfigurableAttributes']) as $attributeCode) {
            $attributeIds[] = $configurableproduct->getResource()->getAttribute(trim($attributeCode))->getId();
        }
        $configurableproduct->getTypeInstance()->setUsedProductAttributeIds($attributeIds, $configurableproduct);
        $configurableAttributesData =
            $configurableproduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableproduct);
        $configurableproduct->setCanSaveConfigurableAttributes(true);
        $configurableproduct->setConfigurableAttributesData($configurableAttributesData);
        $configurableProductsData = [];
        $configurableproduct->setConfigurableProductsData($configurableProductsData);
        try {
            $configurableproduct->save();
            $this->productFieldProcessor->log("config created: " . $configurableproduct->getId());
            $this->productFieldProcessor->log($simpleProductIds);
        } catch (\Exception $ex) {
            $this->productFieldProcessor->log("config save error:" . $ex->getMessage());
        }

        $this->addImages($isDefaultSimpleProductArray, $configurableproduct, false, false);
        $product_id = $configurableproduct->getId();
        try {
            $configurableproduct_load = $this->productFactory->create()->load($product_id);
            $configurableproduct_load->setAssociatedProductIds($simpleProductIds);
            $configurableproduct_load->setCanSaveConfigurableAttributes(true);
            $configurableproduct_load->setStoreId(0);
            $configurableproduct_load->save();
            $this->productFieldProcessor->log("Simple assigned to config.");
        } catch (\Exception $ex) {
            $this->productFieldProcessor->log("varient assign error:" . $ex->getMessage());
        }
    }

    /**
     * Update product by sku.
     *
     * @param type $raws
     */
    private function updateProducts($raws)
    {
        $requiredToPublishFileds = $this->productFieldProcessor->getPublishAttributes();
        foreach ($raws as $raw) {
            try {
                $entityVar = '';
                $uniqueColumn = '';
                if(isset($raw['sku']) && $raw['sku'] !='') {
                    $entityVar = $raw['sku'];
                    $uniqueColumn = 'sku';
                    $product = $this->productFactory->create()->loadByAttribute('sku', $entityVar);
                }else if(isset($raw['esin']) && $raw['esin'] !='') {
                    $entityVar = $raw['esin'];
                    $uniqueColumn = 'esin';
                    $product = $this->productFactory->create()->loadByAttribute('esin', $entityVar);
                }

                if (!$product) {
                    $raw['error'] = "Product doesn't exist with ".$uniqueColumn.":" . $entityVar;
                    $this->products['failure'][] = $raw;
                    continue;
                }

                if (isset($raw["is_bom"]) && $raw["is_bom"] == 1) {

                    if (!isset($raw['base_offer_id'])) {
                        $raw['error'] = "Please add base_offer_id: " . $entityVar;
                        $this->products['failure'][] = $raw;
                        continue;
                    }

                    if (isset($raw['base_offer_id']) && !in_array($raw['base_offer_id'], $this->avalibleOfferIds) ) {
                        $raw['error'] = 'base offer id is not available in the system';
                        $this->products['failure'][] = $raw;
                        continue;
                    }

                    if (!isset($raw['secondary_offer_id'])) {
                        $raw['error'] = "Please add secondary_offer_id: " . $entityVar;
                        $this->products['failure'][] = $raw;
                        continue;
                    }

                    if (isset($raw['secondary_offer_id'])) {
                        $secondaryOfferIds = json_decode($raw['secondary_offer_id'], true);
                        if (is_null($secondaryOfferIds)) {
                            $raw['error'] =  'secondary_offer_id value should be array of objects';
                            $this->products['failure'][] = $raw;
                            continue;
                        }
                        if (is_array($secondaryOfferIds)) {
                            $count = 1;
                            foreach ($secondaryOfferIds as $secOfferid) {
                                if (count($secOfferid) !== 3) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count, is not valid";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }
                                if(!isset($secOfferid['offer_id'])) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count, offer_id is not set";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }
                                if(!isset($secOfferid['quantity_uom'])) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count, quantity_uom is not set";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }
                                if(!isset($secOfferid['quantity'])) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count, quantity is not set";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }
                                if(isset($secOfferid['offer_id']) && !in_array($secOfferid['offer_id'], $this->avalibleOfferIds)) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count, offer_id is not available int the system";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }
                                if(isset($secOfferid['quantity_uom']) && in_array($secOfferid['offer_id'], $this->avalibleOfferIds)) {
                                    if(!isset($this->saleUomOptions[$secOfferid['quantity_uom']])) {
                                        $raw['error'] =  "secondary_offer_id object pointer $count, quantity_uom is not available in the system";
                                        $this->products['failure'][] = $raw;
                                        continue;
                                    }
                                    if (!isset($this->productUomValidation[$secOfferid['offer_id']])) {
                                        $raw['error'] =  "secondary_offer_id object pointer $count, quantity_uom is not set for base sku or offer id in the system";
                                        $this->products['failure'][] = $raw;
                                        continue;
                                    }
                                    if(isset($this->saleUomOptions[$secOfferid['quantity_uom']]) && isset($this->productUomValidation[$secOfferid['offer_id']])) {

                                        if ($this->productUomValidation[$secOfferid['offer_id']] !== $this->saleUomOptions[$secOfferid['quantity_uom']]) {
                                            $raw['error'] =  "secondary_offer_id object pointer $count, quantity_uom is not matching with existing offer id sale_uom in the system";
                                            $this->products['failure'][] = $raw;
                                            continue;
                                        }
                                    }
                                }
                                if(isset($secOfferid['quantity']) && !is_int($secOfferid['quantity'])) {
                                    $raw['error'] =  "secondary_offer_id object pointer $count quantity is not integer";
                                    $this->products['failure'][] = $raw;
                                    continue;
                                }

                                $count++;
                            }
                        }
                    }

                    if (!isset($raw['inventory_basis'])) {
                        $raw['error'] = "Please add inventory_basis: " . $entityVar;
                        $this->products['failure'][] = $raw;
                        continue;
                    }
                }

                //Check uniqune variant value - Start
                $newRaw = $raw;
                $newRaw['unique_group_id'] = $product->getUniqueGroupId();

                //Checking variant is added in sheet.
                if (!$product->getAllowSimpleProductToBuy()) {
                $variants = $this->productFieldProcessor->getVariantAttributesWhileProductUpdate($product->getAttributeSetId());
                if (empty($variants)) {
                    $raw['error'] = "Please check variant attributes in var/import/product_name_rule.csv file.";
                    $this->products['failure'][] = $raw;
                    continue;
                }

                    //Variant value can not be empty
                    $emptyVariants = [];
                    foreach ($variants as $variant) {
                        if (array_key_exists($variant, $newRaw) && trim($newRaw[$variant]) == '') {
                            $emptyVariants[] = $variant;
                        }
                    }
                    if (!empty($emptyVariants)) {
                        $raw['error'] = "Empty variant values for: ";
                        $raw['error'] .= implode(",", $emptyVariants);
                        $this->products['failure'][] = $raw;
                        continue;
                    }

                    //Checking any variant field in update.
                    $fields = array_intersect($variants, array_keys($newRaw));
                    if (!empty($fields)) {
                        foreach ($variants as $variant) {
                            if (!array_key_exists($variant, $newRaw)) {
                                $newRaw[$variant] = $product->getAttributeText($variant);
                            }
                        }
                        if ($this->isDuplicateVariantInExistingProducts($newRaw)) {
                            $raw['error'] = "Same variant value is already exist in another product of unique_group_id: " . $newRaw['unique_group_id'];
                            $this->products['failure'][] = $raw;
                            continue;
                        }
                    }

                 //Update unique group id.
                  if (isset($raw['unique_group_id_update']) && $raw['unique_group_id_update'] == '1') {
                      $this->updateUniqueGroupId($product, $raw);
                      continue;
                  }

                  //Create configurable product for existing simple product.
                  if (isset($raw['configurable_recreate']) && $raw['configurable_recreate'] == '1') {
                      $this->createNewConfigurableForExistingSimple($product, $raw);
                      continue;
                  }
                }
                //Check uniqune variant value - End

                $data = [];
                $error = [];
                //Check for category exist or not.
                if ((isset($raw['department']) && $raw['department'] != '')
                        || (isset($raw['class']) && $raw['class'] != '')
                        || (isset($raw['subclass']) && $raw['subclass'] != '')) {
                    $categoryIds = $this->checkCategoryData($raw, $product->getAttributeSetId());
                    if (!isset($categoryIds['error'])) {
                        $data['category_ids'] = $categoryIds['id'];
                        $data['ibo_category_id'] = $categoryIds['ibo_category_id'];
                    } else {
                        $error[] = $categoryIds['error'];
                    }
                }

                $requiredFields = $this->productFieldProcessor->getRequiredFieldName($product->getAttributeSetId());
                $validFields = $this->productFieldProcessor->getValidAttributes($product->getAttributeSetId());
                $additionalValidFileds = $this->productFieldProcessor->getAdditionalValidFields();
                foreach ($raw as $newAttributeCode => $newAttributeValue) {
                    if (!is_array($newAttributeValue)) {
                        $newAttributeValue = trim($newAttributeValue);
                    }
                    if(isset($row['esin']) && $row['esin'] !='') {
                        if (in_array($newAttributeCode, ['esin']) || in_array($newAttributeCode, $additionalValidFileds)) {
                            continue;
                        }
                    }else {
                        if (in_array($newAttributeCode, ['sku']) || in_array($newAttributeCode, $additionalValidFileds)) {
                            continue;
                        }
                    }
                    //MAG-1369 - MRP non updatable
                    if (in_array($newAttributeCode, ['unique_group_id', 'attribute_set_id', 'attribute_set_code', 'name', 'slug', 'url_key', 'offerid', 'seller_sku_id', 'status', 'barcode', 'mrp',
                        'ebo_title', 'type_id', 'visibility', 'allow_simple_product_to_buy', 'is_lot_controlled', 'lot_control_parameters'])) {

                      $error[] = $newAttributeCode . " not allowed to update";
                        continue;
                    }

                    //Allow attribute update for mapped attributes to this attribute set.
                    if (!in_array($newAttributeCode, $validFields)) {
                        $error[] = $newAttributeCode . " is not in this attribute set";
                        continue;
                    }

                    $attribute = $this->getAttribute($newAttributeCode);
                    if (!$attribute) {
                        $error[] = $newAttributeCode . " not a valid attribute code";
                        continue;
                    }

                    //Don't allow empty value updation for required attributes.
                    if (in_array($newAttributeCode, $requiredFields) && $newAttributeValue == '') {
                        $error[] = $newAttributeCode . " is mandatory";
                        continue;
                    }

                    if ($newAttributeCode == 'tax_class_id' && $raw['tax_class_id'] != '') {
                        if ((isset($this->attibuteOptions['tax_class_id'][$raw['tax_class_id']]))) {
                            $data['tax_class_id'] = $this->attibuteOptions['tax_class_id'][$raw['tax_class_id']];
                        } else {
                            $error[] = "tax_class_id not valid";
                        }
                        continue;
                    }

                    //MAG-1668 - Start
                    if ($newAttributeCode == 'ebo_grading') {
                        $eboGrading = $this->productFieldProcessor->validateEboGradingValue($raw);
                        if (array_key_exists("error", $eboGrading)) {
                            $error[] = $newAttributeCode . " is invalid";
                            continue;
                        }
                    }

                    if ($newAttributeCode == 'replenishability') {
                        $replenishability = $this->productFieldProcessor->validateReplenishAbilityValue($raw);
                        if (array_key_exists("error", $replenishability)) {
                            $error[] = $newAttributeCode . " is invalid";
                            continue;
                        }
                    }

                    if ($newAttributeCode == 'loose_item_parent_offer_id') {
                        $looseItemData = $raw;
                        if (!array_key_exists("is_loose_item", $raw)) {
                            $looseItemData['is_loose_item'] = $product->getIsLooseItem() ?? 0;
                        }
                        if (!array_key_exists("loose_item_parent_conversion_factor", $raw)) {
                            $looseItemData['loose_item_parent_conversion_factor'] = $product->getLooseItemParentConversionFactor();
                        }
                        $looseItem = $this->productFieldProcessor->validateLooseItem($looseItemData);
                        if (array_key_exists("error", $looseItem)) {
                            if (!in_array($looseItem['error'], $error)) {
                                $error[] = $looseItem['error'];
                            }
                            continue;
                        }
                    }

                    if ($newAttributeCode == 'loose_item_parent_conversion_factor') {
                        $looseItemData = $raw;
                        if (!array_key_exists("is_loose_item", $raw)) {
                            $looseItemData['is_loose_item'] = $product->getIsLooseItem() ?? 0;
                        }
                        if (!array_key_exists("loose_item_parent_offer_id", $raw)) {
                            $looseItemData['loose_item_parent_offer_id'] = $product->getLooseItemParentOfferId();
                        }
                        $looseItem = $this->productFieldProcessor->validateLooseItem($looseItemData);
                        if (array_key_exists("error", $looseItem)) {
                            if (!in_array($looseItem['error'], $error)) {
                                $error[] = $looseItem['error'];
                            }
                            continue;
                        }
                    }

                    if ($newAttributeCode == 'is_loose_item') {
                        $looseItem = $this->productFieldProcessor->validateLooseItem($raw);
                        if (array_key_exists("error", $looseItem)) {
                            if (!in_array($looseItem['error'], $error)) {
                                $error[] = $looseItem['error'];
                            }
                            continue;
                        } else if ($raw['is_loose_item'] == 0) {
                            $data['loose_item_parent_offer_id'] = '';
                            $data['loose_item_parent_conversion_factor'] = '';
                        }
                    }
                    //MAG-1668 - End

                    $attributeValue = $this->getAttributeValue($attribute->getFrontendInput(), $newAttributeCode, $newAttributeValue);
                    if ($attributeValue !== '') {
                        $data[$newAttributeCode] = $attributeValue;
                    } else {
                        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
                            $error[] = $newAttributeCode . " is invalid";
                        } else {
                            $data[$newAttributeCode] = $attributeValue;
                        }
                    }

                    if($newAttributeCode == 'hsn_code' && $attributeValue !== '') {
                        if(is_numeric($attributeValue)) {
                            $taxClass = $this->productFieldProcessor->getTaxClassId($attributeValue);
                            if ($taxClass) {
                                $data['tax_class_id'] = $taxClass;
                                $data['hsn_code'] = $attributeValue;
                            } else {
                                $error[] =  'Tax class dose not exist with given hsn code';
                            }
                        } else {
                            $error[] =  'hsn_code should be only number type.';
                        }
                    }
                }

                //Validate fields which are required to publish the product.
                if (!empty($requiredToPublishFileds)) {
                    foreach ($requiredToPublishFileds as $requiredToPublishFiled) {
                        if (!isset($data[$requiredToPublishFiled]) || $data[$requiredToPublishFiled] == '') {
                            if (!$product->getData($requiredToPublishFiled)) {
                                $error[] = $requiredToPublishFiled . " is required for publish.";
                            }
                        }
                    }
                }

                //Manual title update
                if ($this->getIsProductHavingManualTitle($raw)) {
                    $data['is_product_having_manual_title'] = 1;
                    $data['name'] = trim($raw['manual_title']);
                    $data['meta_title'] = trim($raw['manual_title']);
                }

                //If only image update.
                if (($product->getId() && empty($data) && empty($error))
                        && (isset($raw['primary_image_url']) || isset($raw['additional_image_url']))) {
                    $response = $this->addImages($raw, $product, true, true);
                    if ($response) {
                        if (!$product->getAllowSimpleProductToBuy()) {
                            $this->updateConfigurableProduct($product->getId(), $data, $raw);
                        }
                        $this->products['success'][] = $raw;
                        $this->products['odoo'][] = $this->productFieldProcessor->getOdooFields($raw);
                    }
                } elseif (!empty($data) && empty($error) && !isset($raw['error'])) {
                    $data['website_ids'] = [1];
                    $data['store_id'] = 0;
                    $name = $this->productName->getUpdatedProductName($product, $raw);
                    if ($name != '') {
                        $data['name'] = $name;
                    }
                    $product->addData($data);
                    $product->save();
                    $this->catalogHelper->addLog('Import data in the update handler');
                    $this->catalogHelper->updateSeldate($product->getSku(),$data);
                    $this->addImages($raw, $product, true, true);
                    if (!$product->getAllowSimpleProductToBuy()) {
                        $this->updateConfigurableProduct($product->getId(), $data, $raw, true);
                    }
                    $this->products['success'][] = $raw;
                    $this->products['odoo'][] = $this->productFieldProcessor->getOdooFields($raw);
                } else {
                    if (empty($error)) {
                        $error[] = "Skipping raw as all other fields are empty.";
                    }
                    $raw['additional_error'] = "Invalid fields: ";
                    $raw['additional_error'] .= implode(',', $error);
                    $this->products['failure'][] = $raw;
                }
            } catch (\Exception $e) {
                if(isset($raw['esin']) && $raw['esin'] !='') {
                    $entityVal = $raw['esin'];
                }else { $entityVal = $raw['sku'];}
                $this->productFieldProcessor->log("There is some issue for : " . $entityVal . ", error=>" . $e->getMessage());
                $raw['error'] = $e->getMessage();
                $this->products['failure'][] = $raw;
            }
        }
    }

    /**
     * Update unique group id of given SKUs.
     *
     * @param type $product
     * @param type $raw
     * @return type
     */
    public function updateUniqueGroupId($product, $raw)
    {
        if (!isset($raw['unique_group_id']) || $raw['unique_group_id'] == "") {
            $raw['error'] = "unique_group_id is mandatory.";
            $this->products['failure'][] = $raw;
            return;
        }
        try {
            $product->setUniqueGroupId($raw['unique_group_id']);
            $product->setStoreId(0);
            $product->save();

            $configProductId = $this->productFieldProcessor->getConfigurableProductId($product->getId());
            if ($configProductId) {
                $configProduct = $this->productFactory->create()->load($configProductId);
                $configProduct->setUniqueGroupId($raw['unique_group_id'])
                    ->setStoreId(0)
                    ->save();
            }
            $this->products['success'][] = $raw;
            return;
        } catch (\Exception $ex) {
            $raw['error'] = $ex->getMessage();
            $this->products['failure'][] = $raw;
            return;
        }
    }

    /**
     * Create new configurable product for existing simple product.
     *
     * @param type $product
     * @param type $raw
     * @return type
     */
    public function createNewConfigurableForExistingSimple($product, $raw)
    {
        $configProductId = $this->productFieldProcessor->getConfigurableProductId($product->getId());
        if ($configProductId) {
            $raw['error'] = "This simple product is already associated with another configurable product.";
            $this->products['failure'][] = $raw;
            return;
        }

        if (!isset($raw['unique_group_id']) || $raw['unique_group_id'] == "") {
            $raw['error'] = "unique_group_id is mandatory.";
            $this->products['failure'][] = $raw;
            return;
        }

        $simpleId = $this->assignVarient($product->getId(), $raw['unique_group_id']);
        if (!$simpleId) {
            $this->products['success'][] = $raw;
            return;
        }

        try {
            $productData = [];
            $productData['attribute_set_id'] = $product->getAttributeSetId();
            $productData['sku'] = $product->getSku();
            $productData['category_ids'] = $product->getCategoryIds();
            foreach ($product->getData() as $attributeCode => $value) {
                if (in_array($attributeCode, ['name', 'entity_id', 'entity_id', 'store_id', 'created_in', 'updated_in', 'created_at', 'updated_at', 'type_id', 'status', 'visibility', 'quantity_and_stock_status', 'options', 'extension_attributes', 'tier_price_changed', 'is_salable', 'attribute_set_id', 'offerid', 'seller_sku_id', 'slug', 'esin'])) {
                    //Do not allow to add above attributes of simple product into conigurable product.
                    continue;
                }

                try {
                    $attribute = $this->getAttribute($attributeCode);
                } catch (\Exception $ex) {
                    continue;
                }

                if (!$attribute || in_array($attributeCode, ['sku'])) {
                    continue;
                }

                //Get product attribute label
                if ($attribute->getFrontendInput() == 'select') {
                    if ($attributeCode == 'brand_Id') {
                        $brandName = $product->getAttributeText('brand_Id');
                        $brandOptions = array_flip($this->productFieldProcessor->getBrandNameOptions());
                        $productData[$attributeCode] = (isset($brandOptions[$brandName])) ? $brandOptions[$brandName] : '';
                    } else {
                        $text = $product->getAttributeText($attributeCode);
                        $productData[$attributeCode] = (is_object($text)) ? (string) $text : $text;
                    }
                } elseif ($attribute->getFrontendInput() == 'multiselect') {
                    $options = $product->getAttributeText($attributeCode);
                    if (is_array($options)) {
                        $productData[$attributeCode] = implode(",", $options);
                    } else {
                        $productData[$attributeCode] = $options;
                    }
                } elseif ($attribute->getFrontendInput() == 'boolean') {
                    $productData[$attributeCode] = ($product->getData($attributeCode)) ? "Yes" : "No";
                } else {
                    $productData[$attributeCode] = $product->getData($attributeCode);
                }
            }

            $productData['attribute_set_code'] = $this->productFieldProcessor->getAttributeSetById($product->getAttributeSetId())->getAttributeSetName();
            $this->loadConfigFields($productData['attribute_set_code']);
            $productData['status'] = 1;
            $this->createConfigurableProduct([$product->getId()], $productData, $product->getId());
            $this->products['success'][] = $raw;
        } catch (\Exception $ex) {
            $raw['error'] = "Error while creating configurable product., Error: " . $ex->getMessage();
            $this->products['failure'][] = $raw;
            return false;
        }
    }

    /**
     * Update configurable product details while updating simple product.
     *
     * @param type $simpleProductId
     * @param int $data
     * @param type $raw
     * @param type $dataUpdate
     * @return type
     */
    public function updateConfigurableProduct($simpleProductId, $data, $raw, $dataUpdate = false)
    {
        $configProduct = $this->getConfigurableProductByChildId($simpleProductId);
        if (!$configProduct) {
            return;
        }

        $childProducts = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
        $childs = [];
        foreach ($childProducts as $childProduct) {
            $childs[] = $childProduct->getId();
        }

        //If simple product is first child of configurable product then update config product.
        if (min($childs) == $simpleProductId) {
            $this->addImages($raw, $configProduct, false, true);
            if ($dataUpdate) {
                $data['store_id'] = 0;
                $categorieIds = [];
                if (isset($data['category_ids']) && !empty($data['category_ids'])) {
                    $categorieIds = $data['category_ids'];
                    unset($data['category_ids']);
                }
                $configProduct->addData($data);
                $configProduct->save();

                //Save product categories.
                if (!empty($categorieIds)) {
                    $this->categoryLinkManagement->assignProductToCategories(
                        $configProduct->getSku(),
                        $categorieIds
                    );
                }
            }
        }
    }

    /**
     * Get configurable product by simple product id.
     *
     * @param type $productId
     * @return boolean
     */
    public function getConfigurableProductByChildId($productId)
    {
        $configProductId = $this->productFieldProcessor->getConfigurableProductId($productId);
        if ($configProductId) {
            return $this->productFactory->create()->load($configProductId);
        }
        return false;
    }

    /**
     * Get category data if column of department, class or subclass exist in the csv raw.
     *
     * @param type $raw
     * @param type $attributeSetId
     * @return array
     */
    private function checkCategoryData($raw, $attributeSetId)
    {
        if (isset($raw['department']) && $raw['department'] != ''
                && isset($raw['class']) && $raw['class'] != ''
                && isset($raw['subclass']) && $raw['subclass'] != '') {
            return $this->productFieldProcessor->getCategoryIds($raw, $attributeSetId);
        }
        return ['error' => 'Please pass valid department, class & subclass'];
    }

    /**
     * Add product images.
     *
     * @param type $data
     * @param type $product
     * @param type $isSimpleProduct
     * @param type $productUpdate
     */
    private function addImages($data, $product, $isSimpleProduct, $productUpdate)
    {
        if (!array_key_exists('primary_image_url', $data) && !array_key_exists('additional_image_url', $data)) {
            return false;
        }

        //Primary image is mandatory
        if (!isset($data['primary_image_url']) || trim($data['primary_image_url']) == '') {
            if ($isSimpleProduct && $productUpdate) {
                $imageData['sku'] = $data['sku'];
                $imageData['primary_image_url'] = (isset($data['primary_image_url'])) ? $data['primary_image_url'] : '';
                $imageData['additional_image_url'] = (isset($data['additional_image_url'])) ? $data['additional_image_url'] : '';
                $imageData['error'] = "Please add primary image.";
                $this->products['failure'][] = $imageData;
            }
            return false;
        }

        $baseImagePath = $this->directoryList->getPath('media') . "/import/";
        try {
            $isValid = true;

            //Validate primary image
            $baseimage = '';
            if (isset($data['primary_image_url']) && trim($data['primary_image_url']) != '') {
                $response = json_decode($this->productImportApiHelper->send($data['primary_image_url']), true);
                $baseimage = $baseImagePath.pathinfo(trim($response['image_url']))['basename'];
                $imageUrl = $this->productImportApiHelper->getBaseUrl() . $response['image_url'] . "?q=" . $this->getTimeStamp() ;
                file_put_contents($baseimage, file_get_contents( $imageUrl));
                if ($this->_fileDriver->isExists(trim($baseimage)) === false) {
                    $isValid = false;
                }
            }

            //Validate additional images
            $additionalImage = '';
            $additionalImageList = [];
            if (isset($data['additional_image_url']) && trim($data['additional_image_url']) != '') {
                foreach (explode(",", trim($data['additional_image_url'])) as $additionalImage) {
                    if (trim($additionalImage) == '') {
                        continue;
                    }
                    $response = json_decode($this->productImportApiHelper->send($additionalImage), true);
                    $image = $baseImagePath.pathinfo(trim($response['image_url']))['basename'];
                    $imageUrl = $this->productImportApiHelper->getBaseUrl() . $response['image_url'] . "?q=" . $this->getTimeStamp() ;
                    file_put_contents($image, file_get_contents( $imageUrl));
                    $newAdditionalImg = $baseImagePath . basename( $response['image_url']);
                    $additionalImageList[] = $newAdditionalImg;
                    if ($this->_fileDriver->isExists(trim($newAdditionalImg)) === false) {
                        $isValid = false;
                    }
                }
            }

            //If primary & additional images are valid then delete and import images.
            if ($isValid === true) {
                // Remove all images first if image update
                if ($productUpdate) {
                    $_product = $this->productFactory->create()->load($product->getId());
                    $gallery = $_product->getMediaGalleryImages();
                    foreach ($gallery as $image) {
                        $this->imageProcessor->removeImage($_product,$image->getFile());
                    }
                    $_product->setStoreId(0);
                    $_product->save();
                }

                //Create primary images
                if (file_exists($baseimage)) {
                    $img = $baseImagePath.$product->getSku().'_image_'.pathinfo(trim($baseimage))['basename'];
                    file_put_contents($img, file_get_contents($baseimage));
                }

                $product->setMediaGallery(['images' => [], 'values' => []]);
                $product->addImageToMediaGallery($baseimage, ['image', 'small_image', 'thumbnail'], true, false);

                //Create additional images
                if (isset($data['additional_image_url']) && $data['additional_image_url'] != '') {
                    $j=0;
                    foreach ($additionalImageList as $additionalImage) {
                        if (trim($additionalImage) == '') {
                            continue;
                        }

                        $newAdditionalImg = $baseImagePath.pathinfo(trim($additionalImage))['basename'];
                        $product->addImageToMediaGallery($newAdditionalImg, 'media_image', true, false);
                        $j++;
                    }
                }

                $product->setStoreId(0);
                $product->save();
                return true;
            } else {
                if ($isSimpleProduct) {
                    $imageData['sku'] = $data['sku'];
                    if (isset($data['primary_image_url'])) {
                        $imageData['primary_image_url'] = $data['primary_image_url'];
                    }
                    if (isset($data['additional_image_url'])) {
                        $imageData['additional_image_url'] = $data['additional_image_url'];
                    }
                    $imageData['error'] = "Please add valid image.";
                    $this->products['failure'][] = $imageData;
                }
            }
        } catch (\Exception $ex) {
            if ($isSimpleProduct) {
                $imageData['sku'] = $data['sku'];
                if (isset($data['primary_image_url'])) {
                    $imageData['primary_image_url'] = $data['primary_image_url'];
                }
                if (isset($data['additional_image_url'])) {
                    $imageData['additional_image_url'] = $data['additional_image_url'];
                }
                $imageData['error'] = "Image upload error: " . $ex->getMessage();
                $this->products['failure'][] = $imageData;
            }
            $this->productFieldProcessor->log("image save error:" . $ex->getMessage());
        }
        return false;
    }

    /**
     * Set product stock
     *
     * @param type $sku
     * @param type $qty
     * @param type $isInStock
     * @return boolean
     */
    private function setStock($sku, $qty, $isInStock)
    {
        try {
            $stockItem = $this->stockRepository->getStockItemBySku($sku);
            if ($stockItem->getQty() != $qty) {
                $stockItem->setQty($qty);
                $stockItem->setIsInStock($isInStock);
                $this->stockRepository->updateStockItemBySku($sku, $stockItem);
            }
        } catch (\Exception $e) {
            $this->productFieldProcessor->log($sku . ": Stock save error=>" . $e->getMessage());
            return false;
        }
    }

    /**
     * Set special price to product.
     *
     * @param type $sku
     * @param type $price
     * @return boolean
     */
    private function setSpecialPrice($sku, $price)
    {
        try {
            $prices[] = $this->specialPriceFactory->create()
                ->setSku($sku)
                ->setStoreId(0)
                ->setPrice($price);
            $this->specialPrice->update($prices);
        } catch (\Exception $e) {
            $this->productFieldProcessor->log($sku . ": Special price save error=>" . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate backend url for success & failed records.
     *
     * @param type $status
     * @return type
     */
    public function getBackendUrl($status)
    {
        return $this->csvProcessor->getBackendUrl($status);
    }

    /**
     * Generate SKU by Rule set
     * 1 Character + 9digits +  2digit(count/addition of 1 ,3,5,7,9 odd no)
     * Ex: 124578126->1+4+7+1+6 = 19 (odd Number position 1,3,5,7,9)
     * O+123458126+19
     */
    public function genSku()
    {
        $nineDigitRandom = $this->getAutoNumberOfferId();
        $arrayRan = str_split($nineDigitRandom);
        $additionOddPosition = $arrayRan[0] + $arrayRan[2] + $arrayRan[4] + $arrayRan[6] + $arrayRan[8];    //Addition value of Odd Number position
        if ($additionOddPosition > 9) {
            $arrayRan = str_split($additionOddPosition);
            $additionOddPosition = $arrayRan[0] + $arrayRan[1];
            if ($additionOddPosition > 9) {
                $arrayRan = str_split($additionOddPosition);
                $additionOddPosition = $arrayRan[0] + $arrayRan[1];
            }
        }
        $productSKU = $nineDigitRandom.$additionOddPosition;
        return $productSKU;
    }

    /**
     * Enter new record to 'autonumber_offerid' table and return last id.
     *
     * @return type
     */
    protected function getAutoNumberOfferId()
    {
        $tableName = $this->connection->getTableName('autonumber_offerid');
        $data = [];
        $respose = $this->connection->insert($tableName, $data);
        if ($respose > 0) {
            $query = "SELECT id FROM {$tableName} ORDER BY id DESC LIMIT 1";
            $result = $this->connection->fetchRow($query);
            return $result['id'];
        }
        //return 0;
    }

    /**
     * Generate ESIN by Rule set
     * Offer Id Format: The offer id would be a 10 digit number, constructed in the following manner
     * 9 digit running number starting with 100000000 (allowing for a total of 899999999 Offer IDs)
     * 1 check digit which would be the addition of the 1st, 3rd, 5th, 7th and 9th digits and if it results in a 2 digit number, further addition of those 2 digits.
     */
    public function genEsin()
    {
        $length_of_string = 9;
        $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $cp = strtoupper(substr(str_shuffle($str_result), 0, $length_of_string));
        $esinVal = "E" . $cp;
        return $esinVal;
    }

    /**
     * Generate Product Name
     * brand_id from csv --> "orient_bell" hardcode it now. Here orient_bell is the dropdown value, Orient Bell is dropdown label.
     * %fields are attribute from csv sheet.
     * Rule : {eval_brand([%brand_id])} [%ebo_size] [%finish] finish [%pattern] [%class]    //explode with space, start with % is attribute
     * Ex: Orient Bell 10x10 metalic finish dotted tiles
     *
     *
     */
    public function evalTitle($rawData, $product = null)
    {
        //Check if title can be created with new functionality.
        $newTitle = $this->productName->getUpdatedProductName($product, $rawData);
        if ($newTitle) {
            return $newTitle;
        }

        //Continue with old functionality
        //Manual title START
        //if (isset($rawData['is_catalog_sales']) && $rawData['is_catalog_sales'] && isset($rawData['vendor_sku_title'])) {
        if (isset($rawData['is_catalog_sales']) && ($rawData['is_catalog_sales'] == '1' || $rawData['is_catalog_sales'] == 1 || strtolower($rawData['is_catalog_sales']) == 'yes') && isset($rawData['vendor_sku_title'])) {
            return trim($rawData['vendor_sku_title']);
        } else {
            if (isset($rawData['manual_title']) && trim($rawData['manual_title']) != '') {
                return trim($rawData['manual_title']);
            }
        }
        if (isset($rawData['sku'])) {
            $product = $this->productFactory->create()->loadByAttribute('sku', $rawData['sku']);
            if ($product && $product->getIsProductHavingManualTitle()) {
                return $product->getName();
            }
        }
        //Manual title END

        $brandId = (isset($rawData['brand_Id'])) ? trim($rawData['brand_Id']) : '';
        $brandName = $this->productFieldProcessor->getBrandNameById($brandId);

        $commonString = "finish";
        $eboSizeAtt = (isset($rawData['ebo_size'])) ? $rawData['ebo_size'] : '';
        $finishAtt = (isset($rawData['finish'])) ? $rawData['finish'] : '';
        $patternAtt = (isset($rawData['pattern'])) ? $rawData['pattern'] : '';
        $classAtt = (isset($rawData['class'])) ? $rawData['class'] : '';

        $eboBrandCollection = (isset($rawData['brand_collection'])) ? $rawData['brand_collection'] : '';
        $eboPackOf = (isset($rawData['pack_of'])) ? $rawData['pack_of'] : '';
        $eboMaterial = (isset($rawData['material'])) ? $rawData['material'] : '';
        $eboSubclass = (isset($rawData['subclass'])) ? trim($rawData['subclass']) : '';
        $subclassToCompare = strtolower($eboSubclass);
        $size = (isset($rawData['size'])) ? $rawData['size'] : '';
        $thickness = (isset($rawData['thickness'])) ? $rawData['thickness'] : '';
        $brushThickness = (isset($rawData['brush_thickness'])) ? $rawData['brush_thickness'] : '';
        $department = (isset($rawData['department'])) ? $rawData['department'] : '';
        $noOfPly = (isset($rawData['no_of_ply'])) ? $rawData['no_of_ply'] : '';
        $grade = (isset($rawData['grade'])) ? $rawData['grade'] : '';
        $certification = (isset($rawData['certification'])) ? $rawData['certification'] : '';
        $typeforBoardClass = (isset($rawData['type'])) ? $rawData['type'] : '';
        $operationType = (isset($rawData['operation_type'])) ? $rawData['operation_type'] : '';
        $weightBearingCapKg = (isset($rawData['weight_bearing_capacity_kg'])) ? $rawData['weight_bearing_capacity_kg'] : '';
        $modelNo = (isset($rawData['brand_model_number'])) ? $rawData['brand_model_number'] : '';
        $headType = (isset($rawData['head_type'])) ? $rawData['head_type'] : '';
        $brandColor = (isset($rawData['brand_color'])) ? $rawData['brand_color'] : '';

        //Hardware & tools, it will be used finish_drop and material_drop.
        $finishDrop = (isset($rawData['finish_drop'])) ? $rawData['finish_drop'] : '';
        $materialDrop = (isset($rawData['material_drop'])) ? $rawData['material_drop'] : '';
        $typeDrop = (isset($rawData['type_drop'])) ? $rawData['type_drop'] : '';
        $noOfAccessoriesSet = (isset($rawData['no_of_accessories_set'])) ? $rawData['no_of_accessories_set'] : '';
        $weight = (isset($rawData['weight'])) ? $rawData['weight'] : '';
        $grit = (isset($rawData['grit'])) ? $rawData['grit'] : '';
        $wattage = (isset($rawData['wattage'])) ? $rawData['wattage'] : '';
        $powerType = (isset($rawData['power_type'])) ? $rawData['power_type'] : '';

        $length = (isset($rawData['length'])) ? $rawData['length'] : '';
        $lengthMd = (isset($rawData['length_md'])) ? $rawData['length_md'] : '';
        $lengthMf = (isset($rawData['length_mf'])) ? $rawData['length_mf'] : '';
        $sdrScheduleMd = (isset($rawData['sdr_schedule_md'])) ? $rawData['sdr_schedule_md'] : '';
        $socketType = (isset($rawData['socket_type'])) ? $rawData['socket_type'] : '';
        $fitMd = (isset($rawData['fit_md'])) ? $rawData['fit_md'] : '';
        $pressureMd = (isset($rawData['pressure_md'])) ? $rawData['pressure_md'] : '';
        $layersMd = (isset($rawData['layers_md'])) ? $rawData['layers_md'] : '';
        $capacityMd = (isset($rawData['capacity_md'])) ? $rawData['capacity_md'] : '';
        $itemWeight = (isset($rawData['item_weight'])) ? $rawData['item_weight'] : '';
        $suitableFor = (isset($rawData['suitable_for'])) ? $rawData['suitable_for'] : '';
        $areaOfApplicationMf = (isset($rawData['area_of_application_mf'])) ? $rawData['area_of_application_mf'] : '';
        $insulatedMaterial = (isset($rawData['insulated_material'])) ? $rawData['insulated_material'] : '';
        $ratedCurrent = (isset($rawData['rated_current'])) ? $rawData['rated_current'] : '';
        $pinsMd = (isset($rawData['pins_md'])) ? $rawData['pins_md'] : '';
        $moduleMd = (isset($rawData['module_md'])) ? $rawData['module_md'] : '';
        $batteryType = (isset($rawData['battery_type'])) ? $rawData['battery_type'] : '';
        $voltage = (isset($rawData['voltage'])) ? $rawData['voltage'] : '';
        $compositionMd = (isset($rawData['composition_md'])) ? $rawData['composition_md'] : '';
        $powerConsuptionMd = (isset($rawData['power_consumption_md'])) ? $rawData['power_consumption_md'] : '';
        $sweepSizeMd = (isset($rawData['sweep_size_md'])) ? $rawData['sweep_size_md'] : '';
        $protectionMd = (isset($rawData['protection_md'])) ? $rawData['protection_md'] : '';

        $installationTypeMd = (isset($rawData['installation_type_md'])) ? $rawData['installation_type_md'] : '';
        $typeOfLidMd = (isset($rawData['type_of_lid_md'])) ? $rawData['type_of_lid_md'] : '';
        $exposedTrimMaterialMd = (isset($rawData['exposed_trim_material_md'])) ? $rawData['exposed_trim_material_md'] : '';
        $flushingTypeMd = (isset($rawData['flushing_type_md'])) ? $rawData['flushing_type_md'] : '';
        $phaseMd = (isset($rawData['phase_md'])) ? $rawData['phase_md'] : '';
        $powerRatingMd = (isset($rawData['power_rating_md'])) ? $rawData['power_rating_md'] : '';
        $numberOfStagesMd = (isset($rawData['number_of_stages_md'])) ? $rawData['number_of_stages_md'] : '';
        $styleMd = (isset($rawData['style_md'])) ? $rawData['style_md'] : '';
        $finishCode = (isset($rawData['finish_code'])) ? $rawData['finish_code'] : '';
        $powerMd = (isset($rawData['power_md'])) ? $rawData['power_md'] : '';
        $stepMd = (isset($rawData['step_md'])) ? $rawData['step_md'] : '';

        $productName = '';

        /* Title Logic Changed based on the new rules provided by Ebo Team - By mohit*/
        if (isset($rawData['attribute_set_code'])) {
            if ($rawData['attribute_set_code'] == "tiles_class") {
                if (in_array($subclassToCompare, ['ceramic wall tiles', 'ceramic floor tiles', 'outdoor ceramic floor tile', 'outdoor ceramic wall tile', 'vitrified wall tile', 'vitrified floor tile', 'outdoor vitrified floor tile', 'outdoor vitrified wall tile'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $eboSizeAtt . " " . $materialDrop . " Tile";
                } else {
                    $productName = $brandName . " " . $eboBrandCollection . " [Pack of " . $eboPackOf . "] " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "adhesives_class") {
                $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "plywood-and-blockboard_class") {
                if (in_array($subclassToCompare, ['plywood', 'blockboards', 'blockboard'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $grade . " " . $eboSubclass;
                } else {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $certification . " " . $grade . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "engineered-board_class") {
                $productName = $brandName . " " . $eboBrandCollection . " " . $typeforBoardClass . " " . $eboSizeAtt . " " . $grade . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "composite-board_class") {
                $productName = $brandName . " " . $eboBrandCollection . " " . $typeforBoardClass . " " . $eboSizeAtt . " " . $grade . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "laminates_class") {
                $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $brandColor . " " . $finishCode . " " . $thickness . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "edgebands_class") {
                $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " ". $brandColor . " " . $finishAtt . " " . $eboSizeAtt . " " . $length . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "class_cabinet_hinges") {
                $productName = $brandName . " " . $typeDrop . " " . $operationType . " " . $eboSubclass;
            } elseif ($rawData['attribute_set_code'] == "class_cabinet_hardware") {
                if ($subclassToCompare == 'lift up') {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $weightBearingCapKg . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'cabinet knob') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'drawer slide') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $operationType . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'cabinet handle') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'bracket') {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'drawer lock') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "class_door_hardware") {
                if ($subclassToCompare == 'door aldrop/latch') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'cylindrical lock') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'tower bolt') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'lever handle') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'door lock') {
                    $productName = $brandName . " " . $modelNo . " " . $eboBrandCollection . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'door closer') {
                    $productName = $brandName . " " . $modelNo . " " . $weightBearingCapKg . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'door hinge') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'door handle') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'door accessory') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $typeDrop;
                } elseif ($subclassToCompare == 'knobs & tubular lock') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $typeDrop;
                } elseif ($subclassToCompare == 'door stopper') {
                    $productName = $brandName . " " . $modelNo . " " . $brandColor . " " . $materialDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_kitchen_hardware") {
                if ($subclassToCompare == 'tall unit') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass . " " . $typeDrop;
                } elseif ($subclassToCompare == 'roller shutter') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'drawer organiser') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $materialDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'drawer system') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $operationType . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'corner unit') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $materialDrop . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'bottle pull out') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'kitchen accessory') {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $materialDrop . " " . $typeDrop;
                } elseif ($subclassToCompare == 'profile') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "class_fasteners") {
                if ($subclassToCompare == 'screw') {
                    $productName = $brandName . " " . $materialDrop . " " . $headType . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                } elseif ($subclassToCompare == 'nut') {
                    $productName = $brandName . " " . $materialDrop . " " . $typeDrop . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                } elseif ($subclassToCompare == 'bolt') {
                    $productName = $brandName . " " . $materialDrop . " " . $typeDrop . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                } elseif ($subclassToCompare == 'washer') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSubclass . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                } elseif ($subclassToCompare == 'nail') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                } elseif ($subclassToCompare == 'dowel') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass . " - " . $eboPackOf;
                }
            } elseif ($rawData['attribute_set_code'] == "class_wardrobe_sliding_fitting") {
                if (in_array($subclassToCompare, ['roller set', 'top track system', 'bottom track system', 'wardrobe sliding system'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'wardrobe fitting') {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_others_hardware") {
                if (in_array($subclassToCompare, ['gate hook', 'basket'])) {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $materialDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'wardobe accessory') {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['floor spring', 'glass hardware'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['top patch', 'top pivot for top patch'])) {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['shower fitting', 'profile connector', 'glass & plate rack', 'gola connector'])) {
                    $productName = $brandName . " " . $modelNo . " " . $materialDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'bracket') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'lock body') {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $typeDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'glass door accessory') {
                    $productName = $brandName . " " . $materialDrop . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'bed fitting') {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'minifix') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'dish rack') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'er dowel') {
                    $productName = $brandName . " " . $typeDrop . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'skirting') {
                    $productName = $brandName . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif ($subclassToCompare == 'spacer') {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $finishDrop . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['drawer mat', 'magnetic catcher', 'shelf support'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $materialDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_hand_tools") {
                if (in_array($subclassToCompare, ['saw', 'hand saw'])) {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['hand tool set'])) {
                    $productName = $brandName . " " . $eboSubclass . " " . $noOfAccessoriesSet;
                } elseif (in_array($subclassToCompare, ['chisel', 'clamps', 'clamps & vice', 'screwdriver'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['hammer'])) {
                    $productName = $brandName . " " . $modelNo . " " . $weight . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['pliers & pincer', 'measuring & layout tool', 'cutting tool', 'files & rasp', 'tools storage & organizers', 'multitools & accessory'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['spanners & wrench', 'socket & socket set'])) {
                    //no. of pcs has been removed
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['allen key'])) {
                    //no. of pcs has been removed
                    $productName = $brandName . " " . $modelNo . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['scissor', 'wrecking & pry bar'])) {
                    //no. of pcs has been removed
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['bricklaying tool', 'hand plane'])) {
                    $productName = $brandName . " " . $modelNo . " " . $eboSizeAtt . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['soldering equipment'])) {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "class_power_tool_accessories") {
                if (in_array($subclassToCompare, ['drill bit', 'saw blade', 'grinding disc', 'cutting disc', 'planer blade'])) {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['flap disc', 'polisher accessory', 'router bit set', 'coated abrasive'])) {
                    $productName = $brandName . " " . $modelNo . " " . $grit . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_other_power_tools") {
                if (in_array($subclassToCompare, ['polisher', 'air blower', 'glue gun', 'heat gun', 'electric mixer', 'trimmer'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['power tool kit'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $typeDrop . " " . $noOfAccessoriesSet;
                } elseif (in_array($subclassToCompare, ['power pressure washer'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['drill driver', 'impact driver', 'electric screwdriver'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $powerType . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['impact drill', 'demolition drill', 'rotary drill', 'hammer drill', 'angle grinder', 'bench grinder', 'tile cutter', 'circular saw', 'planer', 'router', 'chop saw', 'miter saw', 'jig saw', 'reciprocating saw', 'sander'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_saw_tools") {
                if (in_array($subclassToCompare, ['saw'])) {
                    $productName = $brandName . " " . $modelNo . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "class_hand_tools_set") {
                if (in_array($subclassToCompare, ['hand tool set'])) {
                    $productName = $brandName . " " . $eboSubclass . " " . $noOfAccessoriesSet;
                }
            } elseif ($rawData['attribute_set_code'] == "class_drills") {
                if (in_array($subclassToCompare, ['impact drill', 'demolition drill', 'rotary drill', 'hammer drill'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['drill driver', 'impact driver', 'electric screwdriver'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $powerType . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_cutting_power_tools") {
                if (in_array($subclassToCompare, ['angle grinder', 'bench grinder', 'tile cutter', 'circular saw', 'planer', 'router', 'chop saw', 'miter saw', 'jig saw', 'reciprocating saw', 'sander'])) {
                    $productName = $brandName . " " . $modelNo . " " . $wattage . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_tiles_tools") {
                if (in_array($subclassToCompare, ['tile tools'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['tile accessories'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $brandColor . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "class_tiles_accessories") {
                if (in_array($subclassToCompare, ['tile adhesive', 'tile grout'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $brandColor . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['waterproofing', 'tile clean & care'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $typeDrop;
                }
            } elseif ($rawData['attribute_set_code'] == "veneer_class") {
                if (in_array($subclassToCompare, ['natural veneer', 'recon veneer'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_pipes") {
                if (in_array($subclassToCompare, ['water supply pipe', 'water pipe'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $sdrScheduleMd . " " . $materialDrop . " " . $lengthMd . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['drainage pipe', 'swr pipe'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $typeDrop . " " . $socketType . " " . $fitMd . " " . $lengthMd . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['agriculture pipe'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $pressureMd . " " . $lengthMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_fittings") {
                if (in_array($subclassToCompare, ['water supply fitting', 'water pipe fitting'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $sdrScheduleMd . " " . $materialDrop . " " . $typeDrop;
                } elseif (in_array($subclassToCompare, ['drainage fitting', 'swr fitting'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $typeDrop . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['agriculture fitting'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $pressureMd . " " . $typeDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_tanks") {
                if (in_array($subclassToCompare, ['overhead tank'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $layersMd . " " . $brandColor . " " . $capacityMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "solvent cements") {
                if (in_array($subclassToCompare, ['natural veneer', 'recon veneer'])) {
                    $productName = $brandName . " " . $itemWeight . " " . $suitableFor;
                }
            } elseif ($rawData['attribute_set_code'] == "class_interior_exterior_paint_color") {
                    if (in_array($subclassToCompare, ['colour emulsion (ready shade)','colour distemper (ready shade)','colour lustre (ready shade)','interior emulsion - colour','interior distemper - colour','exterior emulsion - colour','interior lustre - colour'])) {
                        $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $brandColor . " " . $eboSubclass;
                    } elseif (in_array($subclassToCompare, ['interior texture','exterior texture'])) {
                        $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $eboSizeAtt . " " . $eboSubclass;
                    }
            } elseif ($rawData["attribute_set_code"] == "class_interior_exterior_paint_base") {
                if (in_array($subclassToCompare, ['base emulsion (for tinting)','base distemper (for tinting)','base lustre (for tinting)','exterior emulsion - base','interior emulsion - base','interior distemper - base','interior lustre - base'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $modelNo . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_metal_paints_enamel") {
                if (in_array($subclassToCompare, ["enamel"])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData["attribute_set_code"] == "class_undercoats_primers") {
                if (in_array($subclassToCompare, ["metal primer", "wall primer", "wood primer"])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $eboSubclass;
                }
            } elseif ($rawData["attribute_set_code"] == "class_applicators_roller") {
                if (in_array($subclassToCompare, ["roller"])) {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $eboBrandCollection . " " . $areaOfApplicationMf . " " .$eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == "class_applicators_brush") {
                if (in_array($subclassToCompare, ["brush"])) {
                    $productName = $brandName . " " . $eboSizeAtt . " " . $modelNo . " " . $brushThickness . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_wire_cable_low_tension_wire') {
                if (in_array($subclassToCompare, ['low tension wire'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $eboSizeAtt . " " . $brandColor . " " . $lengthMd . " " . $insulatedMaterial . " Insulated Wire";
                }
            } elseif ($rawData['attribute_set_code'] == 'class_electrical_switch') {
                if (in_array($subclassToCompare, ['switch'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $typeDrop . " " . $ratedCurrent . " Modular " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_electrical_electrical_socket') {
                if (in_array($subclassToCompare, ['electrical socket'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $pinsMd . " " . $ratedCurrent . " " . $moduleMd . " Socket";
                }
            } elseif ($rawData['attribute_set_code'] == 'class_electrical_switch_communication_socket') {
                if (in_array($subclassToCompare, ['communication socket'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $moduleMd . " " . $socketType;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_battery_torches_battery') {
                if (in_array($subclassToCompare, ['battery'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $batteryType . " " . $voltage . " Pack of " . $eboPackOf . " " . $compositionMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_light_bulbs_led_night_filament_bulb') {
                if (in_array($subclassToCompare, ['led bulb'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd . " " . $socketType . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_light_bulbs_Led_batten_tubelight') {
                if (in_array($subclassToCompare, ['led batten'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd  . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_ceiling_light_panel_down_light') {
                if (in_array($subclassToCompare, ['panel light'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd  . " " . $brandColor . " " . $eboSubclass;
                } elseif(in_array($subclassToCompare, ['downlighter', 'led downlighter'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd  . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_ceiling_light_spot_cob_light') {
                if (in_array($subclassToCompare, ['spotlight', 'led spotlight'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd  . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_light_bulbs_led_night_filament_bulb') {
                if (in_array($subclassToCompare, ['night bulb'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd . " " . $socketType . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_fan_ceiling_fan') {
                if (in_array($subclassToCompare, ['ceiling fan'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $sweepSizeMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_pedestal_table_wall_fan') {
                if (in_array($subclassToCompare, ['pedestal fan', 'table fan', 'wall fan'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $sweepSizeMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_fans_exhaust_fan') {
                if (in_array($subclassToCompare, ['exhaust fan'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $sweepSizeMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_led_strip_rope_light') {
                if (in_array($subclassToCompare, ['led strip light'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd . " " . $brandColor . " " . $lengthMf . " " . $protectionMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_od_wall_gate_lamppost_light') {
                if (in_array($subclassToCompare, ['wall light'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $powerConsuptionMd . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_electrical_switch_board_plate') {
                if (in_array($subclassToCompare, ['switch board plate', 'switch board plate and frame'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $moduleMd . " " . $brandColor . " " . $finishDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_switch_socket_blank_plate_cover') {
                if (in_array($subclassToCompare, ['blank plate cover'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $moduleMd . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_one_piece_two_piece_toilet') {
                if (in_array($subclassToCompare, ['two piece toilet'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $installationTypeMd . " " . $brandColor . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_toilets_indian_toilet_urinal') {
                if (in_array($subclassToCompare, ['indian toilet (iwc)'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $brandColor . " " . $eboSizeAtt . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_toilet_seat_cover') {
                if (in_array($subclassToCompare, ['toilet seat cover'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $typeOfLidMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_toilet_flush_tank') {
                if (in_array($subclassToCompare, ['flush tank'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $capacityMd . " " . $typeDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_toilet_flush_plate_flush_valve') {
                if (in_array($subclassToCompare, ['flush plate'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['flush valve'])) {
                    $productName = $brandName . " " . $modelNo . " " . $exposedTrimMaterialMd . " " . $eboSizeAtt . " " . $flushingTypeMd;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_pumps') {
                if (in_array($subclassToCompare, ['centrifugal pump'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $phaseMd . " " . $powerRatingMd . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['borewell submersible pump'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $phaseMd . " " . $numberOfStagesMd . " " . $powerRatingMd . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['openwell submersible pump'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $phaseMd . " " . $powerRatingMd . " " . $eboSubclass;
                } elseif (in_array($subclassToCompare, ['booster pump'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $phaseMd . " " . $powerRatingMd . " " . $capacityMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_cement_solvent') {
                if (in_array($subclassToCompare, ['solvent cement'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $itemWeight . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_plumbing_acc_flange') {
                if (in_array($subclassToCompare, ['flange'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $styleMd . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_plumbing_acc_connection_pipe') {
                if (in_array($subclassToCompare, ['connection pipe'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $materialDrop . " " . $eboSizeAtt . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_plumbing_acc_waste_pipe') {
                if (in_array($subclassToCompare, ['waste pipe'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $modelNo . " " . $materialDrop . " " . $eboSubclass;
                }
            } elseif ($rawData['attribute_set_code'] == 'class_electrical_fan_regulator') {
                if (in_array($subclassToCompare, ['fan regulator'])) {
                    $productName = $brandName . " " . $eboBrandCollection . " " . $brandColor . " " . $powerMd . " " . $moduleMd . " " . $stepMd . " " . $typeDrop . " " . $eboSubclass;
                }
            }
        }

        if (trim($productName) == '') {
            if (isset($rawData['vendor_sku_title']) && $rawData['vendor_sku_title'] != '') {
                $productName = $rawData['vendor_sku_title'];
            } else {
                $productName = $brandName . " " . $eboSizeAtt . " " . $finishAtt . " " . $commonString . " " . $patternAtt . " " . $classAtt;
            }
        }
        $productName = str_replace("   ", " ", $productName);
        $productName = str_replace("  ", " ", $productName);
        $productName = str_replace("  ", " ", $productName);

        return trim($productName);
    }

    /**
     * Get attribute value
     *
     * @param type $frontendInput
     * @param type $newAttributeCode
     * @param type $newAttributeValue
     * @return string
     */
    public function getAttributeValue($frontendInput, $newAttributeCode, $newAttributeValue)
    {
        //Get product attribute option id from label
        if ($frontendInput == 'select') {
            return $this->getSelectValue($newAttributeCode, $newAttributeValue);
        } elseif ($frontendInput == 'multiselect') {
            return $this->getMultiselectValue($newAttributeCode, $newAttributeValue);
        } elseif ($frontendInput == 'boolean') {
            return $this->getBooleanValue($newAttributeValue);
        } else {
            return $newAttributeValue;
        }
        return "";
    }

    /**
     * Convert to required boolean value
     * Yes/YES/yes/1/True/TRUE/true to 1
     * Others to 0
     *
     * @param type $value
     * @return int
     */
    public function getBooleanValue($value)
    {
        if ($value && trim($value) != '') {
            if (in_array(strtolower(trim($value)), [1, 'true', 'yes'])) {
                return 1;
            }
        }
        return "0";
    }

    /**
     * Get select option value
     *
     * @param type $value
     * @return int
     */
    public function getSelectValue($newAttributeCode, $newAttributeValue)
    {
        if ($newAttributeCode == 'brand_Id') {
            if ($this->state->getAreaCode() != "crontab") {
                $newAttributeValue = $this->productFieldProcessor->getBrandNameById($newAttributeValue);
            }
        }

        if (isset($this->attibuteOptions[$newAttributeCode][$newAttributeValue])) {
            return $this->attibuteOptions[$newAttributeCode][$newAttributeValue];
        } else {
            if ($this->attibuteOptions[$newAttributeCode]['attribute_type']=='ebo-classification') {
                $attributeSwatchInputType = ($this->attibuteOptions[$newAttributeCode]['swatchtype'] != null) ? $this->attibuteOptions[$newAttributeCode]['swatchtype'] : null;
                $this->productFieldProcessor->createAttributeOption($newAttributeCode, $newAttributeValue, $attributeSwatchInputType);
                $this->loadAttributeOptions($newAttributeCode);
                return $this->getAttributeOptionsValue($newAttributeCode, $newAttributeValue);
            }
        }
        return "";
    }

    /**
     * Get multiselect option value
     *
     * @param type $newAttributeCode
     * @param type $newAttributeValue
     * @return string
     */
    public function getMultiselectValue($newAttributeCode, $newAttributeValue)
    {
        $options = [];
        foreach (explode(",", $newAttributeValue) as $value) {
            $selectValue = $this->getSelectValue($newAttributeCode, trim($value));
            if ($selectValue) {
                $options[] = $selectValue;
            } else {
                return "";
            }
        }
        return implode(",", $options);
    }

    /**
     * Generate Slug
     */
    public function genSlug($str)
    {
        // $res = preg_replace('/[^a-zA-Z0-9 ]/s','',$str);
        // $data = strtolower(str_replace(' ','-', trim($res)));
        $res = preg_replace('/[^.a-zA-Z0-9\/ -]/s','',trim($str));
        $data = strtolower(preg_replace("/[.\s\/]+/",'-',$res));
        $data = preg_replace("/-+/",'-',$data);

        return $data;
        //return $slug;
    }

    public function setCommonAttribute($product, $slug, $simpleProductId)
    {
        $prdURL = $slug."-".$simpleProductId;
        //$product->setOfferId($simpleProductId);
        $product->setStireId(0);
        $product->setUrl($prdURL);
        $product->save();
        return true;
    }

    /**
     * @param $tmp_name
     * @return array|type
     */
    public function getFilteredCsvData($tmp_name)
    {
        $csvData = $this->csvProcessor->getCsvData($tmp_name);

        $filteredCsvData = $this->productFieldProcessor->getFilteredCsvData($csvData);
        return $filteredCsvData;
    }

    /**
     * @return int
     */
    public function getTimeStamp(): int
    {
        $now = new \DateTime();
        return $now->getTimestamp();
    }
}
