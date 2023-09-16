<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Import;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Swatches\Helper\Media;
use Magento\Framework\Filesystem;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Embitel\ProductImport\Model\CategoryProcessor;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Api\AttributeSetRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Exception;
use \Embitel\TaxMaster\Model\GetTaxApi;

/**
 * This class is to prepare product fields.
 */
class ProductFieldProcessor extends AbstractModel
{
    const MERCHANDISING_CATEGORY_NAME = 'Merchandising Category';
    const NAVIGATION_CATEGORY_NAME = 'Navigation Category';
    const BRAND_CATEGORY_NAME = 'Brand';
    public const TABLE_NAME_EMBITEL_TAX_MASTER = 'embitel_tax_master';

    /**
     * Cron status path
     */
    const XML_PATH_PUBLISH_ATTRIBUTES = 'ebo/ebo_config/publish_fields';

    /**
     * Ebo Grading Allowed Values
     */
    const XML_PATH_EBO_GRADING_VALUES = 'ebo/ebo_product_create/ebo_grading_default_value';

    /**
     * Replenishability Allowed Values
     */
    const XML_PATH_REPLENISHABILITY_VALUES = 'ebo/ebo_product_create/replenishability_default_value';

    /**
     * Store Allowed Values
     */
    const XML_PATH_AVAILABLE_STORE_VALUES = 'ebo/ebo_product_create/current_available_stores';

    /**
     * @var CategoryFactory
     */
    protected $category;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /** @var \Magento\Framework\Filesystem  */
    protected $filesystem;

    /** @var \Magento\Swatches\Helper\Media */
    protected $swatchHelper;

    /** @var \Magento\Catalog\Model\Product\Media\Config */
    protected $productMediaConfig;

    /** @var CategoryProcessor */
    protected $categoryProcessor;

    /** @var \Magento\Framework\Filesystem\Driver\File */
    protected $driverFile;

    protected $rootCategoryName = null;

    /**
     * @var SetFactory
     */
    protected $eavConfig;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var Config
     */
    protected $setFactory;

    /**
     * @var AttributeSetCollectionFactory
     */
    protected $attributeSetCollectionFactory;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollection;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var AttributeSetRepositoryInterface
     */
    protected $attributeSetRepository;

    /**
     * @var CsvProcessor
     */
    protected $csvProcessor;

    /**
     * @var Configurable
     */
    protected $configurableModel;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $connection = null;

    protected $products = [];

    protected $categories = [];

    protected $requiredFields = [];

    protected $variantAttributes = [];

    protected $publishAttributes = [];

    protected $validAttributes = false;

    protected $brandNameOptions = [];

    protected $brandChildCategoryName = [];

    protected $attributeSetValidation = [];

    protected $eboGradingConfigValues = [];

    protected $replenishabilityConfigValues = [];

    protected $storeNameConfigValues = [];

    private array $avalibleOfferIds;
    private array $productUomValidation;
    private $saleUomOptions;

    protected $taxTableData = [];

    /**
     * @param Context $context
     * @param Registry $registry
     * @param SetFactory $setFactory
     * @param Config $eavConfig
     * @param Attribute $attribute
     * @param CategoryFactory $category
     * @param AttributeSetCollectionFactory $attributeSetCollectionFactory
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param Media $swatchHelper
     * @param Filesystem $filesystem
     * @param MediaConfig $productMediaConfig
     * @param CategoryProcessor $categoryProcessor
     * @param DriverFile $driverFile
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManagerInterface
     * @param ResourceConnection $resourceConnection
     * @param ProductCollectionFactory $productCollection
     * @param AttributeRepository $attributeRepository
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param CsvProcessor $csvProcessor
     * @param Configurable $configurableModel
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        Registry $registry,
        SetFactory $setFactory,
        Config $eavConfig,
        Attribute $attribute,
        CategoryFactory $category,
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        WebsiteRepositoryInterface $websiteRepository,
        Media $swatchHelper,
        Filesystem $filesystem,
        MediaConfig $productMediaConfig,
        CategoryProcessor $categoryProcessor,
        DriverFile $driverFile,
        DirectoryList $directoryList,
        StoreManagerInterface $storeManagerInterface,
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollection,
        AttributeRepository $attributeRepository,
        AttributeSetRepositoryInterface $attributeSetRepository,
        CsvProcessor $csvProcessor,
        Configurable $configurableModel,
        ScopeConfigInterface $scopeConfig,
        GetTaxApi $getTaxApi
    ) {
        parent::__construct($context, $registry);
        $this->setFactory = $setFactory;
        $this->eavConfig = $eavConfig;
        $this->attribute = $attribute;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->websiteRepository = $websiteRepository;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->category = $category;
        $this->filesystem = $filesystem;
        $this->swatchHelper = $swatchHelper;
        $this->productMediaConfig = $productMediaConfig;
        $this->categoryProcessor = $categoryProcessor;
        $this->driverFile = $driverFile;
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->productCollection = $productCollection;
        $this->attributeRepository = $attributeRepository;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->csvProcessor = $csvProcessor;
        $this->configurableModel = $configurableModel;
        $this->scopeConfig = $scopeConfig;
        $this->getTaxApi = $getTaxApi;
    }

    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Get some required fields.
     *
     * @return type
     */
    public function getRequiredFieldName($attributeSetId)
    {
        if (!isset($this->requiredFields[$attributeSetId])) {
            $requiredAttribute = ['mrp', 'unique_group_id'];
            $attributeCollection = $this->attribute->getCollection()
                    ->addFieldToFilter("main_table.entity_type_id", 4)
                    ->addFieldToFilter("is_required", "1");

            $jointable = 'eav_entity_attribute';
            $attributeCollection
            ->getSelect()
            ->join(
                ['ot'=>$jointable],
                "main_table.attribute_id = ot.attribute_id AND ot.attribute_set_id = ".$attributeSetId
            );

            $notRequired = ['created_at','updated_at','sku','esin','name', 'qty', 'visibility', 'is_bom',
                'production_start_date','slug','giftcard_type','links_purchased_separately',
                'links_title','price','price_type','samples_title','price_view','shipment_type','sku_type','weight_type'];
            foreach ($attributeCollection as $attribute) {
                if (!in_array($attribute->getAttributeCode(), $notRequired)) {
                    $requiredAttribute[] = $attribute->getAttributeCode();
                }
            }

            $this->requiredFields[$attributeSetId] = array_unique($requiredAttribute);
        }
        return $this->requiredFields[$attributeSetId];
    }

