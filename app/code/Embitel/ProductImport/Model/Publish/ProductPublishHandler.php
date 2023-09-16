<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Publish;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Store\Api\StoreWebsiteRelationInterface;
use Magento\Catalog\Api\SpecialPriceInterface;
use Magento\Catalog\Api\Data\SpecialPriceInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Area;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

/**
 * This classs is to handle product import
 */
class ProductPublishHandler
{
    const MERCHANDISING_CATEGORY_NAME = 'Merchandising Category';
    const NAVIGATION_CATEGORY_NAME = 'Navigation Category';
    const BRAND_CATEGORY_NAME = 'Brand';
    const DEFAULT_CUSTOMER_GROUP = 'customer/create_account/default_group';
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    const UNPUBLISH_LOWER_MRP = 'ebo/ebo_product_publish/unpublish_lower_mrp';
    const DEFAULT_ZONE = 'regional_pricing/setting/default_zone';
    const IMAGE_LOCATION_PATH = 'core_media/service/use_custom_source';

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
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection = null;

    protected $attibuteOptions = [];

    protected $products = [];

    protected $configurableMethod = null;

    protected $categoryFactory;


    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    protected $groupRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Tax\Api\TaxCalculationInterface
     */
    protected $taxCalculation;

    protected $configurableType;

    protected $parentIdsArray = [];

    /**
     * @var ProductAction
     */
    protected $productAction;

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

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
     * @param ResourceConnection $resourceConnection
     * @param string $storeManagerInterface
     * @param GroupRepositoryInterface $groupRepository
     * @param string $scopeConfigInterface
     * @param string $transportBuilder
     * @param Configurable $configurableType
     * @param ProductAction $productAction
     * @param DateTimeFactory $dateTime
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
        ResourceConnection $resourceConnection,
        CategoryFactory $categoryFactory,
        StoreManagerInterface $storeManagerInterface,
        GroupRepositoryInterface $groupRepository,
        ScopeConfigInterface $scopeConfigInterface,
        TransportBuilder $transportBuilder,
        TaxCalculationInterface $taxCalculation,
        Configurable $configurableType,
        ProductAction $productAction,
        DateTimeFactory $dateTime,
        ProductPushHelper $productPushHelper
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
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->categoryFactory = $categoryFactory;
        $this->storeManager = $storeManagerInterface;
        $this->groupRepository = $groupRepository;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->transportBuilder = $transportBuilder;
        $this->taxCalculation = $taxCalculation;
        $this->configurableType = $configurableType;
        $this->productAction = $productAction;
        $this->dateTime = $dateTime;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Import Products from CSV file
     *
     * @param array $file file info retrieved from $_FILES array
     * @return array
     * @throws LocalizedException
     */
    public function publishFromCsvFile($file)
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

        if (isset($filteredCsvData['failure'])) {
            $this->products['failure'] = $filteredCsvData['failure'];
        }
        if (isset($filteredCsvData['success'])) {
            $this->publishProducts($filteredCsvData['success']);
        }

