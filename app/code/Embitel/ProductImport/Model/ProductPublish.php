<?php

namespace Embitel\ProductImport\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

/**
 * This class to get products to publish.
 */
class ProductPublish
{
    /**
     * Cron status path
     */
    const CRON_STATUS_PATH = 'ebo/ebo_product_publish/cron_status';
    const CRON_LOG_STATUS_PATH = 'ebo/ebo_product_publish/log_active';
    const FILTER_PRODUCTS = 'ebo/ebo_product_publish/cron_filter';
    const DEFAULT_CUSTOMER_GROUP = 'customer/create_account/default_group';
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    const XML_PATH_PUBLISH_ATTRIBUTES = 'ebo/ebo_config/publish_fields';
    const UNPUBLISH_LOWER_MRP = 'ebo/ebo_product_publish/unpublish_lower_mrp';
    const DEFAULT_ZONE = 'regional_pricing/setting/default_zone';
    const IMAGE_LOCATION_PATH = 'core_media/service/use_custom_source';

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var StoreManagerInterface
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TaxCalculationInterface
     */
    protected $taxCalculation;

    /**
     * @var ProductAction
     */
    protected $productAction;

    protected $publishAttributes = [];

    /**
     * @var Configurable
     */
    protected $configurableType;

    protected $parentIdsArray = [];

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection = null;

    /**
     * @param ProductFactory $productFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param GroupRepositoryInterface $groupRepository
     * @param StoreManagerInterface $storeManagerInterface
     * @param TaxCalculationInterface $taxCalculation
     * @param ProductAction $productAction
     * @param Configurable $configurableType
     * @param DateTimeFactory $dateTime
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,
        GroupRepositoryInterface $groupRepository,
        StoreManagerInterface $storeManagerInterface,
        TaxCalculationInterface $taxCalculation,
        ProductAction $productAction,
        DateTimeFactory $dateTime,
        Configurable $configurableType,
        ResourceConnection $resourceConnection,
        ProductPushHelper $productPushHelper
    ) {
        $this->productFactory = $productFactory;
        $this->scopeConfigInterface = $scopeConfig;
        $this->groupRepository = $groupRepository;
        $this->storeManager = $storeManagerInterface;
        $this->taxCalculation = $taxCalculation;
        $this->productAction = $productAction;
        $this->dateTime = $dateTime;
        $this->configurableType = $configurableType;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfigInterface->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Check if cron is active or not.
     */
    public function isCronActive()
    {
        return $this->getConfig(self::CRON_STATUS_PATH);
    }

    /**
     * Check whether to filter products by is_published attribute.
     */
    public function isPublishFilter()
    {
        return $this->getConfig(self::FILTER_PRODUCTS);
    }

    /**
     * Regenerate titles for products.
     *
     * @param type $products
     */
    public function publishProducts($products, $isCron = false)
    {
        $defaultZone = $this->getConfig(self::DEFAULT_ZONE);
        foreach ($products as $_product) {
            $this->publishProduct($_product, $defaultZone, $isCron);
        }
    }