    /**
     * Get some required fields.
     *
     * @return type
     */
    public function getValidAttributes($attributeSetId)
    {
        $attributeCollection = $this->attribute->getCollection()
            ->addFieldToFilter("main_table.entity_type_id", 4);

        $jointable = 'eav_entity_attribute';
        $attributeCollection
        ->getSelect()
        ->join(
            ['ot'=>$jointable],
            "main_table.attribute_id = ot.attribute_id AND ot.attribute_set_id = ".$attributeSetId
        );

        $attributes = array_column($attributeCollection->getData(), 'attribute_code');
        $additionalAttributes = ['primary_image_url', 'additional_image_url', 'is_default', 'attribute_set_code', 'product_type', 'manual_title','base_offer_id','secondary_offer_id','inventory_basis'];
        $additionalAttributes = array_merge($additionalAttributes, array_keys($this->getOdooColumns()));
        return array_merge($attributes, $additionalAttributes);
    }

    /**
     * Additional valid fields which are allow to add or update.
     *
     * @return type
     */
    public function getAdditionalValidFields()
    {
        return ['primary_image_url', 'additional_image_url', 'is_default', 'attribute_set_code', 'product_type', 'manual_title'];
    }

    /**
     * Validate attribute which are required to publish.
     *
     * @return type
     */
    public function getPublishAttributes()
    {
        if (empty($this->publishAttributes)) {
            $configFields = $this->getConfig(self::XML_PATH_PUBLISH_ATTRIBUTES);
            if ($configFields) {
                $fields = explode(",", $configFields);
                $this->publishAttributes = array_combine($fields, $fields);
            }
        }
        return $this->publishAttributes;
    }

    /**
     * Filter correct and incorrect raws from CSV.
     *
     * @param type $csvData
     * @return type
     */
    public function getFilteredCsvData($csvData)
    {
        $this->validateIfTinted($csvData);
        $header = [];
        foreach ($csvData as $rowIndex => $dataRow) {
            //skip headers
            if ($rowIndex == 0) {
                $header = $dataRow;
                continue;
            }
            if(in_array('package_length_in_cm', $header) || in_array('package_height_in_cm', $header) || in_array('package_width_in_cm', $header) || in_array('package_weight_in_kg', $header) || in_array('courier_type', $header)){
                throw new LocalizedException(__('Product Package Dimension and Courier Type attributes are added in CSV. Please remove and try again.'));
            }
            $this->validateRowData($header, $dataRow);
        }

        unset($this->productUomValidation);
        unset($this->avalibleOfferIds);

        return $this->products;
    }