        return $this->csvProcessor->prepareResponse($this->products);
    }

    /**
     * Load all options of the select attributes.
     */
    public function loadAttributeAllOptions()
    {
        $attributeCollection = $this->attribute->getCollection()
                ->addFieldToFilter("entity_type_id", 4)
                ->addFieldToFilter("frontend_input", "select");
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
     * Publish product by sku.
     *
     * @param type $rows
     */
    private function publishProducts($rows)
    {
        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $currentTime = date('Y-m-d H:i:s', strtotime($current));

        $defaultCustomerGroupId = $this->getConfig(self::DEFAULT_CUSTOMER_GROUP);
        $defaultCustomerGroupName = $this->getGroupName($defaultCustomerGroupId);
        $unpublishLowerMrp = $this->getConfig(self::UNPUBLISH_LOWER_MRP);

        $defaultZone = $this->getConfig(self::DEFAULT_ZONE);

        foreach ($rows as $row) {
            try {
                $validationError = [];

                if (isset($row['publish']) && !in_array(strtolower($row['publish']), ["yes", "no"])) {
                    $row['error'] = "Skipping row as publish is not set as yes or no.";
                    $this->products['failure'][] = $row;
                    continue;
                }

                $product = $this->productFactory->create()->loadByAttribute('sku', $row['sku']);
                if (!$product) {
                    $row['error'] = "Product doesn't exist with sku:" . $row['sku'];
                    $this->products['failure'][] = $row;
                    continue;
                }

                if (isset($row['publish']) && strtolower($row['publish']) == 'no') {
                    if (!isset($row['reason']) || $row['reason'] == "") {
                        $row['error'] = "Provide valid reason for manually unpublish the product.";
                        $this->products['failure'][] = $row;
                        continue;
                    }

                    //Disable Product if already Enabled
                    $updatedProductData =
                    [
                        'is_published' => 0,
                        'manual_unpublish' => 1,
                        'manual_unpublish_reason' => $row['reason'],
                        'unpublish_at' => $currentTime
                    ];

                    $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                    $this->products['success'][] = $row;
                    $this->productFieldProcessor->log("SKU Published: " . $product->getSku());
                    $this->publishParentProduct($product->getId());
                    $this->updateProductCronFlag($product->getId());
                    $this->productPushHelper->updateCatalogServicePushData($product->getId());

                    continue;
                }

                //Check if  Allowed Channels is set as Store
                if (strtolower($product->getAttributeText('allowed_channels')) == "store") {
                    $validationError[] = " Allowed Channels is set as Store only.";
                    $row['error'] = implode(",", $validationError);
                    $this->products['failure'][] = $row;
                    continue;
                }

                //Check if product image is set
                if ($this->getConfig(self::IMAGE_LOCATION_PATH)) {
                    //Check if product image is set
                    if (empty($product->getData('base_image_custom'))) {
                        $validationError[] = "Product doesn't have an image uploaded.";
                    }
                } else {
                    //Check if product image is set
                    if (empty($product->getImage()) || $product->getImage() == 'no_selection') {
                        $validationError[] = "Product doesn't have an image uploaded.";
                    }
                }

                //Check if tax class is set
                if ($product->getTaxClassId() == 0) {
                    $validationError[] = "Product doesn't have a tax class.";
                }

                //Check if product is having price or not.
                if ($product->getPrice() <= 0) {
                    $validationError[] = "Product price not available.";
                }

                //Check if tier prices greater than the MRP
                if ($unpublishLowerMrp == 1) {
                    if ($product->getMrp() > 0 && $product->getPrice() > $product->getMrp()) {
                        $validationError[] = "Product price is greater than MRP.";
                    }
                }

                //Price for 1 qty of default customer group must be exist - START
                $defaultTierPrice = false;
                $product->setSkipZoneCheckFlag(true);
                foreach ($product->getTierPrices() as $tierPrices) {
                    if ($tierPrices->getCustomerGroupId() == $defaultCustomerGroupId
                            && $tierPrices->getExtensionAttributes()->getCustomerZone() == $defaultZone
                            && $tierPrices->getQty() == 1 && $tierPrices->getValue() > 0) {
                        $defaultTierPrice = true;
                        break;
                    }
                }
                if ($defaultTierPrice == false) {
                    $validationError[] = "Base Tier Price for 1 qty of (".$defaultCustomerGroupName.") not Available.";
                }
                //Price for 1 qty of default customer group must be exist - END

                $productRateId = $product->getTaxClassId();
                $rate = $this->taxCalculation->getCalculatedRate($productRateId);

                $productMrp = floatval(preg_replace('/[^\d.]/', '', trim($product->getMrp())));
                $product->setSkipZoneCheckFlag(true);
                $defaultTierPriceValue = round($product->getPrice(), 2);

                /*$b2bPriceArray = [];
                $b2pPriceArray = [];
                $b2cPriceArray = [];*/
                // $b2bPriceWithZoneArray = [];
                $b2pPriceWithZoneArray = [];
                $b2cPriceWithZoneArray = [];
                $allZone = [];
                foreach ($product->getTierPrices() as $tierPrices) {
                    $customerGroupName = $this->getGroupName($tierPrices->getCustomerGroupId());
                    $tierPriceValue = floatval(preg_replace('/[^\d.]/', '', trim($tierPrices->getValue())));
                    $zone = $tierPrices->getExtensionAttributes()->getCustomerZone();
                    // if (strtolower($customerGroupName) == "b2b") {
                    //     /*$b2bPriceArray[] = [
                    //         "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                    //         "customer_group_name"   => $customerGroupName,
                    //         "qty"                   => (int)$tierPrices->getQty(),
                    //         "value"                 => $tierPriceValue
                    //     ];*/
                    //     $b2bPriceWithZoneArray[$zone][]  = [
                    //     "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                    //     "customer_group_name"   => $customerGroupName,
                    //     "qty"                   => (int)$tierPrices->getQty(),
                    //     "value"                 => $tierPriceValue
                    //     ];
                    //     $allZone[$zone][(int)$tierPrices->getQty()][$customerGroupName] = ['value' => $tierPriceValue];
                    // }

                    if (strtolower($customerGroupName) == "b2p") {
                        /*$b2pPriceArray[] = [
                            "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                            "customer_group_name"   => $customerGroupName,
                            "qty"                   => (int)$tierPrices->getQty(),
                            "value"                 => $tierPriceValue
                        ];*/
                        $b2pPriceWithZoneArray[$zone][] = [
                        "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                        "customer_group_name"   => $customerGroupName,
                        "qty"                   => (int)$tierPrices->getQty(),
                        "value"                 => $tierPriceValue
                        ];
                        $allZone[$zone][(int)$tierPrices->getQty()][$customerGroupName] = ['value' => $tierPriceValue];
                    }

                    if (strtolower($customerGroupName) == "b2c") {
                        /*$b2cPriceArray[] = [
                            "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                            "customer_group_name"   => $customerGroupName,
                            "qty"                   => (int)$tierPrices->getQty(),
                            "value"                 => $tierPriceValue
                        ];*/
                        $b2cPriceWithZoneArray[$zone][] = [
                        "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                        "customer_group_name"   => $customerGroupName,
                        "qty"                   => (int)$tierPrices->getQty(),
                        "value"                 => $tierPriceValue
                        ];
                        $allZone[$zone][(int)$tierPrices->getQty()][$customerGroupName] = ['value' => $tierPriceValue];
                    }

                    $tierError = false;
                    $productTaxPrices = $this->getPriceInclAndExclTax($tierPrices->getValue(), $rate);
                    $productTierPrice = $productTaxPrices['incl'];

                    if ($unpublishLowerMrp == 1) {
                        if ($productMrp > 0 && $productTierPrice > $productMrp) {
                            $validationError[] = "Product price for " . $customerGroupName . " customer group is greater than MRP.";
                            $tierError = true;
                        }
                    }

                    // Changing the logic here by removing default customer group id
                    /*if (isset($defaultTierPriceValue) && $productTierPrice > $defaultTierPriceValue && $tierPrices->getCustomerGroupId() !== $defaultCustomerGroupId) {
                        $validationError[] = $customerGroupName . " tier price is higher than Base Tier Price (".$defaultCustomerGroupName.").";
                        $tierError = true;
                    }*/

                    if (isset($defaultTierPriceValue) && $productTierPrice > $defaultTierPriceValue) {
                        $validationError[] = $customerGroupName.'  '.(int)$tierPrices->getQty().' Unit '.$tierPrices->getExtensionAttributes()->getCustomerZone().' > Base Price';
                        $tierError = true;
                    }

                    if ($tierError == true) {
                        continue;
                    }
                }

                /** Added logic to check  if B2B > B2P/B2C or B2P > B2C for same quantity **/
                foreach ($allZone as $zone => $ZoneData) {
                    foreach ($ZoneData as $key => $value) {
                        // if (isset($value['B2B'])) {
                        //     if(isset($value['B2C']) &&
                        //         ($value['B2B']['value'] > $value['B2C']['value'])) {
                        //         $validationError[] = "B2B ".strtoupper($zone)." Zone ".$key." unit price is higher than B2C ".strtoupper($zone)." Zone ".$key. "unit price.";
                        //     }
                        //     if(isset($value['B2P']) &&
                        //         ($value['B2B']['value'] > $value['B2P']['value'])
                        //     ) {
                        //         $validationError[] = "B2B ".strtoupper($zone)." Zone ".$key." unit price is higher than B2P ".strtoupper($zone)." Zone ".$key. " unit price.";
                        //     }

                        // }
                        if(isset($value['B2P'])) {
                            if(isset($value['B2C']) &&
                                ($value['B2P']['value'] > $value['B2C']['value'])) {
                                $validationError[] = "B2P ".strtoupper($zone)." Zone ".$key." unit price is higher than B2C ".strtoupper($zone)." Zone ".$key. " unit price.";
                            }
                        }
                    }
                }

                // foreach ($b2bPriceWithZoneArray as $key => $b2bPriceArray) {
                //     $b2bArrayKeys = array_column($b2bPriceArray, 'qty');
                //     array_multisort($b2bArrayKeys, SORT_ASC, $b2bPriceArray);
                //     for($i = 1; $i < sizeof($b2bPriceArray); $i++) {
                //         //We check if the value is greater or equal than the one before
                //         if($b2bPriceArray[$i]['value'] >= $b2bPriceArray[$i-1]['value']) {
                //             $validationError[] = $b2bPriceArray[$i]['customer_group_name']." ".$key." Zone ".$b2bPriceArray[$i]['qty'] .
                //                     " unit price is higher or equal than ".$b2bPriceArray[$i]['customer_group_name']." ".$b2bPriceArray[$i-1]['qty']." unit price.";
                //             continue;
                //         }
                //     }
                // }

                foreach ($b2pPriceWithZoneArray as $key => $b2pPriceArray) {
                    $b2pArrayKeys = array_column($b2pPriceArray, 'qty');
                    array_multisort($b2pArrayKeys, SORT_ASC, $b2pPriceArray);
                    for($i = 1; $i < sizeof($b2pPriceArray); $i++) {
                        //We check if the value is greater or equal than the one before
                        if($b2pPriceArray[$i]['value'] >= $b2pPriceArray[$i-1]['value']) {
                            $validationError[] = $b2pPriceArray[$i]['customer_group_name']." ".$key." Zone ".$b2pPriceArray[$i]['qty'] .
                                    " unit price is higher or equal than ".$b2pPriceArray[$i]['customer_group_name']." ".$b2pPriceArray[$i-1]['qty']." unit price.";
                            continue;
                        }
                    }
                }

                foreach ($b2cPriceWithZoneArray as $key => $b2cPriceArray) {
                    $b2cArrayKeys = array_column($b2cPriceArray, 'qty');
                    array_multisort($b2cArrayKeys, SORT_ASC, $b2cPriceArray);
                    for($i = 1; $i < sizeof($b2cPriceArray); $i++) {
                        //We check if the value is greater or equal than the one before
                        if($b2cPriceArray[$i]['value'] >= $b2cPriceArray[$i-1]['value']) {
                            $validationError[] = $b2cPriceArray[$i]['customer_group_name']." ".$key." Zone ".$b2cPriceArray[$i]['qty'] .
                                    " unit price is higher or equal than ".$b2cPriceArray[$i]['customer_group_name']." ".$b2cPriceArray[$i-1]['qty']." unit price.";
                            continue;
                        }
                    }
                }

                //Check for package related attributes
                $packageHeight = trim($product->getPackageHeightInCm());
                if (empty($packageHeight) || !is_numeric($packageHeight)) {
                    $validationError[] = "Package Height In Cm is not a valid number.";
                }

                $packageLength = trim($product->getPackageLengthInCm());
                if (empty($packageLength) || !is_numeric($packageLength)) {
                    $validationError[] = "Package Length In Cm is not a valid number.";
                }

                $packageWeight = trim($product->getPackageWeightInKg());
                if (!isset($packageWeight) || !is_numeric($packageWeight)) {
                    $validationError[] = "Package Weight In Kg is not a valid number.";
                }

                $packageWidth = trim($product->getPackageWidthInCm());
                if (empty($packageWidth) || !is_numeric($packageWidth)) {
                    $validationError[] = "Package Width In Cm is not a valid number.";
                }

                $courierType = $product->getCourierType();
                if (!isset($courierType)) {
                    $validationError[] = "Courier Type is not set.";
                }

                //Check for publish set to yes.
                $attrError = [];
                $attributes = $product->getAttributes();
                foreach ($attributes as $attr) {
                    if ($attr->getIsRequiredForPublish() &&
                            ($attr->getFrontend()->getValue($product) == null ||
                            $attr->getFrontend()->getValue($product) == '' )) {
                        //$attributeCode = $attr->getAttributeCode();
                        $attributeLabel = $attr->getFrontend()->getLabel();
                        $attrError[] = $attributeLabel;
                        continue;
                    }
                }

                if (!empty($attrError)) {
                    $validationError[] = "Product can not be published as following fields are required to publish and have empty values: "
                        .implode(',', $attrError);
                }

                if (!empty($validationError)) {
                    $row['error'] = implode(",", $validationError);
                    $this->products['failure'][] = $row;

                    //Disable Product if already Enabled
                    if ($product->getIsPublished()
                            || $product->getPublishFailureReport() != implode(",", $validationError)) {
                        $updatedProductData =
                        [
                            'is_published' => 0,
                            'publish_failure_report' => implode(",", $validationError),
                            'unpublish_at' => $currentTime
                        ];
                        $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("SKU Un-Published: " . $product->getSku());
                        $this->publishParentProduct($product->getId());
                    }
                } else {
                    //Enable Product
                    if (!$product->getIsPublished() || $product->getPublishFailureReport()) {
                        $updatedProductData =
                        [
                            'is_published' => 1,
                            'publish_failure_report' => "",
                            'unpublish_at' => "",
                            'manual_unpublish' => 0,
                            'manual_unpublish_reason' => ""
                        ];
                        $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("SKU Published: " . $product->getSku());
                        $this->publishParentProduct($product->getId());
                    }
                    $this->products['success'][] = $row;
                }
                $this->updateProductCronFlag($product->getId());
                $this->productPushHelper->updateCatalogServicePushData($product->getId());
            } catch (\Exception $e) {
                $this->productFieldProcessor->log("There is some issue for : " . $row['sku'] . ", error=>" . $e->getMessage());
                $row['error'] = $e->getMessage();
                $this->products['failure'][] = $row;
            }
        }
    }

    /**
     * Publish parent product based on child is_published values.
     *
     * @param int $childId
     * @param datetime $currentTime
     * @return void
     */
    public function publishParentProduct($childId)
    {
        $childIds = [];
        $parentIds = $this->configurableType->getParentIdsByChild($childId);
        $this->parentIdsArray[] = $parentIds;

        foreach ($parentIds as $productId) {
            if (!in_array($productId, $this->parentIdsArray)) {
                $configProduct = $this->productFactory->create()->load($productId);
                $_children = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
                foreach ($_children as $child){
                    $childIds[] = $child->getId();
                }

                $connection = $this->resourceConnection->getConnection();
                $query = "SELECT cpe.entity_id,sku,cpes.entity_id FROM catalog_product_entity cpe
                    LEFT JOIN catalog_product_entity_int cpes ON cpe.entity_id = cpes.entity_id
                    AND cpes.attribute_id = (SELECT attribute_id FROM eav_attribute
                    WHERE entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product')
                    AND attribute_code = 'is_published') WHERE type_id = 'simple' AND cpes.value=1
                    AND cpe.entity_id IN (".implode(',', $childIds).")";
                $result = $connection->fetchAll($query);
                if (count($result) > 0) {
                    if (!$configProduct->getIsPublished()) {
                        $updatedProductData = ['is_published' => 1, 'manual_unpublish' => 0];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("Parent SKU Published: " . $configProduct->getSku());
                    } else {
                        $this->productFieldProcessor->log("Parent SKU already Published: " . $configProduct->getSku());
                    }
                } else {
                    if ($configProduct->getIsPublished()) {
                        $updatedProductData = ['is_published' => 0];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("Parent SKU Un-Published: " . $configProduct->getSku());
                    } else {
                        $this->productFieldProcessor->log("Parent SKU already Un-Published: " . $configProduct->getSku());
                    }
                }
            }
        }
    }

    /**
     * Update product sync count.
     *
     * @param type $productId
     */
    protected function updateProductCronFlag($productId)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setData('two_step_publish_cron', 1);
        $product->setData('store_id', 0);
        $product->getResource()->saveAttribute($product, 'two_step_publish_cron');
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
        return 0;
    }

    /**
     * Get Category id by name.
     *
     * @param string $categoryTitle
     * @return int
     */
    public function getCategoryIdByName($categoryTitle)
    {
        $categoryId = '';
        $collection = $this->categoryFactory->create()->getCollection()
              ->addAttributeToFilter('name', $categoryTitle)->setPageSize(1);

        if ($collection->getSize()) {
            $categoryId = $collection->getFirstItem()->getId();
        }

        return $categoryId;
    }

    /**
     * Get Customer Group code by id.
     *
     * @param int $groupId
     * @return string
     */
    public function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    /**
     * get stock status
     *
     * @param string $sku
     * @return bool
     */
    public function getStockStatus($sku)
    {
        $stockItem = $this->stockRepository->getStockItemBySku($sku);
        $isInStock = $stockItem ? $stockItem->getIsInStock() : false;
        return $isInStock;
    }

    /**
     * Get config field value by path
     *
     * @param $path
     * @return
     */
    public function getConfig($path)
    {
        return $this->scopeConfigInterface->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
    * @param float $price
    * @param float $rate
    * @throws \Magento\Framework\Exception\NoSuchEntityException
    * @throws \Magento\Framework\Exception\LocalizedException
    * @return array
    */
    public function getPriceInclAndExclTax($price, $rate)
    {
        if ((int) $this->getConfig('tax/calculation/price_includes_tax') === 1) {
            // Product price in catalog is including tax.
            $priceExcludingTax = $price / (1 + ($rate / 100));
        } else {
            // Product price in catalog is excluding tax.
            $priceExcludingTax = $price;
        }

        $priceIncludingTax = $priceExcludingTax + ($priceExcludingTax * ($rate / 100));

        return [
            'incl' => round($priceIncludingTax, 2),
            'excl' => round($priceExcludingTax, 2)
        ];
    }

    /**
     * [generateTemplate description]  with template file and tempaltes variables values
     *
     * @param Mixed $emailTemplateVariables
     * @param Mixed $receiverInfo
     * @param Mixed $senderInfo
     * @param Mixed $templateId
     * @return void
     */
    public function generateTemplate($emailTemplateVariables, $receiverInfo, $senderInfo, $templateId)
    {
        $receiverInfoEmail = str_replace(' ', '', $receiverInfo['email']);
        $recieverEmails = array_map('trim', explode(',', $receiverInfoEmail));
        $storeId = (int)$this->storeManager->getStore()->getId();
        $template = $this->transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ]
            )->setTemplateVars($emailTemplateVariables)
            ->setFrom($senderInfo)
            ->addTo($recieverEmails);
        return $this;
    }
}
