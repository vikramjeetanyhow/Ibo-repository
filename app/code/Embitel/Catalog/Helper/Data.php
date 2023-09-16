<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Catalog\Helper;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;

/**
 * Catalog data helper
 *
 * @api
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PRICE_SCOPE_GLOBAL = 0;

    const PRICE_SCOPE_WEBSITE = 1;

    const XML_PATH_PRICE_SCOPE = 'catalog/price/scope';

    const CONFIG_USE_STATIC_URLS = 'cms/wysiwyg/use_static_urls_in_catalog';

    /**
     * @deprecated
     * @see \Magento\Catalog\Helper\Output::isDirectivesExists
     */
    const CONFIG_PARSE_URL_DIRECTIVES = 'catalog/frontend/parse_url_directives';

    const XML_PATH_DISPLAY_PRODUCT_COUNT = 'catalog/layered_navigation/display_product_count';

    /**
     * Cache context
     */
    const CONTEXT_CATALOG_SORT_DIRECTION = 'catalog_sort_direction';

    const CONTEXT_CATALOG_SORT_ORDER = 'catalog_sort_order';

    const CONTEXT_CATALOG_DISPLAY_MODE = 'catalog_mode';

    const CONTEXT_CATALOG_LIMIT = 'catalog_limit';

    /**
     * Breadcrumb Path cache
     *
     * @var array
     */
    protected $_categoryPath;

    /**
     * Currently selected store ID if applicable
     *
     * @var int
     */
    protected $_storeId;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * Catalog product
     *
     * @var Product
     */
    protected $_catalogProduct;

    /**
     * Catalog category
     *
     * @var Category
     */
    protected $_catalogCategory;

    /**
     * @var \Magento\Framework\Stdlib\StringUtils
     */
    protected $string;

    /**
     * @var string
     */
    protected $_templateFilterModel;

    /**
     * Catalog session
     *
     * @var \Magento\Catalog\Model\Session
     */
    protected $_catalogSession;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Template filter factory
     *
     * @var \Magento\Catalog\Model\Template\Filter\Factory
     */
    protected $_templateFilterFactory;

    /**
     * Tax class key factory
     *
     * @var \Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory
     */
    protected $_taxClassKeyFactory;

    /**
     * Tax helper
     *
     * @var \Magento\Tax\Model\Config
     */
    protected $_taxConfig;

    /**
     * Quote details factory
     *
     * @var \Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory
     */
    protected $_quoteDetailsFactory;

    /**
     * Quote details item factory
     *
     * @var \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory
     */
    protected $_quoteDetailsItemFactory;

    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    /**
     * Tax calculation service interface
     *
     * @var \Magento\Tax\Api\TaxCalculationInterface
     */
    protected $_taxCalculationService;

    /**
     * Price currency
     *
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $customerGroupRepository;

    /**
     * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
     */
    protected $addressFactory;

    /**
     * @var \Magento\Customer\Api\Data\RegionInterfaceFactory
     */
    protected $regionFactory;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Session $catalogSession
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param Category $catalogCategory
     * @param Product $catalogProduct
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Catalog\Model\Template\Filter\Factory $templateFilterFactory
     * @param string $templateFilterModel
     * @param \Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory $taxClassKeyFactory
     * @param Config $taxConfig
     * @param \Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory $quoteDetailsFactory
     * @param \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory
     * @param \Magento\Tax\Api\TaxCalculationInterface $taxCalculationService
     * @param CustomerSession $customerSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository
     * @param \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory
     * @param \Magento\Customer\Api\Data\RegionInterfaceFactory $regionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalculationService, 
        \Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory $quoteDetailsFactory,
        \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory,
        \Magento\Customer\Api\GroupRepositoryInterface $customerGroupRepository,
        \Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory $taxClassKeyFactory,
        CustomerSession $customerSession,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\Data\RegionInterfaceFactory $regionFactory,
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig,
        DateTimeFactory $dateTime
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->_taxCalculationService = $taxCalculationService;  
        $this->_quoteDetailsFactory = $quoteDetailsFactory;
        $this->_quoteDetailsItemFactory = $quoteDetailsItemFactory;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->_taxClassKeyFactory = $taxClassKeyFactory; 
        $this->_customerSession = $customerSession;
        $this->_taxConfig = $taxConfig;
        $this->_storeManager = $storeManager;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;   
        $this->resource = $resource;
        $this->scopeConfig = $scopeConfig; 
        $this->dateTime = $dateTime;    
        parent::__construct($context);
    }

    public function taxPriceCurrency($price)
    {
        return $this->priceCurrency->convertAndRound($price);
    }

    /**
     * Convert tax address array to address data object with country id and postcode
     *
     * @param array $taxAddress
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    private function convertDefaultTaxAddress(array $taxAddress = null)
    {
        if (empty($taxAddress)) {
            return null;
        }
        /** @var \Magento\Customer\Api\Data\AddressInterface $addressDataObject */
        $addressDataObject = $this->addressFactory->create()
            ->setCountryId($taxAddress['country_id'])
            ->setPostcode($taxAddress['postcode']);

        if (isset($taxAddress['region_id'])) {
            $addressDataObject->setRegion($this->regionFactory->create()->setRegionId($taxAddress['region_id']));
        }
        return $addressDataObject;
    }

    /**
     * Get product price with all tax settings processing
     *
     * @param   \Magento\Catalog\Model\Product $product
     * @param   float $price inputted product price
     * @param   bool $includingTax return price include tax flag
     * @param   null|\Magento\Customer\Model\Address\AbstractAddress $shippingAddress
     * @param   null|\Magento\Customer\Model\Address\AbstractAddress $billingAddress
     * @param   null|int $ctc customer tax class
     * @param   null|string|bool|int|\Magento\Store\Model\Store $store
     * @param   bool $priceIncludesTax flag what price parameter contain tax
     * @param   bool $roundPrice
     * @return  float
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getTaxPrice(
        $product,
        $price,
        $includingTax = null,
        $shippingAddress = null,
        $billingAddress = null,
        $ctc = null,
        $store = null,
        $priceIncludesTax = null,
        $roundPrice = true
    ) {
        if (!$price) {
            return $price;
        }

        $store = $this->_storeManager->getStore($store);
        //if ($this->_taxConfig->needPriceConversion($store)) {
            if ($priceIncludesTax === null) {
                $priceIncludesTax = $this->_taxConfig->priceIncludesTax($store);
            }

            $shippingAddressDataObject = null;
            if ($shippingAddress === null) {
                $shippingAddressDataObject =
                    $this->convertDefaultTaxAddress($this->_customerSession->getDefaultTaxShippingAddress());
            } elseif ($shippingAddress instanceof \Magento\Customer\Model\Address\AbstractAddress) {
                $shippingAddressDataObject = $shippingAddress->getDataModel();
            }

            $billingAddressDataObject = null;
            if ($billingAddress === null) {
                $billingAddressDataObject =
                    $this->convertDefaultTaxAddress($this->_customerSession->getDefaultTaxBillingAddress());
            } elseif ($billingAddress instanceof \Magento\Customer\Model\Address\AbstractAddress) {
                $billingAddressDataObject = $billingAddress->getDataModel();
            }

            $taxClassKey = $this->_taxClassKeyFactory->create();
            $taxClassKey->setType(TaxClassKeyInterface::TYPE_ID)
                ->setValue($product->getTaxClassId());

            if ($ctc === null && $this->_customerSession->getCustomerGroupId() != null) {
                $ctc = $this->customerGroupRepository->getById($this->_customerSession->getCustomerGroupId())
                    ->getTaxClassId();
            }

            $customerTaxClassKey = $this->_taxClassKeyFactory->create();
            $customerTaxClassKey->setType(TaxClassKeyInterface::TYPE_ID)
                ->setValue($ctc);

            $item = $this->_quoteDetailsItemFactory->create();
            $item->setQuantity(1)
                ->setCode($product->getSku())
                ->setShortDescription($product->getShortDescription())
                ->setTaxClassKey($taxClassKey)
                ->setIsTaxIncluded($priceIncludesTax)
                ->setType('product')
                ->setUnitPrice($price);

            $quoteDetails = $this->_quoteDetailsFactory->create();
            $quoteDetails->setShippingAddress($shippingAddressDataObject)
                ->setBillingAddress($billingAddressDataObject)
                ->setCustomerTaxClassKey($customerTaxClassKey)
                ->setItems([$item])
                ->setCustomerId($this->_customerSession->getCustomerId());

            $storeId = null;
            if ($store) {
                $storeId = $store->getId();
            }
            $taxDetails = $this->_taxCalculationService->calculateTax($quoteDetails, $storeId, $roundPrice);
            $items = $taxDetails->getItems();
            $taxDetailsItem = array_shift($items);

            if ($includingTax !== null) {
                if ($includingTax) {
                    $price = $taxDetailsItem->getPriceInclTax();
                } else {
                    $price = $taxDetailsItem->getPrice();
                }
            } else {
                switch ($this->_taxConfig->getPriceDisplayType($store)) {
                    case Config::DISPLAY_TYPE_EXCLUDING_TAX:
                    case Config::DISPLAY_TYPE_BOTH:
                        $price = $taxDetailsItem->getPrice();
                        break;
                    case Config::DISPLAY_TYPE_INCLUDING_TAX:
                        $price = $taxDetailsItem->getPriceInclTax();
                        break;
                    default:
                        break;
                }
            }
        //}

        if ($roundPrice) {
            return $this->priceCurrency->round($price);
        } else {
            return $price;
        }
    }

    public function updateSeldate($sku, $prodAttribute = []) {
        
        $selUpdateEnable = $this->scopeConfig->getValue("sel_label_setting/product_sel_dates/sel_enabled");
        if($selUpdateEnable) {
            $this->addLog('=====Entered Update Sel date function for : '.$sku.' ====');
            $allowedAttribute = $this->scopeConfig->getValue("sel_label_setting/product_sel_dates/product_attributes");
            $this->addLog('Product Attribute :'.print_r($prodAttribute,true)); 
            $this->addLog('Allowed Product Attributes: '.$allowedAttribute);
            $allowedAttributes = [];
            if($allowedAttribute != '') {
            $allowedAttribute = explode(',',$allowedAttribute);
                foreach($allowedAttribute as $key=>$value) {
                    $allowedAttributes[$value] = $value;
                }
            }
            $this->addLog(print_r($allowedAttributes,true)); 
            $selUpdateAttributes = count(array_intersect_key($prodAttribute,$allowedAttributes));
        
            $this->addLog('Sel date update feature is enabled');
            //$this->addLog('Update Product Attribute: '.$prodAttribute);
            if($selUpdateAttributes > 0) {
                $this->addLog('Product Attribute exists in the allowed attributes');
                $dateModel = $this->dateTime->create();
                $current = $dateModel->gmtDate();
                $currentTime = date('Y-m-d H:i:s', strtotime($current));
                $tableName = "catalog_product_entity";
                $connection = $this->resource->getConnection();
                $data = ["sel_updated_at"=>$currentTime]; // Key_Value Pair
                $where = $connection->quoteInto('sku = ?', $sku);
                $connection->update($tableName, $data,$where);
            } else {
                $this->addLog('Attribute is not exist in the allowed attributes');
            }
            $this->addLog('===Sel date updated ======');
       }
   }

   public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = 1;
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/sel_date_update.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

}