    /**
     * Validate CSV data
     *
     * @param type $header
     * @param type $dataRow
     * @return boolean
     */
    private function validateRowData($header, $dataRow)
    {
        $isRawValid = true;
        $data = array_combine($header, $dataRow);
        //Is Catalog Sales Login Check
        if (isset($data['is_catalog_sales'])) {
            if ($data['is_catalog_sales'] == 1) {
                if (!isset($data['allowed_channels']) || $data['allowed_channels'] != 'STORE') {
                    $data['error'] = "When is_catalog_sales value is 1, allowed_channels attribute value should be - STORE";
                    $this->products['failure'][] = $data;
                    return true;
                }
                if(!isset($data['is_returnable']) || $data['is_returnable'] != 'No') {
                    $data['error'] = "When is_catalog_sales value is 1, is_returnable attribute value should be No";
                    $this->products['failure'][] = $data;
                    return true;
                }
                if(!isset($data['pod_eligible']) || $data['pod_eligible'] != 'No') {
                    $data['error'] = "When is_catalog_sales value is 1, pod_eligible attribute value should be No";
                    $this->products['failure'][] = $data;
                    return true;
                }
            }
        }

        //Product update will have a SKU value.
        if (isset($data['sku']) && $data['sku'] != '') {
            $this->products['update'][] = $data;
            return true;
        }
        else if (isset($data['esin']) && $data['esin'] != '') {
            $this->products['update'][] = $data;
            return true;
        }

        //If attribute set is not added, skip the raw.
        if (!isset($data['attribute_set_code']) || !trim($data['attribute_set_code'])) {
            $data['error'] = "Provide attribute_set_code";
            $this->products['failure'][] = $data;
            return true;
        }

        //Check for attribute set & website
        $attributeSetId = $this->getAttributeSetId(trim($data['attribute_set_code']));
        if ($attributeSetId === null) {
            $data['error'] = "attribute_set_code is wrong.";
            $this->products['failure'][] = $data;
            return true;
        }

        //Allow only valid product_type values.
        if (isset($data['product_type'])) {
            if (!in_array(trim($data['product_type']), ['', 'simple', 'configurable'])) {
                $data['error'] = "Please add valid product_type.";
                $this->products['failure'][] = $data;
                return true;
            }
        }

        if (!$this->validAttributes) {
            $validAttributes = $this->getValidAttributes($attributeSetId);
            $validAttributes = array_unique($validAttributes);
            $validAttributes = array_combine($validAttributes, $validAttributes);
            $additionalFields = array_diff_key($data, $validAttributes);
            if (count($additionalFields) > 0) {
                throw new LocalizedException(__('Some additional attributes are added in CSV. Please remove and try again. Additional attributes are: ' . implode(", ", array_keys($additionalFields))));
            }
            $this->validAttributes = true;
        }

        //Validate attribute set in var/import/product_name_rule.csv for CONFIGURABLE products
        if ($this->isConfigurableRow($data)) {
            $validationMessage = $this->validateAttributeSetInRuleNameCsv(trim($data['attribute_set_code']), $attributeSetId);
            if ($validationMessage) {
                $data['error'] = $validationMessage;
                $this->products['failure'][] = $data;
                return true;
            }
        }

        //If require fields are not in the CSV row then skip row.
        $invalidFields = $this->getInvalidRowFields($data);
        if (!empty($invalidFields)) {
            $isRawValid = false;
        }

        /*if (!$this->isValidWebsiteId($data['website_id'])) {
            $invalidFields[] = 'attribute_set_code';
            $isRawValid = false;
        }*/

        //Check for category inside the brand category
        $brandCategoryArr = $this->getBrandCategoryChildNames();
        if (!isset($data['brand_Id']) || !in_array(trim($data['brand_Id']), $brandCategoryArr)) {
            $invalidFields[] = 'brand category';
            $isRawValid = false;
        }

        //Check for category exist or not.
        $categoryIdsData = $this->getCategoryIds($data, $attributeSetId);
        if (empty($categoryIdsData) || isset($categoryIdsData['error'])) {
            $invalidFields[] =  $categoryIdsData['error'];
            $isRawValid = false;
        }

        if (!isset($data['is_bom'])) {
            $invalidFields[] =  'Please add is_bom field.';
            $isRawValid = false;
        }

        //Get attributes which are required to publish
        $publishAttributes = $this->getPublishAttributes();
        if (!empty($publishAttributes)) {
            foreach ($publishAttributes as $publishAttribute) {
                if (!isset($data[$publishAttribute]) || $data[$publishAttribute] == '') {
                    $invalidFields[] =  $publishAttribute . ' field required for publish.';
                    $isRawValid = false;
                }
            }
        }

        // Check for Paint Fields
        if (isset($data['is_bom']) && $data['is_bom'] == 1) {
            if (!isset($data['base_offer_id']) || $data['base_offer_id'] == "") {
                $invalidFields[] =  'add base_offer_id field value';
                $isRawValid = false;
            }
            if (isset($data['base_offer_id']) && !in_array($data['base_offer_id'], $this->avalibleOfferIds) ) {
                $invalidFields[] = 'base offer id is not available in the system';
                $isRawValid = false;
            }

            if (!isset($data['secondary_offer_id']) || $data['secondary_offer_id'] == "") {
                $invalidFields[] =  'add secondary_offer_id field value';
                $isRawValid = false;
            }

            if (isset($data['secondary_offer_id'])) {
                $secondaryOfferIds = json_decode($data['secondary_offer_id'], true);
                if (is_null($secondaryOfferIds)) {
                    $invalidFields[] =  'secondary_offer_id value should be array of objects';
                    $isRawValid = false;
                }
                if (is_array($secondaryOfferIds)) {
                    $count = 1;
                    foreach ($secondaryOfferIds as $secOfferid) {
                        if (count($secOfferid) !== 3) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count, is not valid";
                            $isRawValid = false;
                        }
                        if(!isset($secOfferid['offer_id'])) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count, offer_id is not set";
                            $isRawValid = false;
                        }
                        if(!isset($secOfferid['quantity_uom'])) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count, quantity_uom is not set";
                            $isRawValid = false;
                        }
                        if(!isset($secOfferid['quantity'])) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count, quantity is not set";
                            $isRawValid = false;
                        }
                        if(isset($secOfferid['offer_id']) && !in_array($secOfferid['offer_id'], $this->avalibleOfferIds)) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count, offer_id is not available int the system";
                            $isRawValid = false;
                        }
                        if(isset($secOfferid['quantity_uom']) && in_array($secOfferid['offer_id'], $this->avalibleOfferIds)) {
                            if(!isset($this->saleUomOptions[$secOfferid['quantity_uom']])) {
                                $invalidFields[] =  "secondary_offer_id object pointer $count, quantity_uom is not available in the system";
                                $isRawValid = false;
                            }
                            if (!isset($this->productUomValidation[$secOfferid['offer_id']])) {
                                $invalidFields[] =  "secondary_offer_id object pointer $count, quantity_uom is not set for base sku or offer id in the system";
                                $isRawValid = false;
                            }
                            if(isset($this->saleUomOptions[$secOfferid['quantity_uom']]) && isset($this->productUomValidation[$secOfferid['offer_id']])) {

                                if ($this->productUomValidation[$secOfferid['offer_id']] !== $this->saleUomOptions[$secOfferid['quantity_uom']]) {
                                    $invalidFields[] =  "secondary_offer_id object pointer $count, quantity_uom is not matching with existing offer id sale_uom in the system";
                                    $isRawValid = false;
                                }
                            }
                        }
                        if(isset($secOfferid['quantity']) && !is_int($secOfferid['quantity'])) {
                            $invalidFields[] =  "secondary_offer_id object pointer $count quantity is not integer";
                            $isRawValid = false;
                        }