    /**
     * Regenerate titles for product.
     *
     * @param type $_product
     */
    public function publishProduct($_product, $defaultZone, $isCron = false)
    {
        try {
            $validationError = [];

            $product = $this->productFactory->create()->load($_product->getId());
            if (!$product) {
                $validationError[] = "Product doesn't exist with Id:" . $_product->getId();
                $this->log("Product does not exist with Id: " . $_product->getId());
                return;
            }

            //Skip product if it's manually unpublished.
            if ($product->getManualUnpublish() == 1) {
                $this->log("Product is manually unpublished: " . $_product->getId());
                return;
            }

            //Check if  Allowed Channels is set as Store
            if (strtolower($product->getAttributeText('allowed_channels')) == "store") {
                if ($product->getIsPublished()) {
                    $dateModel = $this->dateTime->create();
                    $current = $dateModel->gmtDate();
                    $currentTime = date('Y-m-d H:i:s', strtotime($current));

                    //Unpublish Product if already Published
                    $updatedProductData =
                        [
                            'is_published' => 0,
                            'publish_failure_report' => "Allowed Channels is set as Store only.",
                            'unpublish_at' => $currentTime
                        ];

                    $this->log("STORE SKU Un-Published: " . $product->getSku());
                    $this->productAction->updateAttributes([$_product->getId()], $updatedProductData, 0);
                    $this->publishParentProduct($_product->getId());
                }
                $this->updateProductCronFlag($_product->getId());
                $this->productPushHelper->updateCatalogServicePushData($_product->getId());

                return;
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
            $unpublishLowerMrp = $this->getConfig(self::UNPUBLISH_LOWER_MRP);

            //Check if tier prices greater than the MRP
            if ($unpublishLowerMrp == 1) {
                if ($product->getMrp() > 0 && $product->getPrice() > $product->getMrp()) {
                    $validationError[] = "Product price is greater than MRP.";
                }
            }

            //Price for 1 qty of default customer group must be exist - START
            $defaultTierPrice = false;
            $product->setSkipZoneCheckFlag(true);
            $defaultCustomerGroupId = $this->getConfig(self::DEFAULT_CUSTOMER_GROUP);
            $defaultCustomerGroupName = $this->getGroupName($defaultCustomerGroupId);
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

            /** changing $defaultTierPriceValue to be fetched from base price **/
            $product->setSkipZoneCheckFlag(true);
            $defaultTierPriceValue = round($product->getPrice(), 2);
            $productRateId = $product->getTaxClassId();
            $rate = $this->taxCalculation->getCalculatedRate($productRateId);
            $productMrp = floatval(preg_replace('/[^\d.]/', '', trim($product->getMrp())));

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
                //         "customer_group_id"     => $tierPrices->getCustomerGroupId(),
                //         "customer_group_name"   => $customerGroupName,
                //         "qty"                   => (int)$tierPrices->getQty(),
                //         "value"                 => $tierPriceValue
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
                    $validationError[] = $customerGroupName.'  '.(int)$tierPrices->getQty().' Unit '.$zone.' > Base Price';
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

            //$this->log("Product Id: {$_product->getId()} Status: {$product->getStatus()} Is Published: {$product->getIsPublished()} ");
            if (!empty($validationError)) {
                $this->log("Product Publish Failed! Id: {$_product->getId()} " . implode(',', $validationError));

                //Get current GMT time.
                $dateModel = $this->dateTime->create();
                $current = $dateModel->gmtDate();
                $currentTime = date('Y-m-d H:i:s', strtotime($current));

                //Unpublish Product if already Published
                if ($_product->getIsPublished()
                            || $_product->getPublishFailureReport() != implode(",", $validationError)) {
                    $updatedProductData =
                        [
                            'is_published' => 0,
                            'publish_failure_report' => implode(",", $validationError),
                            'unpublish_at' => $currentTime
                        ];
                    $this->productAction->updateAttributes([$_product->getId()], $updatedProductData, 0);
                    $this->log("Product unpublish Success! Id: {$_product->getSku()} ");
                    $this->publishParentProduct($_product->getId());
                }
            } else {
                //Publish Product
                if (!$_product->getIsPublished() || $_product->getPublishFailureReport()) {
                    $updatedProductData =
                        [
                            'is_published' => 1,
                            'publish_failure_report' => "",
                            'unpublish_at' => ""
                        ];
                    $this->productAction->updateAttributes([$_product->getId()], $updatedProductData, 0);
                    $this->log("Product Publish Success! Id: {$_product->getSku()} ");
                    $this->publishParentProduct($_product->getId());
                }
            }
            $this->updateProductCronFlag($_product->getId());
            $this->productPushHelper->updateCatalogServicePushData($_product->getId());
            
        } catch (\Exception $e) {
            $this->log("Something went wrong! Product Id: {$_product->getId()}, Error: " . $e->getMessage());
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
                        $this->log("Parent SKU Published: " . $configProduct->getSku());
                    } else {
                        $this->log("Parent SKU already Published: " . $configProduct->getSku());
                    }
                } else {
                    if ($configProduct->getIsPublished()) {
                        $updatedProductData = ['is_published' => 0];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->log("Parent SKU Un-Published: " . $configProduct->getSku());
                    } else {
                        $this->log("Parent SKU already Un-Published: " . $configProduct->getSku());
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
     * Log the data
     *
     * @param type $message
     * @return type
     */
    public function log($message)
    {
        if (!$this->getConfig(self::CRON_LOG_STATUS_PATH)) {
            return;
        }

        $logFileName = BP . '/var/log/product_publish.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
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
}
