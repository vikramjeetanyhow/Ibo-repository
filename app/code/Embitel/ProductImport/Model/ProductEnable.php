<?php

namespace Embitel\ProductImport\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

/**
 * This class to get products to enable.
 */
class ProductEnable
{
    /**
     * Cron status path
     */
    const CRON_STATUS_PATH = 'ebo/ebo_product_enable/cron_status';
    const CRON_LOG_STATUS_PATH = 'ebo/ebo_product_enable/log_active';    
    const FILTER_PRODUCTS = 'ebo/ebo_product_enable/cron_filter_by_status';
    const DEFAULT_CUSTOMER_GROUP = 'customer/create_account/default_group';
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    const XML_PATH_ENABLE_ATTRIBUTES = 'ebo/ebo_config/publish_fields';
    const DISABLE_LOWER_MRP = 'ebo/ebo_product_enable/disable_lower_mrp';
    const DEFAULT_ZONE = 'regional_pricing/setting/default_zone';
    
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

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection = null;

    protected $parentIdsArray = [];

    /**
     * @var Configurable
     */
    protected $configurableType;

    protected $enableAttributes = [];

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

    /**
     * @param ProductFactory $productFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param GroupRepositoryInterface $groupRepository
     * @param StoreManagerInterface $storeManagerInterface
     * @param TaxCalculationInterface $taxCalculation
     * @param ProductAction $productAction
     * @param Configurable $configurableType
     * @param ResourceConnection $resourceConnection
     * @param DateTimeFactory $dateTime
     */
    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,
        GroupRepositoryInterface $groupRepository,
        StoreManagerInterface $storeManagerInterface,
        TaxCalculationInterface $taxCalculation,
        ProductAction $productAction,
        Configurable $configurableType,
        ResourceConnection $resourceConnection,
        DateTimeFactory $dateTime,
        ProductPushHelper $productPushHelper
    ) {
        $this->productFactory = $productFactory;
        $this->scopeConfigInterface = $scopeConfig;
        $this->groupRepository = $groupRepository;
        $this->storeManager = $storeManagerInterface;
        $this->taxCalculation = $taxCalculation;
        $this->productAction = $productAction;
        $this->configurableType = $configurableType;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->dateTime = $dateTime;
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
     * Check whether to filter products by status attribute.
     */
    public function isEnableFilter()
    {
        return $this->getConfig(self::FILTER_PRODUCTS);
    }

    /**
     * Regenerate titles for products.
     *
     * @param type $products
     */
    public function enableProducts($products, $isCron = false)
    {
        $defaultZone = $this->getConfig(self::DEFAULT_ZONE);
        foreach ($products as $_product) {
            $this->enableProduct($_product, $defaultZone, $isCron);
        }
    }

    /**
     * Regenerate titles for product.
     *
     * @param type $_product
     */
    public function enableProduct($_product, $defaultZone, $isCron = false)
    {
        try {
            $validationError = [];

            $product = $this->productFactory->create()->load($_product->getId());
            if (!$product) {
                $validationError[] = "Product doesn't exist with Id:" . $_product->getId();
                $this->log("Product does not exist with Id: " . $_product->getId());
            }

            //Check if tax class is set
            if ($product->getTaxClassId() == 0) {
                $validationError[] = "Tax Slab Not Available.";
            }

            //Check if product is manually disabled.
            if ($product->getManualDisable()) {
                $validationError[] = "Product is manually disabled.";
            }

            $product->setSkipZoneCheckFlag(true);
            $defaultTierPriceValue = round($product->getPrice(), 2);
            $productRateId = $product->getTaxClassId();
            $rate = $this->taxCalculation->getCalculatedRate($productRateId);
            $productMrp = floatval(preg_replace('/[^\d.]/', '', trim($product->getMrp())));

            $disable_lower_mrp = $this->getConfig(self::DISABLE_LOWER_MRP);

            //Check if product is having price or not.
            if ($product->getPrice() <= 0) {
                $validationError[] = "Product price not available.";
            }

            //Check if tier prices greater than the MRP
            if ($disable_lower_mrp == 1) {
                if ($product->getMrp() > 0 && $product->getPrice() > $product->getMrp()) {
                    $validationError[] = "Product price is greater than MRP.";
                }
            }

            //Price for 1 qty of default customer group must be exist - START
            $defaultTierPrice = false;
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

            foreach ($product->getTierPrices() as $tierPrices) {
                $tierError = false;
                $productTaxPrices = $this->getPriceInclAndExclTax($tierPrices->getValue(), $rate);
                $productTierPrice = $productTaxPrices['incl'];
                $customerGroupName = $this->getGroupName($tierPrices->getCustomerGroupId());
                if ($disable_lower_mrp == 1) {
                    if ($productMrp > 0 && $productTierPrice > $productMrp) {
                        $validationError[] = "Product price for " . $customerGroupName . " customer group is greater than MRP.";
                        $tierError = true;
                    }
                }


                /*if (isset($defaultTierPriceValue) && $productTierPrice > $defaultTierPriceValue) {
                    $customerGroupName = $this->getGroupName($tierPrices->getCustomerGroupId());
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

            //Check if  Allowed Channels is set as Store
//            if (strtolower($product->getAttributeText('allowed_channels')) == "online") {
//                $validationError[] = " Allowed Channels is set as Online only.";
//            }
            
            //Get attributes which are required to enable
            $enableAttributes = $this->getEnableAttributes();
            if (!empty($enableAttributes)) {
                foreach ($enableAttributes as $attributeCode) {
                    if (($product->getData($attributeCode) == null) || $product->getData($attributeCode) == '') {
                        $attrError[] = $attributeCode;
                        continue;
                    }
                }
            }
            
            if (!empty($attrError)) {
                $validationError[] = "Product can not be enabled as following fields are required to enable and have empty values: "
                    .implode(',', $attrError);
            }
            
            $this->log("Product Id: {$_product->getId()} Status: {$product->getStatus()} Is Published: {$product->getIsPublished()} ");
            if (!empty($validationError)) {
                $this->log("Product Enable Failed! Id: {$_product->getId()} " . implode(',', $validationError));
                //Get current GMT time.
                $dateModel = $this->dateTime->create();
                $current = $dateModel->gmtDate();
                $currentTime = date('Y-m-d H:i:s', strtotime($current));

                //Disable Product if already Enabled
                if ($product->getStatus() != Status::STATUS_DISABLED
                            || $product->getEnableFailureReport() != implode(",", $validationError)) {
                    $updatedProductData =
                        [
                            'status' => Status::STATUS_DISABLED,
                            'enable_failure_report' => implode(",", $validationError),
                            'disabled_at' => $currentTime,
                            'two_step_status_cron' => 1
                        ];
                    $this->productAction->updateAttributes([$_product->getId()], $updatedProductData, 0);
                    $this->log("SKU is disabled: " . $_product->getSku());
                    $this->disableParentProduct($_product->getId());
                }
                if ($product->getStatus() != Status::STATUS_DISABLED) {
                    $this->productPushHelper->updateCatalogServicePushData($_product->getId());
                }
            } else {
                //Enable Product
                if ($product->getStatus() != Status::STATUS_ENABLED || $product->getEnableFailureReport()) {
                    $updatedProductData =
                        [
                            'status' => Status::STATUS_ENABLED,
                            'enable_failure_report' => "",
                            'disabled_at' => "",
                            'two_step_status_cron' => 1
                        ];
                    $this->productAction->updateAttributes([$_product->getId()], $updatedProductData, 0);
                    $this->log("SKU is enabled: " . $_product->getSku());
                    $this->disableParentProduct($_product->getId());
                }
                if ($product->getStatus() != Status::STATUS_ENABLED) {
                    $this->productPushHelper->updateCatalogServicePushData($_product->getId());
                }
            }
            $this->updateProductCronFlag($_product->getId());
        } catch (\Exception $e) {
            $this->log("Something went wrong! Product Id: {$_product->getId()}, Error: " . $e->getMessage());
        }
    }

    /**
     * Disable parent product based on child is disabled.
     *
     * @param int $childId
     * @return void
     */
    public function disableParentProduct($childId)
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
                    AND attribute_code = 'status') WHERE type_id = 'simple' AND cpes.value=".Status::STATUS_ENABLED."
                    AND cpe.entity_id IN (".implode(',', $childIds).")";
                $result = $connection->fetchAll($query);
                if (count($result) == 0) {
                    if ($configProduct->getStatus() != Status::STATUS_DISABLED) {
                        $updatedProductData = ['status' => Status::STATUS_DISABLED];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->log("Parent SKU is disabled: " . $configProduct->getSku());
                    } else {
                        $this->log("Parent SKU is already disabled: " . $configProduct->getSku());
                    }
                } else {
                    if ($configProduct->getStatus() != Status::STATUS_ENABLED) {
                        $updatedProductData = ['status' => Status::STATUS_ENABLED];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->log("Parent SKU is enabled: " . $configProduct->getSku());
                    } else {
                        $this->log("Parent SKU is already enabled: " . $configProduct->getSku());
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
        $product->setData('two_step_status_cron', 1);
        $product->setData('store_id', 0);
        $product->getResource()->saveAttribute($product, 'two_step_status_cron');
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
        
        $logFileName = BP . '/var/log/product_enable.log';
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

    /**
     * Validate attribute which are required to enable.
     *
     * @return type
     */
    public function getEnableAttributes()
    {
        if (empty($this->enableAttributes)) {
            $configFields = $this->getConfig(self::XML_PATH_ENABLE_ATTRIBUTES);
            if ($configFields) {
                $fields = explode(",", $configFields);
                $this->enableAttributes = array_combine($fields, $fields);
            }
        }
        return $this->enableAttributes;
    }
}
