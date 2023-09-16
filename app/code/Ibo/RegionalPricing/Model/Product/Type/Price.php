<?php
namespace Ibo\RegionalPricing\Model\Product\Type;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\Store;
use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Catalog\Model\Product\Type\Price as CorePrice;
use Magento\Framework\App\Http\Context as HttpContext;
use Ibo\RegionalPricing\Model\PriceZoneManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ProductRepository;

/**
 * Product type price model
 * overriden to customize the price zone wise
 */
class Price extends CorePrice
{
    /**
     * Product price cache tag
     */
    const CACHE_TAG = 'PRODUCT_PRICE';

    /**
     * @var array
     */
    protected static $attributeCache = [];

    /**
     * Core event manager proxy
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager;

    /**
     * Customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_localeDate;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Rule factory
     *
     * @var \Magento\CatalogRule\Model\ResourceModel\RuleFactory
     */
    protected $_ruleFactory;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var GroupManagementInterface
     */
    protected $_groupManagement;

    /**
     * @var \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory
     */
    protected $tierPriceFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $config;

    /**
     * @var ProductTierPriceExtensionFactory
     */
    private $tierPriceExtensionFactory;

    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var ProductRepository
     */
    protected $_productRepository;

    /**
     * Constructor
     *
     * @param \Magento\CatalogRule\Model\ResourceModel\RuleFactory $ruleFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param GroupManagementInterface $groupManagement
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory $tierPriceFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param ProductTierPriceExtensionFactory|null $tierPriceExtensionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\CatalogRule\Model\ResourceModel\RuleFactory $ruleFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        PriceCurrencyInterface $priceCurrency,
        GroupManagementInterface $groupManagement,
        \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory $tierPriceFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null,
        HttpContext $httpContext,
        ResourceConnection $resources,
         ProductRepository $productRepository
    ) {
        $this->_ruleFactory = $ruleFactory;
        $this->_storeManager = $storeManager;
        $this->_localeDate = $localeDate;
        $this->_customerSession = $customerSession;
        $this->_eventManager = $eventManager;
        $this->priceCurrency = $priceCurrency;
        $this->_groupManagement = $groupManagement;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->config = $config;
        $this->tierPriceExtensionFactory = $tierPriceExtensionFactory ?: ObjectManager::getInstance()
            ->get(ProductTierPriceExtensionFactory::class);
        $this->httpContext = $httpContext;
        $this->resources = $resources;
        $this->connection = $this->resources->getConnection();
        $this->_productRepository = $productRepository;
    }

    /**
     * Default action to get price of product
     *
     * @param Product $product
     * @return float
     */
    public function getPrice($product)
    {
        return $product->getData('price');
    }

    /**
     * Get base price with apply Group, Tier, Special prises
     *
     * @param Product $product
     * @param float|null $qty
     *
     * @return float
     */
    public function getBasePrice($product, $qty = null)
    {
        $price = (float) $product->getPrice();

        return min(
            $this->_applyTierPrice($product, $qty, $price),
            $this->_applySpecialPrice($product, $price)
        );
    }

    /**
     * Retrieve product final price
     *
     * @param float|null $qty
     * @param Product $product
     * @return float
     */
    public function getFinalPrice($qty, $product)
    {
        if ($qty === null && $product->getCalculatedFinalPrice() !== null) {
            return $product->getCalculatedFinalPrice();
        }

        $finalPrice = $this->getBasePrice($product, $qty);
        $product->setFinalPrice($finalPrice);

        $this->_eventManager->dispatch('catalog_product_get_final_price', ['product' => $product, 'qty' => $qty]);

        $finalPrice = $product->getData('final_price');
        $finalPrice = $this->_applyOptionsPrice($product, $qty, $finalPrice);
        $finalPrice = max(0, $finalPrice);
        $product->setFinalPrice($finalPrice);

        return $finalPrice;
    }

    /**
     * Retrieve final price for child product
     *
     * @param Product $product
     * @param float $productQty
     * @param Product $childProduct
     * @param float $childProductQty
     * @return float
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getChildFinalPrice($product, $productQty, $childProduct, $childProductQty)
    {
        return $this->getFinalPrice($childProductQty, $childProduct);
    }

    /**
     * Gets the 'tier_price' array from the product
     *
     * @param Product $product
     * @param string $key
     * @param bool $returnRawData
     * @return array
     */
    protected function getExistingPrices($product, $key, $returnRawData = false)
    {
        $prices = $product->getData($key);

        if ($prices === null) {
            $attribute = $product->getResource()->getAttribute($key);
            if ($attribute) {
                $attribute->getBackend()->afterLoad($product);
                $prices = $product->getData($key);
            }
        }

        if ($prices === null || !is_array($prices)) {
            return ($returnRawData ? $prices : []);
        }

        return $prices;
    }

