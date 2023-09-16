<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Disable;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

/**
 * This class is to handle product import
 */
class ProductDisableHandler
{
    const MERCHANDISING_CATEGORY_NAME = 'Merchandising Category';
    const NAVIGATION_CATEGORY_NAME = 'Navigation Category';
    const BRAND_CATEGORY_NAME = 'Brand';
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
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

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

    protected $products = [];

    protected $configurableMethod = null;
    

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
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
     * @var \TaxCalculationInterface
     */
    protected $taxCalculation;
    
    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;
    
    protected $enableAttributes = [];

    /**
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFieldProcessor $productFieldProcessor
     * @param CsvProcessor $csvProcessor
     * @param ResourceConnection $resourceConnection
     * @param string $storeManagerInterface
     * @param string $scopeConfigInterface
     * @param string $transportBuilder
     * @param Configurable $configurableType
     * @param ProductAction $productAction
     * @param DateTimeFactory $dateTime
     * @param TaxCalculationInterface $taxCalculation
     * @param GroupRepositoryInterface $groupRepository
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        ProductFieldProcessor $productFieldProcessor,
        CsvProcessor $csvProcessor,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        Configurable $configurableType,
        ProductAction $productAction,
        DateTimeFactory $dateTime,
        TaxCalculationInterface $taxCalculation,
        GroupRepositoryInterface $groupRepository,
        ProductPushHelper $productPushHelper
    ) {
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->csvProcessor = $csvProcessor;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->storeManager = $storeManagerInterface;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->configurableType = $configurableType;
        $this->productAction = $productAction;
        $this->dateTime = $dateTime;
        $this->taxCalculation = $taxCalculation;
        $this->groupRepository = $groupRepository;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Import Products from CSV file
     *
     * @param array $file file info retrieved from $_FILES array
     * @return array
     * @throws LocalizedException
     */
    public function disableFromCsvFile($file)
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
            $this->disableProducts($filteredCsvData['success']);
        }

        return $this->csvProcessor->prepareResponse($this->products);
    }

    /**
     * Disable product by sku.
     *
     * @param type $rows
     */
    private function disableProducts($rows)
    {
        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $currentTime = date('Y-m-d H:i:s', strtotime($current));
        
        $defaultZone = $this->getConfig(self::DEFAULT_ZONE);
        $defaultCustomerGroupId = $this->getConfig(self::DEFAULT_CUSTOMER_GROUP);
        $defaultCustomerGroupName = $this->getGroupName($defaultCustomerGroupId);
        
        foreach ($rows as $row) {
            try {
                $validationError = [];
                $product = $this->productFactory->create()->loadByAttribute('sku', $row['sku']);
                if (!$product) {
                    $row['error'] = "Product doesn't exist with sku:" . $row['sku'];
                    $this->products['failure'][] = $row;
                    continue;
                }
                
                if (isset($row['disable']) && strtolower($row['disable']) == 'yes') {
                    if (!isset($row['reason']) || $row['reason'] == "") {
                        $row['error'] = "Provide valid reason for manually disable the product.";
                        $this->products['failure'][] = $row;
                        continue;
                    }
                    
                    //Disable Product if already Enabled
                    $updatedProductData =
                    [
                        'status' => Status::STATUS_DISABLED,
                        'manual_disable' => 1,
                        'manual_disable_reason' => $row['reason'],
                        'disabled_at' => $currentTime
                    ];
                    $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                    $this->products['success'][] = $row;
                    $this->productFieldProcessor->log("SKU is manually disabled : " . $row['sku']);
                    $this->updateProductCronFlag($product->getId());
                    $this->productPushHelper->updateCatalogServicePushData($product->getId());
                    $this->disableParentProduct($product->getId());
                    continue;
                }

                //Check if tax class is set
                if ($product->getTaxClassId() == 0) {
                    $validationError[] = "Tax Slab Not Available.";
                }

                //Check if default customer group 1 quantity price doesn't exist
                $defaultTierPrice = false;

                $productRateId = $product->getTaxClassId();
                $product->setSkipZoneCheckFlag(true);
                $defaultTierPriceValue = round($product->getPrice(), 2);
                $rate = $this->taxCalculation->getCalculatedRate($productRateId);
                $productMrp = floatval(preg_replace('/[^\d.]/', '', trim($product->getMrp())));

                //Check if tier prices greater than the MRP
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
                
                if (!empty($validationError)) {
                    $row['error'] = implode(",", $validationError);
                    $this->products['failure'][] = $row;

                    //Disable Product if already Enabled
                    if ($product->getStatus() != Status::STATUS_DISABLED
                            || $product->getEnableFailureReport() != implode(",", $validationError)) {
                        $updatedProductData =
                            [
                                'status' => Status::STATUS_DISABLED,
                                'enable_failure_report' => implode(",", $validationError),
                                'disabled_at' => $currentTime
                            ];
                        $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("SKU is disabled: " . $row['sku']);
                        $this->disableParentProduct($product->getId());
                    }
                } else {
                    //Enable Product
                    if ($product->getStatus() != Status::STATUS_ENABLED || $product->getEnableFailureReport()) {
                        $updatedProductData =
                            [
                                'status' => Status::STATUS_ENABLED,
                                'enable_failure_report' => "",
                                'disabled_at' => "",
                                'manual_disable' => 0,
                                'manual_disable_reason' => ""
                            ];
                        $this->productAction->updateAttributes([$product->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("SKU is enabled: " . $row['sku']);
                        $this->disableParentProduct($product->getId());
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
                        $this->productFieldProcessor->log("Parent SKU is disabled: " . $configProduct->getSku());
                    } else {
                        $this->productFieldProcessor->log("Parent SKU is already disabled: " . $configProduct->getSku());
                    }
                } else {
                    if ($configProduct->getStatus() != Status::STATUS_ENABLED) {
                        $updatedProductData = ['status' => Status::STATUS_ENABLED];
                        $this->productAction->updateAttributes([$configProduct->getId()], $updatedProductData, 0);
                        $this->productFieldProcessor->log("Parent SKU is enabled: " . $configProduct->getSku());
                    } else {
                        $this->productFieldProcessor->log("Parent SKU is already enabled: " . $configProduct->getSku());
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
}