                        $count++;
                    }
                }
            }

            if (!isset($data['inventory_basis']) || $data['inventory_basis'] == "") {
                $invalidFields[] =  'add inventory_basis field value';
                $isRawValid = false;
            }
        }

        if(isset($data['hsn_code'])) {
            if(is_numeric($data['hsn_code'])) {
                $taxClass = $this->getTaxClassId($data['hsn_code']);
                if ($taxClass) {
                    $data['tax_class_id'] = $taxClass;
                } else {
                    $invalidFields[] =  'Tax class dose not exist with given hsn code';
                    $isRawValid = false;
                }
            } else {
                $invalidFields[] =  'hsn_code should be only number type.';
                $isRawValid = false;
            }
        }
        
        //MAG-1668 - Start
        $eboGrading = $this->validateEboGradingValue($data);
        $replenishAbility = $this->validateReplenishAbilityValue($data);
        $looseItem = $this->validateLooseItem($data);

        if (array_key_exists("error", $eboGrading)) {
            $invalidFields[] =  'ebo_grading';
            $isRawValid = false;
        }

        if (array_key_exists("error", $replenishAbility)) {
            $invalidFields[] =  'replenishability';
            $isRawValid = false;
        }

        if (array_key_exists("error", $looseItem)) {
            $invalidFields[] =  $looseItem['error'];
            $isRawValid = false;
        }
        //MAG-1668 - End

        if ($isRawValid) {
            $data['category_ids'] = $categoryIdsData['id'];
            $data['ibo_category_id'] = $categoryIdsData['ibo_category_id'];
            $data['attribute_set_id'] = $attributeSetId;
            $data['mrp'] = 0; //MAG-1369 - MRP should always 0 for new product create.
            $this->products['success'][$data['unique_group_id']][] = $data;
            return true;
        } else {
            $data['error'] = "Invalid fields: ";
            $data['error'] .= implode(',', $invalidFields);
            $this->products['failure'][] = $data;
            return false;
        }
    }

    /**
     * Get invalid CSV raws from CSV data.
     *
     * @param type $data
     * @return type
     */
    private function getInvalidRowFields($data)
    {
        $errorFields = [];
        if (!isset($data['attribute_set_code'])) {
            return ['attribute_set_code'];
        }
        $attributeSetId = $this->getAttributeSetId(trim($data['attribute_set_code']));
        if ($attributeSetId == null) {
            return ['attribute_set_code'];
        }

        //Check if any required field is missing in the CSV
        $requiredFields = $this->getRequiredFieldName($attributeSetId);

        //Add variant attributes as a required attribute list.
        if ($this->isConfigurableRow($data)) {
            foreach ($this->getVariantAttributes() as $variantAttribute) {
                $requiredFields[] = $variantAttribute;
            }
            $requiredFieldsUnique = array_unique($requiredFields);
        } else {
            $requiredFieldsUnique = $requiredFields;
        }

        $fields = array_diff_key(array_flip($requiredFieldsUnique), $data);
        if (!empty($fields)) {
            $errorFields = array_keys($fields);
        }

        //Check if value of any required field is empty in the CSV
        foreach ($requiredFieldsUnique as $requiredField) {
            if (!isset($data[$requiredField]) || $data[$requiredField] == '') {
                if (!in_array($requiredField, $errorFields)) {
                    $errorFields[] = $requiredField;
                }
            }
        }

        if (!empty($errorFields)) {
            return $errorFields;
        }
        return [];
    }

    //Get row product type to be created.
    public function isConfigurableRow($data)
    {
        if (in_array($this->getProductTypeField($data), ['', 'configurable'])) {
            return true;
        }
        return false;
    }

    //Get row product type to be created.
    public function getProductTypeField($data)
    {
        if (isset($data['product_type']) && trim($data['product_type']) != '') {
            return trim(strtolower($data['product_type']));
        }
        return '';
    }

    /**
     * Generate category name by department, class and subclass CSV column
     *
     * @param type $rawData
     * @return string
     */
    public function getCategoryName($rawData)
    {
        $rootCategoryName = $this->getRootCategoryName();
        $levelOne = (isset($rawData['department']) && trim($rawData['department']) != '') ? trim($rawData['department']) : '';
        $levelTwo = (isset($rawData['class']) && trim($rawData['class']) != '') ? trim($rawData['class']) : '';
        $levelThree = (isset($rawData['subclass']) && trim($rawData['subclass']) != '') ? trim($rawData['subclass']) : '';
        $finalCategoryName = self::MERCHANDISING_CATEGORY_NAME ."||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
        return $finalCategoryName;
    }

    /**
     * Get category id from path
     *
     * @param type $rawData
     * @param type $attributeSetId
     * @return type
     */
    public function getCategoryIds($rawData, $attributeSetId = 0)
    {
        $ids = [];
        $merchCategoryName = $this->getCategoryName($rawData);
        if (array_key_exists($merchCategoryName, $this->categories)
                && isset($this->categories[$merchCategoryName][$attributeSetId])) {
            return $this->categories[$merchCategoryName][$attributeSetId];
        }

        $id = $this->categoryProcessor->getCategoryIdByPath(trim($merchCategoryName));
        if (!$id) {
            $this->categories[$merchCategoryName][$attributeSetId]['error'] = "Please add correct merchandising department, class and subclass.";
            return $this->categories[$merchCategoryName][$attributeSetId];
        }

        $ids[] = $id;
        $merchCategory = $this->getCategory($id);
        if ($attributeSetId > 0) {
            if ($merchCategory->getAttributeSet() != $attributeSetId) {
                $this->categories[$merchCategoryName][$attributeSetId]['error'] = "Please map correct attribute set in merchandising subclass";
                return $this->categories[$merchCategoryName][$attributeSetId];
            }
        }

        $collection = $this->category->create()->getCollection()
                ->addAttributeToFilter('name', self::NAVIGATION_CATEGORY_NAME)->setPageSize(1);
        $path = '';
        if ($collection->getData()) {
            $path = $collection->getFirstItem()->getPath();
        }

        $categoryId = $merchCategory->getCategoryId();
        $catColl = $this->category->create()->getCollection()
            ->addAttributeToFilter('category_id', $categoryId)
            ->addAttributeToFilter('path', ['like' => $path."/%"]);

        if ($catColl->getSize() > 0) {
            $ids[] = $catColl->getFirstItem()->getId();
            $this->categories[$merchCategoryName][$attributeSetId]['id'] = $ids;
            $this->categories[$merchCategoryName][$attributeSetId]['ibo_category_id'] = $categoryId;
        } else {
            $this->categories[$merchCategoryName][$attributeSetId]['error'] = "Navigation subclass not found with ibo_category_id.";
        }

        return $this->categories[$merchCategoryName][$attributeSetId];
    }

    /**
     * Get category by id
     *
     * @param type $id
     * @return type
     */
    public function getCategory($id)
    {
        return $this->category->create()->load($id);
    }

    /**
     * Ebo Grading config values
     */
    public function getEboGradingConfigValues()
    {
        if (!empty($this->eboGradingConfigValues)) {
            return $this->eboGradingConfigValues;
        }

        $eboGradingValues = $this->getConfig(self::XML_PATH_EBO_GRADING_VALUES);
        if ($eboGradingValues != '') {
            $this->eboGradingConfigValues = array_map('trim', explode(",", $eboGradingValues));
        } else {
            $this->eboGradingConfigValues = [];
        }
        return $this->eboGradingConfigValues;
    }

    /**
     * Replenishability config values
     */
    public function getReplenishabilityConfigValues()
    {
        if (!empty($this->replenishabilityConfigValues)) {
            return $this->replenishabilityConfigValues;
        }

        $replenishabilityValues = $this->getConfig(self::XML_PATH_REPLENISHABILITY_VALUES);
        if ($replenishabilityValues != '') {
            $this->replenishabilityConfigValues = array_map('trim', explode(",", $replenishabilityValues));
        } else {
            $this->replenishabilityConfigValues = [];
        }
        return $this->replenishabilityConfigValues;
    }

    /**
     * Available store config values
     */
    public function getStoreNameConfigValues()
    {
        if (!empty($this->storeNameConfigValues)) {
            return $this->storeNameConfigValues;
        }

        $storeValues = $this->getConfig(self::XML_PATH_AVAILABLE_STORE_VALUES);
        if ($storeValues != '') {
            $this->storeNameConfigValues = array_map('trim', explode(",", $storeValues));
        } else {
            $this->storeNameConfigValues = [];
        }
        return $this->storeNameConfigValues;
    }

    /**
     * Validate ebo grading value as per the required formate
     *
     * @param type $data
     * @return type
     */
    public function validateEboGradingValue($data)
    {
        $stores = $this->getStoreNameConfigValues();
        if (array_key_exists("ebo_grading", $data) && $data['ebo_grading'] != '') {
            foreach (explode("|", $data['ebo_grading']) as $eboGrading) {
                $eboGradingStoreWiseValue = explode(":", $eboGrading);
                if (count($eboGradingStoreWiseValue) <= 1) {
                    return ['error' => 'ebo_grading is not valid'];
                }
                if (!in_array(trim($eboGradingStoreWiseValue[0]), $this->getStoreNameConfigValues())
                        || !in_array(trim($eboGradingStoreWiseValue[1]), $this->getEboGradingConfigValues())) {
                    return ['error' => 'ebo_grading is not valid'];
                }
                if(($key = array_search(trim($eboGradingStoreWiseValue[0]), $stores)) !== false) {
                    unset($stores[$key]);
                }
            }
        }

        if (!empty($stores)) {
            return ['error' => 'ebo_grading is not valid'];
        }

        return [];
    }

    /**
     * Validate replenish ability value as per the required formate
     *
     * @param type $data
     * @return type
     */
    public function validateReplenishAbilityValue($data)
    {
        $stores = $this->getStoreNameConfigValues();
        if (array_key_exists("replenishability", $data) && $data['replenishability'] != '') {
            foreach (explode("|", $data['replenishability']) as $replenishability) {
                $replenishStoreWiseValue = explode(":", $replenishability);
                if (count($replenishStoreWiseValue) <= 1) {
                    return ['error' => 'replenishability is not valid'];
                }
                if (!in_array(trim($replenishStoreWiseValue[0]), $this->getStoreNameConfigValues())
                        || !in_array(trim($replenishStoreWiseValue[1]), $this->getReplenishabilityConfigValues())) {
                    return ['error' => 'replenishability is not valid'];
                }
                if(($key = array_search(trim($replenishStoreWiseValue[0]), $stores)) !== false) {
                    unset($stores[$key]);
                }
            }
        }

        if (!empty($stores)) {
            return ['error' => 'replenishability is not valid'];
        }

        return [];
    }

    /**
     * Get replenish ability value as per the required formate
     *
     * @param type $data
     * @return type
     */
    public function validateLooseItem($data)
    {
        $looseParentOfferIdValid = false;
        $looseParentConversionFactor = false;
        $isLooseItem = false;
        $errorMessage = [];
        if (array_key_exists('is_loose_item', $data)
                && in_array(strtolower($data['is_loose_item']), ['true', 'yes', 1])) {
            $isLooseItem = true;
            if (isset($data['loose_item_parent_offer_id'])
                    && trim($data['loose_item_parent_offer_id']) != '') {
                $query = "SELECT entity_id FROM catalog_product_entity WHERE sku = " . trim($data['loose_item_parent_offer_id']);
                $result = $this->connection->fetchOne($query);
                if ($result != '') {
                    $looseParentOfferIdValid = true;
                }
            }
            if (isset($data['loose_item_parent_conversion_factor'])
                    && trim($data['loose_item_parent_conversion_factor']) != '') {
                if (is_numeric(trim($data['loose_item_parent_conversion_factor']))) {
                    $looseParentConversionFactor = true;
                }
            }
        } else {
            if (isset($data['loose_item_parent_offer_id'])
                    && trim($data['loose_item_parent_offer_id']) != '') {
                $errorMessage[] = "loose_item_parent_offer_id not allowed for non-loose item";
            }
            if (isset($data['loose_item_parent_conversion_factor'])
                    && trim($data['loose_item_parent_conversion_factor']) != '') {
                $errorMessage[] = "loose_item_parent_conversion_factor not allowed for non-loose item";
            }
        }

        if ($isLooseItem && !$looseParentOfferIdValid) {
            $errorMessage[] = "loose_item_parent_offer_id";
        }
        if ($isLooseItem && !$looseParentConversionFactor) {
            $errorMessage[] = "loose_item_parent_conversion_factor";
        }

        if (!empty($errorMessage)) {
            return ['error' => implode(", ", $errorMessage)];
        }

        return [];
    }

    /**
     * Get all subclass of brand category.
     *
     * @return type
     */
    public function getBrandCategoryChildNames()
    {
        if (empty($this->brandChildCategoryName)) {
            $collection = $this->category->create()->getCollection()
                ->addAttributeToFilter('name', self::BRAND_CATEGORY_NAME)->setPageSize(1);
            if ($collection->getData()) {
                $brandcategoryId = $collection->getFirstItem()->getId();
                $categoryBrandObj = $this->getCategory($brandcategoryId);
                $childrenCategories = $categoryBrandObj->getChildrenCategories();

                foreach ($childrenCategories as $cat) {
                    $categoryObj = $this->getCategory($cat->getId());
                    if ($categoryObj->getCategoryId()) {
                        $this->brandChildCategoryName[] = $categoryObj->getCategoryId();
                    }
                }
            }
        }

        return $this->brandChildCategoryName;
    }

    /**
     * Get brand names of category.
     *
     * @return type
     */
    public function getBrandNameOptions()
    {
        if (empty($this->brandNameOptions)) {
            $collection = $this->category->create()->getCollection()
                    ->addAttributeToFilter('name', self::BRAND_CATEGORY_NAME)->setPageSize(1);
            if ($collection->getData()) {
                $brandcategoryId = $collection->getFirstItem()->getId();
                $categoryBrandObj = $this->getCategory($brandcategoryId);
                $childrenCategories = $categoryBrandObj->getChildrenCategories();

                foreach ($childrenCategories as $cat) {
                    $categoryObj = $this->getCategory($cat->getId());
                    $this->brandNameOptions[$categoryObj->getCategoryId()] = $cat->getName();
                }
            }
        }
        return $this->brandNameOptions;
    }

    /**
     * Get brand name by brand id.
     *
     * @return type
     */
    public function getBrandNameById($brandId)
    {
        $brandNameOptions = $this->getBrandNameOptions();
        return (array_key_exists($brandId, $brandNameOptions)) ? $brandNameOptions[$brandId] : '';
    }

    /**
     * Check uniqueness of data
     *
     * Validation fields: Department, class, subclass, brand, model number, color
     *
     * @param type $data
     * @return boolean
     */
    public function isDataUnique($data)
    {
        $department = (isset($data['department'])) ? trim($data['department']) : '';
        $class = (isset($data['class'])) ? trim($data['class']) : '';
        $subclass = (isset($data['subclass'])) ? trim($data['subclass']) : '';
        $brandId = (isset($data['brand_Id'])) ? trim($data['brand_Id']) : '';
        $model = (isset($data['brand_model_number'])) ? trim($data['brand_model_number']) : '';
        $color = (isset($data['ebo_color'])) ? trim($data['ebo_color']) : '';

        if (!$department || !$class || !$subclass || !$brandId || !$model || !$color) {
            $this->log("Any of uniqueness check field is empty. ");
            return false;
        }

        $brandName = $this->getBrandNameById($brandId);
        $brandOptionId = $this->getOptionIdbyAttributeCodeandLabel("brand_Id", $brandName);
        $colorOptionId = $this->getOptionIdbyAttributeCodeandLabel("ebo_color", $color);

        $this->log("department: " . $department);
        $this->log("class: " . $class);
        $this->log("subclass: " . $subclass);
        $this->log("brand_Id: " . $brandId ."=>". $brandName ."=>".$brandOptionId);
        $this->log("model_number: " . $model);
        $this->log("ebo_color: " . $color ."=>" . $colorOptionId);

        if (!$colorOptionId || !$brandOptionId) {
            return false;
        }

        $productCollection = $this->getProductCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->addStoreFilter(0)
            ->addAttributeToFilter('department', ['eq' => $department])
            ->addAttributeToFilter('class', ['eq' => $class])
            ->addAttributeToFilter('subclass', ['eq' => $subclass])
            ->addAttributeToFilter('brand_Id', ['in' => [$brandOptionId]])
            ->addAttributeToFilter('brand_model_number', ['eq' => $model])
            ->addAttributeToFilter('ebo_color', ['in' => [$colorOptionId]]);
        $this->log("collection size => " . $productCollection->getSize());
        return ($productCollection->getSize() == 0) ? true : false;
    }

    /**
     * Get product collection
     *
     * @return type
     */
    public function getProductCollection()
    {
        return $this->productCollection->create();
    }

    public function getOptionIdbyAttributeCodeandLabel($attributeCode,$optionText)
    {
       $attribute = $this->attributeRepository->get($attributeCode);
       return $attribute->getSource()->getOptionId($optionText);
    }

    /**
     * Get odoo CSV columns
     *
     * @return type
     */
    public function getOdooColumns()
    {
        return [
            'sku' => 'sku',
            'vendor_id' => 'vendor_id',
            'vendor_sku_id' => 'vendor_sku_id',
            'vendor_sku_title' => 'vendor_sku_title',
            'vendor_uom' => 'vendor_uom',
            'vendor_lead_time_in_days' => 'vendor_lead_time_in_days',
            'vendor_product_mrp' => 'vendor_product_mrp',
            'vendor_list_price' => 'vendor_list_price',
            'vendor_tot_price_type' => 'vendor_tot_price_type',
            'vendor_tot_margin_pct' => 'vendor_tot_margin_pct',
            'vendor_moq' => 'vendor_moq',
            'vendor_hsn_code' => 'vendor_hsn_code',
            'vendor_purchase_mode' => 'vendor_purchase_mode'
        ];
    }

    /**
     * Get odoo fields from csv raw data.
     *
     * @param type $rawData
     * @return type
     */
    public function getOdooFields($rawData)
    {
        return [
            'sku' => (isset($rawData['sku'])) ? $rawData['sku'] : '',
            'vendor_id' => (isset($rawData['vendor_id'])) ? $rawData['vendor_id'] : '',
            'vendor_sku_id' => (isset($rawData['vendor_sku_id'])) ? $rawData['vendor_sku_id'] : '',
            'vendor_sku_title' => (isset($rawData['vendor_sku_title'])) ? $rawData['vendor_sku_title'] : '',
            'vendor_uom' => (isset($rawData['vendor_uom'])) ? $rawData['vendor_uom'] : '',
            'vendor_lead_time_in_days' => (isset($rawData['vendor_lead_time_in_days'])) ? $rawData['vendor_lead_time_in_days'] : '',
            'vendor_product_mrp' => (isset($rawData['vendor_product_mrp'])) ? $rawData['vendor_product_mrp'] : '',
            'vendor_list_price' => (isset($rawData['vendor_list_price'])) ? $rawData['vendor_list_price'] : '',
            'vendor_tot_price_type' => (isset($rawData['vendor_tot_price_type'])) ? $rawData['vendor_tot_price_type'] : '',
            'vendor_tot_margin_pct' => (isset($rawData['vendor_tot_margin_pct'])) ? $rawData['vendor_tot_margin_pct'] : '',
            'vendor_moq' => (isset($rawData['vendor_moq'])) ? $rawData['vendor_moq'] : '',
            'vendor_hsn_code' => (isset($rawData['vendor_hsn_code'])) ? $rawData['vendor_hsn_code'] : '',
            'vendor_purchase_mode' => (isset($rawData['vendor_purchase_mode'])) ? $rawData['vendor_purchase_mode'] : ''
        ];
    }

    /**
     * @param $csvData
     */
    public function validateIfTinted($csvData)
    {
        $baseOfferIdList = [];
        $tintedCheckHeader = [];
        foreach ($csvData as $rowIndex => $dataRow) {
            //skip headers
            if ($rowIndex == 0) {
                $tintedCheckHeader = $dataRow;
                continue;
            }
            $keyValue = array_combine($tintedCheckHeader, $dataRow);
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
        }  else {
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
     * Check if website id is valid or not.
     *
     * @param type $websiteId
     * @return boolean
     */
    private function isValidWebsiteId($websiteId)
    {
        try {
            $this->websiteRepository->getById($websiteId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get attribute set id by attribute set name.
     *
     * @param type $attributeSetName
     * @return type
     */
    public function getAttributeSetId($attributeSetName)
    {
        $setCollection = $this->attributeSetCollectionFactory->create()
            ->addFieldToFilter("attribute_set_name", $attributeSetName)
            ->addFieldToFilter("entity_type_id", 4)
            ->setPageSize(1)
            ->load();

        return ($setCollection->getSize() > 0) ? $setCollection->getFirstItem()->getAttributeSetId() : null;
    }

    /**
     * @param int $attributeSetId
     * @return AttributeSetInterface
     */
    public function getAttributeSetById($attributeSetId)
    {
        try {
            $attributeSet = $this->attributeSetRepository->get($attributeSetId);
        } catch (LocalizedException $exception) {
            throw new LocalizedException($exception->getMessage());
        }

        return $attributeSet;
    }

    /**
     * Validate attribute set and variant attributes.
     *
     * @param type $attributeSetCode
     * @return string
     */
    public function validateAttributeSetInRuleNameCsv($attributeSetCode, $attributeSetId)
    {
        if (!array_key_exists($attributeSetCode, $this->attributeSetValidation)) {
            $this->attributeSetValidation[$attributeSetCode] = $this->getValidation($attributeSetCode, $attributeSetId);
        }
        return $this->attributeSetValidation[$attributeSetCode];
    }

    public function getValidation($attributeSetCode, $attributeSetId)
    {
        $csvRow = $this->csvProcessor->getEvalFormula($attributeSetCode);
        if (isset($csvRow['AttributeSet']) && $csvRow['AttributeSet'] == $attributeSetCode) {
            if (isset($csvRow['ConfigurableAttributes']) && trim($csvRow['ConfigurableAttributes']) != '') {
                $attributes = $this->getAttributes($attributeSetId);
                $attributeCodes = array_column($attributes->getData(), 'attribute_code');
                $errors = [];
                foreach (explode(",", trim($csvRow['ConfigurableAttributes'])) as $confAttributeCode) {
                    try {
                        if (!in_array(trim($confAttributeCode), $attributeCodes)) {
                            $errors[] = "{$confAttributeCode} variant attribute is not assigned to current attribute set.";
                        }
                        $attribute = $this->attributeRepository->get(trim($confAttributeCode));
                        if (!$this->configurableModel->canUseAttribute($attribute)) {
                            $errors[] = "{$confAttributeCode} attribute can not be used as a variant attribute.";
                        }
                    } catch (\Exception $ex) {
                        $errors[] = "{$confAttributeCode} attribute in rulename CSV is not valid.";
                    }
                }
                if (!empty($errors)) {
                    return implode(",", $errors);
                } else {
                    $this->variantAttributes = explode(",", trim($csvRow['ConfigurableAttributes']));
                    return "";
                }
            }
            return "Rulename CSV does not have variant attributes for attribute set: {$attributeSetCode}.";
        }
        return $attributeSetCode . " attribute set is not added in rule name CSV.";
    }

    /**
     * Variant attributes
     *
     * @return type
     */
    public function getVariantAttributes()
    {
        if (empty($this->variantAttributes)) {
            throw new LocalizedException(__("Please check variant attributes in var/import/product_name_rule.csv file."));
        }
        return array_map('trim', $this->variantAttributes);
    }

    /**
     * Variant attributes
     *
     * @return type
     */
    public function getVariantAttributesWhileProductUpdate($attributeSetId)
    {
        if (empty($this->variantAttributes)) {
            $attributeSet = $this->getAttributeSetById($attributeSetId);
            $attributeSetCode = $attributeSet->getAttributeSetName();
            $this->getValidation($attributeSetCode, $attributeSetId);
        }
        return array_map('trim', $this->variantAttributes);
    }

    /**
     * Get configurable product ID by simple product ID.
     *
     * @param type $productId
     * @return type
     */
    public function getConfigurableProductId($productId)
    {
        $parentIds = $this->configurableModel->getParentIdsByChild($productId);
        if (empty($parentIds)) {
            return;
        }
        return array_shift($parentIds);
    }

    /**
     * Get attrubutes by attribute set.
     *
     * @param type $attributeSetId
     * @return type
     */
    public function getAttributes($attributeSetId)
    {
        $attributeCollection = $this->attribute->getCollection()
            ->addFieldToSelect("attribute_code")
            ->addFieldToSelect("frontend_input")
            ->addFieldToFilter("main_table.entity_type_id", 4);
        $attributeCollection->setOrder('attribute_id', 'ASC');

        $jointable = 'eav_entity_attribute';
        $attributeCollection
        ->getSelect()
        ->join(
            ['ot' => $jointable],
            "main_table.attribute_id = ot.attribute_id AND ot.attribute_set_id = " . $attributeSetId
        );
        return $attributeCollection;
    }

    /**
     * Get root categorgetCategoryIdByPathy name
     *
     * @return type
     */
    public function getRootCategoryName()
    {
        if (!$this->rootCategoryName) {
            $this->rootCategoryName = $this->getCategory($this->getRootCategoryId())->getName();
        }
        return $this->rootCategoryName;
    }

    /**
     * Get root category ID
     *
     * @return type
     */
    public function getRootCategoryId()
    {
        return $this->storeManagerInterface->getStore()->getRootCategoryId();
    }

    /**
     * Create attribute option.
     *
     * @param type $attributeCode
     * @param type $optionLabel
     */
    public function createAttributeOption($attributeCode, $optionLabel, $attributeSwatchInputType)
    {
        try {
            $optionLabel = trim($optionLabel);
            $this->log("createAttributeOption: " . $attributeCode . "=>". $optionLabel);

            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
            $data = $this->generateOptions($optionLabel, $attributeSwatchInputType);
            $attribute->addData($data)->save();
        } catch (\Exception $ex) {
            $this->log("Error while create new option. " . $attributeCode . "=>". $optionLabel);
            $this->log("Error: " . $ex->getMessage());
        }
    }

    protected function generateOptions($value, $swatchType)
    {
        $i = 0;
        $visualSwatch=$textSwatch=$optionsStore=$delete=$order=[];
        $swatchvisualValue='';

        if ($swatchType == 'visual' && $value!='') {
            $swatchvisualValue = $this->generateSwatchVariationsFile($value);
        }

        $order["option_{$i}"] = $i;

        $optionsStore["option_{$i}"] = [
            0 => $value, // admin
            1 => $value, // default store view
        ];

        $textSwatch["option_{$i}"] = [
            0 => $value, // admin
            1 => $value, // default store view
        ];

        $visualSwatch["option_{$i}"] = $swatchvisualValue;
        $delete["option_{$i}"] = '';

        $this->log(" - ".$swatchType."--- Option {$value} added for the attribute.");
        switch ($swatchType) {
            case 'text':
                return [
                    'optiontext' => [
                        'order'     => $order,
                        'value'     => $optionsStore,
                        'delete'    => $delete,
                    ],
                    'swatchtext' => [
                        'value'     => $textSwatch,
                    ],
                ];
                break;
            case 'visual':
                return [
                    'optionvisual' => [
                        'order'     => $order,
                        'value'     => $optionsStore,
                        'delete'    => $delete,
                    ],
                    'swatchvisual' => [
                        'value'     => $visualSwatch,
                    ],
                ];
                break;
            default:
                return [
                    'option' => [
                        'order'     => $order,
                        'value'     => $optionsStore,
                        'delete'    => $delete,
                    ],
                ];
        }
    }

    protected function generateSwatchVariationsFile($optionValue)
    {
        // Prepare visual swatches files.
        $newFile = "";
        $swatchVisualFile = str_replace([',',' '], '_', $optionValue).'.png';
        $mediaDirectorypath = $this->directoryList->getPath('media');
        if ($this->driverFile->isExists($mediaDirectorypath. DIRECTORY_SEPARATOR .'attribute'. DIRECTORY_SEPARATOR . $swatchVisualFile)) {
            $mediaDirectory = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $tmpMediaPath = $this->productMediaConfig->getBaseTmpMediaPath();
            $fullTmpMediaPath = $mediaDirectory->getAbsolutePath($tmpMediaPath);
            $this->driverFile->createDirectory($fullTmpMediaPath);
            $this->driverFile->copy(
                $mediaDirectorypath. DIRECTORY_SEPARATOR .'attribute'. DIRECTORY_SEPARATOR . $swatchVisualFile,
                $fullTmpMediaPath . DIRECTORY_SEPARATOR . $swatchVisualFile
            );
            $newFile = $this->swatchHelper->moveImageFromTmp($swatchVisualFile);
            if (substr($newFile, 0, 1) == '.') {
                $newFile = substr($newFile, 1); // Fix generating swatch variations for files beginning with ".".
            }
            $this->swatchHelper->generateSwatchVariations($newFile);
        } else {
            $this->log(" - ".$swatchVisualFile."<<--- Swatch image not found.");
        }
        return $newFile;
    }

    public function log($message)
    {
        $logFileName = BP . '/var/log/product_import.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
    }

    public function getTaxClassId($hsnCode)
    {
        $taxclass = $this->getTaxClass($hsnCode);
        if ($taxclass) {
            $attribute = $this->attributeRepository->get('tax_class_id');
            $options = $attribute->getOptions();
            foreach ($options as $option) {
                if ($option->getLabel() == $taxclass) {
                    return $option->getValue();
                }
            }
        }
        return false;
    }

    public function getTaxClass($hsnCode)
    {
        try {
            $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME_EMBITEL_TAX_MASTER);
            if (empty($this->taxTableData)) {
                $select = $this->connection->select()
                    ->from(
                        ['c' => $tableName],
                        ['tax_class_id', 'hsn_code']);
                $this->taxTableData = $this->connection->fetchAll($select);
            }

            //get the key of table data "$this->taxTableData"
            $taxClassKey = array_search($hsnCode, array_column($this->taxTableData,
                'hsn_code'));

            if (!empty($taxClassKey) && !empty($this->taxTableData[$taxClassKey]['tax_class_id'])) {
                return $this->taxTableData[$taxClassKey]['tax_class_id'];
            }

            $curlData = $this->getTaxApi->getTax($hsnCode);

            if (isset($curlData->tax->tax_rate)) {
                $taxRate = $curlData->tax->tax_rate;
                $hsnData = ['hsn_code' => $hsnCode, 'tax_class_id' => $taxRate];
                $this->connection->insertOnDuplicate($tableName, $hsnData);
                unset($this->taxTableData);

                return (string)$taxRate;
            }

            return false;
        } catch (Exception $ex) {
            $this->log("HSN and Tax update error=>" . $ex->getMessage());
        }
    }
}