    /**
     * Returns the website to use for group or tier prices, based on the price scope setting
     *
     * @return int|mixed
     */
    protected function getWebsiteForPriceScope()
    {
        $websiteId = 0;
        $value = $this->config->getValue('catalog/price/scope', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
        if ($value != 0) {
            // use the website associated with the current store
            $websiteId = $this->_storeManager->getWebsite()->getId();
        }
        return $websiteId;
    }

    /**
     * Apply tier price for product if not return price that was before
     *
     * @param   Product $product
     * @param   float $qty
     * @param   float $finalPrice
     * @return  float
     */
    protected function _applyTierPrice($product, $qty, $finalPrice)
    {
        if ($qty === null) {
            return $finalPrice;
        }

        $tierPrice = $product->getTierPrice($qty);
        if (is_numeric($tierPrice)) {
            $finalPrice = min($finalPrice, (float) $tierPrice);
        }
        return $finalPrice;
    }

    /**
     * Get product tier price by qty
     *
     * @param   float $qty
     * @param   Product $product
     * @return  float|array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getTierPrice($qty, $product)
    {
        $allGroupsId = $this->getAllCustomerGroupsId();

        $prices = $this->getExistingPrices($product, 'tier_price', true);
        if ($prices === null || !is_array($prices)) {
            if ($qty !== null) {
                return $product->getPrice();
            } else {
                return [
                    [
                        'price' => $product->getPrice(),
                        'website_price' => $product->getPrice(),
                        'price_qty' => 1,
                        'cust_group' => $allGroupsId,
                    ]
                ];
            }
        }

        $custGroup = $this->_getCustomerGroupId($product);
        if ($qty) {
            $prevQty = 1;
            $prevPrice = $product->getPrice();
            $prevGroup = $allGroupsId;

            foreach ($prices as $price) {
                if ($price['cust_group'] != $custGroup && $price['cust_group'] != $allGroupsId) {
                    // tier not for current customer group nor is for all groups
                    continue;
                }
                if ($qty < $price['price_qty']) {
                    // tier is higher than product qty
                    continue;
                }
                if ($price['price_qty'] < $prevQty) {
                    // higher tier qty already found
                    continue;
                }
                if ($price['price_qty'] == $prevQty &&
                    $prevGroup != $allGroupsId &&
                    $price['cust_group'] == $allGroupsId) {
                    // found tier qty is same as current tier qty but current tier group is ALL_GROUPS
                    continue;
                }
                if ($price['website_price'] < $prevPrice) {
                    $prevPrice = $price['website_price'];
                    $prevQty = $price['price_qty'];
                    $prevGroup = $price['cust_group'];
                }
            }
            return $prevPrice;
        } else {
            $qtyCache = [];
            foreach ($prices as $priceKey => $price) {
                if ($price['cust_group'] != $custGroup && $price['cust_group'] != $allGroupsId) {
                    unset($prices[$priceKey]);
                } elseif (isset($qtyCache[$price['price_qty']])) {
                    $priceQty = $qtyCache[$price['price_qty']];
                    if ($prices[$priceQty]['website_price'] > $price['website_price']) {
                        unset($prices[$priceQty]);
                        $qtyCache[$price['price_qty']] = $priceKey;
                    } else {
                        unset($prices[$priceKey]);
                    }
                } else {
                    $qtyCache[$price['price_qty']] = $priceKey;
                }
            }
        }

        return $prices ?: [];
    }

    /**
     * Gets the CUST_GROUP_ALL id
     *
     * @return int
     */
    protected function getAllCustomerGroupsId()
    {
        // ex: 32000
        return $this->_groupManagement->getAllCustomersGroup()->getId();
    }

    /**
     * Gets list of product tier prices
     *
     * @param Product $product
     * @return \Magento\Catalog\Api\Data\ProductTierPriceInterface[]
     */
    public function getTierPrices($product)
    {
        $productRowId = $product->getRowId();
        $prices = [];
        $tierPrices = $this->getExistingPrices($product, 'tier_price');
        $customerGroup = $this->getCurrentCustomerGroup();
        if (!$product->getSkipZoneCheckFlag()) {
            /** logic added for zone wise customisation-(starts here) - mohit.pandit@embitel.com **/
            $isRegionalPricingActive = $this->config
                 ->getValue("regional_pricing/setting/active",
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
             $this->_storeManager->getStore()->getStoreId()
            );
            $zone = $this->httpContext->getValue(PriceZoneManagerInterface::CONTEXT_PRICE_ZONE);
            $defaultZone = $this->config
                                    ->getValue("regional_pricing/setting/default_zone",
                                            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                                            $this->_storeManager->getStore()->getStoreId()
                                      );
            $zoneKeys = [];
            if ($isRegionalPricingActive == 1) {
                $zoneKeys = array_keys(array_column($tierPrices, 'customer_zone'), $zone);
            }
            $defaultZoneKeys = array_keys(array_column($tierPrices, 'customer_zone'), $defaultZone);

            if(empty($tierPrices)) {
                $value = $this->getPriceById($productRowId);
                $tierPrice = $this->tierPriceFactory->create()
                        ->setExtensionAttributes($this->tierPriceExtensionFactory->create());
                $tierPrice->setCustomerGroupId($customerGroup);
                $tierPrice->setValue($value);
                $tierPrice->setQty(1);

                /** Added customer_zone as extension attribute in tier price **/
                $tierPrice->getExtensionAttributes()->setCustomerZone($zone);
                $websiteId = $this->getWebsiteForPriceScope();
                $tierPrice->getExtensionAttributes()->setWebsiteId($websiteId);
                $prices[] = $tierPrice;
            } else{
                foreach ($tierPrices as $key => $tiers) {
                    if (!empty($zoneKeys) && in_array($key,$zoneKeys)) {
                        continue;
                    }elseif(empty($zoneKeys) && !empty($defaultZoneKeys) && in_array($key,$defaultZoneKeys)){
                        continue;
                    }else{
                        unset($tierPrices[$key]);
                    }
                }
                $tierPrices = array_values($tierPrices);
                if(isset($tierPrices) && !empty($tierPrices)){
                    $firstTier = $tierPrices[0];
                    if ($firstTier['price_qty'] > 1)
                    {
                        $newTierPrice['price'] = 0;
                        $newTierPrice['price_qty'] = 1;
                        $newTierPrice['tier_flag'] = 1;
                        array_unshift($tierPrices, $newTierPrice);
                    }
                }
                foreach ($tierPrices as $key => $tiers) {
                    // ------------Get price by pricezone, customer group and qty----------------
                    /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice */
                    $tierPrice = $this->tierPriceFactory->create()
                        ->setExtensionAttributes($this->tierPriceExtensionFactory->create());
                    if (isset($tiers['tier_flag'])){
                        $defaultZoneSql = "SELECT `value` FROM `catalog_product_entity_tier_price` WHERE `entity_id` = $productRowId AND `customer_zone` = '$defaultZone' AND `customer_group_id` = $customerGroup AND `qty`= 1" ;
                        $defaultZoneResultVal = $this->connection->fetchAll($defaultZoneSql);
                        if(!empty($defaultZoneResultVal)) {
                           $value = $defaultZoneResultVal[0]['value'];
                        }else{
                           $value = $this->getPriceById($productRowId);
                        }
                        $tierPrice->setValue($value);
                        $tierPrice->setQty($tiers['price_qty']);
                    } else {
                        $value = $tiers['price'];
                        $tierPrice->setValue($value);
                        $tierPrice->setQty($tiers['price_qty']);
                    }
                    $tierPrice->setCustomerGroupId($customerGroup);
                    /** Added customer_zone as extension attribute in tier price **/
                    if (isset($tiers['customer_zone'])) {
                        $tierPrice->getExtensionAttributes()->setCustomerZone($tiers['customer_zone']);
                    }

                    if (isset($tiers['percentage_value'])) {
                        $tierPrice->getExtensionAttributes()->setPercentageValue($tiers['percentage_value']);
                    }
                    $websiteId = isset($tiers['website_id']) ? $tiers['website_id'] : $this->getWebsiteForPriceScope();
                    $tierPrice->getExtensionAttributes()->setWebsiteId($websiteId);
                    $prices[] = $tierPrice;
                    // ----------- End get price by pricezone, customer group and qty------------------
                }
            }
            /** logic added for zone wise customisation-(ends here) - mohit.pandit@embitel.com **/
        }
        else{
            foreach ($tierPrices as $key => $price) {
                /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice */
                $tierPrice = $this->tierPriceFactory->create()
                    ->setExtensionAttributes($this->tierPriceExtensionFactory->create());
                $tierPrice->setCustomerGroupId($price['cust_group']);
                if (array_key_exists('website_price', $price)) {
                    $value = $price['website_price'];
                } else {
                    $value = $price['price'];
                }
                $tierPrice->setValue($value);
                $tierPrice->setQty($price['price_qty']);
                /** Added customer_zone as extension attribute in tier price **/
                if (isset($price['customer_zone'])) {
                    $tierPrice->getExtensionAttributes()->setCustomerZone($price['customer_zone']);
                }
                if (isset($price['percentage_value'])) {
                    $tierPrice->getExtensionAttributes()->setPercentageValue($price['percentage_value']);
                }
                $websiteId = isset($price['website_id']) ? $price['website_id'] : $this->getWebsiteForPriceScope();
                $tierPrice->getExtensionAttributes()->setWebsiteId($websiteId);
                $prices[] = $tierPrice;
            }
        }
        return $prices;
    }


    public function getPriceById($prodId)
    {
        $product = $this->_productRepository->getById($prodId);
        $productPriceById = $product->getPrice();
        return $productPriceById;
    }

    /**
     * Sets list of product tier prices
     *
     * @param Product $product
     * @param \Magento\Catalog\Api\Data\ProductTierPriceInterface[] $tierPrices
     * @return $this
     */
    public function setTierPrices($product, array $tierPrices = null)
    {
        // null array means leave everything as is
        if ($tierPrices === null) {
            return $this;
        }

        $allGroupsId = $this->getAllCustomerGroupsId();
        $websiteId = $this->getWebsiteForPriceScope();

        // build the new array of tier prices
        $prices = [];
        foreach ($tierPrices as $price) {
            $extensionAttributes = $price->getExtensionAttributes();
            $priceWebsiteId = $websiteId;
            if (isset($extensionAttributes) && is_numeric($extensionAttributes->getWebsiteId())) {
                $priceWebsiteId = (string)$extensionAttributes->getWebsiteId();
            }
            $prices[] = [
                'website_id' => $priceWebsiteId,
                'cust_group' => $price->getCustomerGroupId(),
                'website_price' => $price->getValue(),
                'price' => $price->getValue(),
                'all_groups' => ($price->getCustomerGroupId() == $allGroupsId),
                'price_qty' => $price->getQty(),
                'percentage_value' => $extensionAttributes ? $extensionAttributes->getPercentageValue() : null
            ];
        }
        $product->setData('tier_price', $prices);

        return $this;
    }

    /**
     * Retrieve customer group id from product
     *
     * @param Product $product
     * @return int
     */
    protected function _getCustomerGroupId($product)
    {
        if ($product->getCustomerGroupId() !== null) {
            return $product->getCustomerGroupId();
        }
        return $this->_customerSession->getCustomerGroupId();
    }

    /**
     * Apply special price for product if not return price that was before
     *
     * @param   Product $product
     * @param   float $finalPrice
     * @return  float
     */
    protected function _applySpecialPrice($product, $finalPrice)
    {
        return $this->calculateSpecialPrice(
            $finalPrice,
            $product->getSpecialPrice(),
            $product->getSpecialFromDate(),
            $product->getSpecialToDate(),
            WebsiteInterface::ADMIN_CODE
        );
    }

    /**
     * Count how many tier prices we have for the product
     *
     * @param   Product $product
     * @return  int
     */
    public function getTierPriceCount($product)
    {
        $price = $product->getTierPrice();
        return count($price);
    }

    /**
     * Get formatted by currency tier price
     *
     * @param   float $qty
     * @param   Product $product
     *
     * @return  array|float
     * @since 102.0.6
     */
    public function getFormattedTierPrice($qty, $product)
    {
        $price = $product->getTierPrice($qty);
        if (is_array($price)) {
            foreach (array_keys($price) as $index) {
                $price[$index]['formatted_price'] = $this->priceCurrency->convertAndFormat(
                    $price[$index]['website_price']
                );
            }
        } else {
            $price = $this->priceCurrency->format($price);
        }

        return $price;
    }

    /**
     * Get formatted by currency tier price
     *
     * @param float $qty
     * @param Product $product
     *
     * @return array|float
     *
     * @deprecated 102.0.6
     * @see getFormattedTierPrice()
     */
    public function getFormatedTierPrice($qty, $product)
    {
        return $this->getFormattedTierPrice($qty, $product);
    }

    /**
     * Get formatted by currency product price
     *
     * @param   Product $product
     * @return  array|float
     * @since 102.0.6
     */
    public function getFormattedPrice($product)
    {
        return $this->priceCurrency->format($product->getFinalPrice());
    }

    /**
     * Get formatted by currency product price
     *
     * @param Product $product
     * @return array || float
     *
     * @deprecated 102.0.6
     * @see getFormattedPrice()
     */
    public function getFormatedPrice($product)
    {
        return $this->getFormattedPrice($product);
    }

    /**
     * Apply options price
     *
     * @param Product $product
     * @param int $qty
     * @param float $finalPrice
     * @return float
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _applyOptionsPrice($product, $qty, $finalPrice)
    {
        $optionIds = $product->getCustomOption('option_ids');
        if ($optionIds) {
            $basePrice = $finalPrice;
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                if ($option = $product->getOptionById($optionId)) {
                    $confItemOption = $product->getCustomOption('option_' . $option->getId());

                    $group = $option->groupFactory($option->getType())
                        ->setOption($option)
                        ->setConfigurationItemOption($confItemOption);
                    $finalPrice += $group->getOptionPrice($confItemOption->getValue(), $basePrice);
                }
            }
        }

        return $finalPrice;
    }

    /**
     * Calculate product price based on special price data and price rules
     *
     * @param   float $basePrice
     * @param   float $specialPrice
     * @param   string $specialPriceFrom
     * @param   string $specialPriceTo
     * @param   bool|float|null $rulePrice
     * @param   mixed|null $wId
     * @param   integer|null $gId
     * @param   int|null $productId
     * @return  float
     */
    public function calculatePrice(
        $basePrice,
        $specialPrice,
        $specialPriceFrom,
        $specialPriceTo,
        $rulePrice = false,
        $wId = null,
        $gId = null,
        $productId = null
    ) {
        \Magento\Framework\Profiler::start('__PRODUCT_CALCULATE_PRICE__');
        if ($wId instanceof Store) {
            $sId = $wId->getId();
            $wId = $wId->getWebsiteId();
        } else {
            $sId = $this->_storeManager->getWebsite($wId)->getDefaultGroup()->getDefaultStoreId();
        }

        $finalPrice = $basePrice;

        $finalPrice = $this->calculateSpecialPrice(
            $finalPrice,
            $specialPrice,
            $specialPriceFrom,
            $specialPriceTo,
            WebsiteInterface::ADMIN_CODE
        );

        if ($rulePrice === false) {
            $date = $this->_localeDate->scopeDate($sId);
            $rulePrice = $this->_ruleFactory->create()->getRulePrice($date, $wId, $gId, $productId);
        }

        if ($rulePrice !== null && $rulePrice !== false) {
            $finalPrice = min($finalPrice, $rulePrice);
        }

        $finalPrice = max($finalPrice, 0);
        \Magento\Framework\Profiler::stop('__PRODUCT_CALCULATE_PRICE__');
        return $finalPrice;
    }

    /**
     * Calculate and apply special price
     *
     * @param float $finalPrice
     * @param float $specialPrice
     * @param string $specialPriceFrom
     * @param string $specialPriceTo
     * @param int|string|Store $store
     * @return float
     */
    public function calculateSpecialPrice(
        $finalPrice,
        $specialPrice,
        $specialPriceFrom,
        $specialPriceTo,
        $store = null
    ) {
        if ($specialPrice !== null && $specialPrice != false) {
            if ($this->_localeDate->isScopeDateInInterval($store, $specialPriceFrom, $specialPriceTo)) {
                $finalPrice = min($finalPrice, (float) $specialPrice);
            }
        }
        return $finalPrice;
    }

    /**
     * Check is tier price value fixed or percent of original price
     *
     * @return bool
     */
    public function isTierPriceFixed()
    {
        return true;
    }

    public function getCurrentCustomerGroup() {
        /* If customer group id pass in pdp request body*/
        $iboCustomerGroupId = $this->httpContext->getValue('ibo_customer_group_id');
        if(!empty($iboCustomerGroupId)) {
            return $iboCustomerGroupId;
        }
       if($this->_customerSession->isLoggedIn()){
            $customerGroupId = $this->_customerSession->getCustomer()->getGroupId();
            return $customerGroupId;
        }
        else{
            return $this->getDefaultGroupName();
        }
    }

    public function getDefaultGroupName()
    {
        $defaultGroupId = $this->config->getValue("customer/create_account/default_group");
        return $defaultGroupId;
    }
}
