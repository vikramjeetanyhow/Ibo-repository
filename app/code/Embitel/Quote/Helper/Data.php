<?php

namespace Embitel\Quote\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;
use Magento\Directory\Model\CountryFactory;
use Magento\SalesRule\Model\RuleFactory;
use Ibo\Emailer\Api\SendGridMailInterface;
use Magento\Framework\App\ResourceConnection;
use Ibo\Order\Model\IR\Order as IROrderModel;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Magento\CustomerGraphQl\Model\Customer\UpdateCustomerAccount;


class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    CONST FRACTION = 10000;
    /**
     * @var RuleFactory
    */
    private $SendGridMail;
    private $rule;
    /**
    * @var ProductRepositoryInterface
    */
    private $productRepository;
    private $groupRepository;
    protected $scopeConfig;
    private $countryFactory;
    protected $curl;

    protected $logger;

    protected $accountManagement;
    protected $orderRepository;
    protected $storeManager;
    protected $_orderCollectionFactory;
    protected $orderRepositorycollection;
    public $productCategory;
    protected $customerRepository;
    private $attributeCollection;
    /**
     * @var \Magento\Store\Model\App\Emulation
    */
    protected $appEmulation;

    protected $resourceConnection;

    private $connection;
    private $emailhelper;
    /**
     * @var IROrderModel
     */
    private $irOrderModel;
    private $validateMobile;
 
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        SendGridMailInterface $SendGridMail,
        Curl $curl,
        CountryFactory $countryFactory,
        ProductCategoryList $productCategory,
        CartRepositoryInterface $cartInterface,
        AttributeCollection $attributeCollection,
        groupRepository $groupRepository,
        RuleFactory $rule,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Sales\Api\Data\OrderInterface $orderRepositorycollection,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Embitel\ProductImport\Model\Import\ProductFieldProcessor $productFieldProcessor,
        \Embitel\ProductImport\Model\CategoryProcessor $CategoryProcessor,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        AccountManagementInterface $accountManagement,
        ResourceConnection $resourceConnection,
        \Ibo\Emailer\Helper\Email $emailhelper,
        IROrderModel $irOrderModel,
        ValidateMobile $validateMobile,
        \Ibo\Emailer\Api\Data\EmailInterface $email_info,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        UpdateCustomerAccount $updateCustomerAccount,
        \Anyhow\SupermaxPos\Helper\Data $posHelper,
        \Magento\Catalog\Model\CategoryFactory $categoryCollection
    
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->SendGridMail = $SendGridMail;
        $this->emailhelper = $emailhelper;
        $this->email_info = $email_info;
        $this->curl = $curl;
        $this->rule = $rule;
        $this->countryFactory = $countryFactory;
        $this->appEmulation = $appEmulation;
        $this->cartInterface = $cartInterface;
        $this->OrderInterface = $orderRepositorycollection;
        $this->customerRepository = $customerRepository;
        $this->transportBuilder = $transportBuilder;
        $this->orderRepository = $orderRepository;
        $this->attributeCollection = $attributeCollection;
        $this->accountManagement = $accountManagement;
        $this->inlineTranslation = $inlineTranslation;
        $this->groupRepository = $groupRepository;
        $this->imageHelper = $imageHelper;
        $this->productCategory = $productCategory;
        $this->storeManager = $storeManager;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->categoryProcessor = $CategoryProcessor;
        $this->categoryRepository = $categoryRepository;
        $this->eavConfig = $eavConfig;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->irOrderModel = $irOrderModel;
        $this->validateMobile = $validateMobile;
        $this->resoruceConnection();
        $this->timezone = $timezone;
        $this->updateCustomerAccount = $updateCustomerAccount;
        $this->posHelper = $posHelper;
        $this->categoryCollection = $categoryCollection;
    }

    public function getClientId()
    {
        $clientId = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_client_id");
        return $clientId;
    }
    public function getPromiseHostURL()
    {
        $promiseApi = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_host_url");
        return $promiseApi;
    }
    public function getPromiseApi()
    {
        $promiseApi = $this->getPromiseHostURL()."promise/";
        return $promiseApi;
    }
    public function getCartPromiseApi()
    {
        $promiseApi = $this->getPromiseHostURL()."fulfillment-options/";
        return $promiseApi;
    }

    public function getTraceId()
    {
        $traceId = substr(str_shuffle("abcdefghijklmnopqrstvwxyz"), 0, 3);
        return $traceId;
    }

    public function getPromiseStatus()
    {
        $status = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_status");
        return $status;
    }
    public function getDeliveryTogether()
    {
        $status = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_delivery_together");
        return $status;
    }

    public function getDefaultShippingPostalCode()
    {
        $clientId = $this->_scopeConfig->getValue("shipping/origin/postcode");
        return $clientId;
    }

    public function getCodMaxValue()
    {
        $maxValue = $this->_scopeConfig->getValue("payment/cashondelivery/max_order_total");
        return $maxValue;
    }
    public function getCodStatus()
    {
        $status = $this->_scopeConfig->getValue("payment/cashondelivery/active");
        return $status;
    }

    public function getDefaultCustomerGroup()
    {
        $groupId = $this->_scopeConfig->getValue("customer/create_account/default_group");
        return $groupId;
    }

    public function processQuantityCheck($cart, $pincode)
    {
        $data = $this->CurlExecute($cart, $pincode);
        return $data;
    }

    public function getFulFillmentApiEndPoint()
    {
        $status = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_order_status");
        return $status;
    }

    public function getPincodeServiceCheckAPI()
    {
        $pinCodeCheckUrl = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_pincode_service_check");
        return $pinCodeCheckUrl;
    }
    public function getPaymentApiUrl()
    {
        $paymentUrl = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_payment_api_url");
        return $paymentUrl;
    }
    public function getPaymentIntentToken()
    {
        $paymentToken = $this->_scopeConfig->getValue("promise_engine/promise_engine_settings/promise_engine_payment_token");
        return $paymentToken;
    }
    public function getPaymentIntentTokenAlt()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->get('Magento\Variable\Model\Variable')->loadByCode('promise_engine_payment_token');
        $paymentToken = $model->getPlainValue();
        return $paymentToken;
    }
    public function RequestJsonData($cart, $pincode, $reqitems, $flag = NULL)
    {
        $this->addLog("RequestJsonData ===> If customer is having the default shippingAddress - LoggedIn. And Flag = ".$flag." \r\n", "requestData.log");
        if($cart->getCustomer()->getId()) {
            $customerId = $cart->getCustomer()->getId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            $customerEmail = $customer->getEmail();
        } else {
            $groupId = $this->getDefaultCustomerGroup();
            $customerGroup = $this->getGroupName($groupId);
            $customerId = '';
            $customerEmail = '';
        }

        $dataArray = [];
        $dataArray['cart_id'] = $cart->getId();
        $dataArray['order_number'] = ($cart->getReservedOrderId())?$cart->getReservedOrderId():$cart->getId();
        $dataArray['channel'] = "APP";
        $dataArray['order_type'] = 'CUSTOMER-ORDER';
        $dataArray['promise_type'] = 'FWD';
        $dataArray['customer_id'] = $customerId;
        $dataArray['customer_group'] = $customerGroup;
        $dataArray['customer_address']['country'] = 'IN';
        $dataArray['customer_address']['post_code'] = $pincode;
        $dataArray['customer_address']['geo_location']['latitude'] = '';
        $dataArray['customer_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['country'] = 'IN';
        $dataArray['shipping_address']['post_code'] = $pincode;
        $dataArray['shipping_address']['geo_location']['latitude'] = '';
        $dataArray['shipping_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['email_id'] = $customerEmail;
        $dataArray['shipping_address']['phone_number']['country_code'] = '';
        $dataArray['shipping_address']['phone_number']['number'] = '';
        $dataArray['cart_value']['cent_amount'] = $cart->getGrandTotal()*self::FRACTION;
        $dataArray['cart_value']['fraction'] = self::FRACTION;
        $dataArray['cart_value']['currency'] = 'INR';
        $tempArray = [];
        if(is_array($reqitems) && count($reqitems) > 0){
            $reqitem = reset($reqitems);
        }
        $items = $cart->getAllItems();
        if($flag == 1){
            $items = $reqitems;
        }
        $i = 0;
        foreach ($items as $item) {
            if(!empty($reqitem['sku']) && $reqitem['sku'] == $item['sku']){
                $item['qty'] = $reqitem['qty'];
            }
            $product = $this->productRepository->get($item['sku'], false, null, true);

            $unitePrice = $product->getPrice()*self::FRACTION;

            $categoryIds = "";
            if(!empty($product->getIboCategoryId())) {
                $categoryIds = $product->getIboCategoryId();
            } else {
                $levelOne = $product->getDepartment();
                $levelTwo = $product->getClass();
                $levelThree = $product->getSubclass();

                $rootCategoryName = "Merchandising Category";
                $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                $id = $cateogyrProcessor;
                if ($id) {
                    $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                    $categoryIds = $category->getCategoryId();
                }
            }
            $tempItemID = $cart->getId().$product->getId();
            $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
            $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
            $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
            $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;
            $weight = ($product->getWeight())?$product->getWeight():1;
            $serviceCategory = ($product->getServiceCategory()) ? $product->getServiceCategory() : "NATIONAL";
            $tempArray[] = [
                'promise_line_id' => $tempItemID,
                'item' => [
                    'offer_id' => $item['sku'],
                    'service_category' => $serviceCategory,
                    'category_id' => $categoryIds,
                    'requires_shipping' => true,
                    'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                ],
                'quantity' => [
                    'quantity_number' => $item['qty'],
                    'quantity_uom' => 'EA'
                ],
                'unit_price' =>[
                    'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                    'currency' => "INR",
                    'fraction' => self::FRACTION,
                    ],
                'force_parent_line_quantity' => false,
                'package_dimension' => [
                    'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                ]
            ];
        }
        $promiseLines['deliver_together'] = ($this->getDeliveryTogether())?true:false;
        $promiseLines['promise_lines'] = $tempArray;
        $dataArray['promise_groups'][] = $promiseLines;
        $payload = json_encode($dataArray);
        return $payload;
    }
    public function RequestCartData($cart, $pincode, $items)
    {
        $this->addLog("RequestCartData ===> If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode "." \r\n", "requestData.log");
        if($cart->getCustomer()->getId()) {
            $customerId = $cart->getCustomer()->getId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            $customerEmail = $customer->getEmail();
        } else {
            $groupId = $this->getDefaultCustomerGroup();
            $customerGroup = $this->getGroupName($groupId);
            $customerId = '';
            $customerEmail = '';
        }

        $dataArray = [];
        $dataArray['channel'] = "APP";
        $dataArray['order_type'] = 'CUSTOMER-ORDER';
        $dataArray['promise_type'] = 'FWD';
        $dataArray['customer_id'] = $customerId;
        $dataArray['customer_group'] = $customerGroup;
        $dataArray['customer_address']['country'] = 'IN';
        $dataArray['customer_address']['post_code'] = $pincode;
        $dataArray['customer_address']['geo_location']['latitude'] = '';
        $dataArray['customer_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['country'] = 'IN';
        $dataArray['shipping_address']['post_code'] = $pincode;
        $dataArray['shipping_address']['geo_location']['latitude'] = '';
        $dataArray['shipping_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['email_id'] = $customerEmail;
        $dataArray['shipping_address']['phone_number']['country_code'] = '';
        $dataArray['shipping_address']['phone_number']['number'] = '';
        $dataArray['cart_value']['cent_amount'] = $cart->getGrandTotal()*self::FRACTION;
        $dataArray['cart_value']['fraction'] = self::FRACTION;
        $dataArray['cart_value']['currency'] = 'INR';
        $i = 0;
        $tempArray = [];
        foreach ($items as $item) { //print_r($item); exit;
            $product = $this->productRepository->get($item['sku'], false, null, true);
           $unitePrice = $product->getPrice()*self::FRACTION;
            $categoryIds = "";
            if(!empty($product->getIboCategoryId())) {
                $categoryIds = $product->getIboCategoryId();
            } else {
                $levelOne = $product->getDepartment();
                $levelTwo = $product->getClass();
                $levelThree = $product->getSubclass();

                $rootCategoryName = "Merchandising Category";
                $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                $id = $cateogyrProcessor;
                if ($id) {
                    $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                    $categoryIds = $category->getCategoryId();
                }
            }

            $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
            $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
            $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
            $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;

            $weight = ($product->getWeight())?$product->getWeight():1;
            $serviceCategory = ($product->getServiceCategory())?$product->getServiceCategory():"NATIONAL";
            $tempItemID = $cart->getId().$product->getId();
            $tempArray[] = [
                'promise_line_id' => $tempItemID,
                'item' => [
                'offer_id' => $product->getSku(),
                'category_id' => $categoryIds,
                'esin' => $product->getEsin(),
                'seller_id' => $product->getSellerId(),
                'ebo_title'=> $product->getName(),
                'brand_id'=> $product->getAttributeText('brand_id'),
                'brand_name'=>$product->getAttributeText('brand_id'),
                'primary_image_url'=>'',
                'is_bom' => ($product->getIsBom())?true:false,
                'non_catalog_sku'=>'',
                'is_lot_controlled' => ($product->getIsLotControlled())?true:false,
                'is_dangerous'=>$product->getAttributeText('is_dangerous'),
                'service_category' => $serviceCategory,
                'requires_shipping' => true,
                'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                ],
                'quantity' => [
                    'quantity_number' => $item['qty'],
                    'quantity_uom' => 'EA'
                ],
                'unit_price' =>[
                    'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                    'currency' => "INR",
                    'fraction' => self::FRACTION,
                    ],
                'force_parent_line_quantity' => false,
                'package_dimension' => [
                    'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                ]
            ];
        }
        $dataArray['promise_lines'] = $tempArray;
        $payload = json_encode($dataArray);
        return $payload;
    }
    public function CreateJsonRequestData($cart, $pincode,$deliveryTogether = false)
    {

        if($cart->getCustomer()->getId()) {
            $customerId = $cart->getCustomer()->getId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            $customerEmail = $customer->getEmail();
        } else {
            $groupId = $this->getDefaultCustomerGroup();
            $customerGroup = $this->getGroupName($groupId);
            $customerId = '';
            $customerEmail = '';
        }

        $dataArray = [];
        $dataArray['cart_id'] = $cart->getId();
        $dataArray['order_number'] = ($cart->getReservedOrderId())?$cart->getReservedOrderId():$cart->getId();
        $dataArray['order_type'] = 'CUSTOMER-ORDER';
        $dataArray['promise_type'] = 'FWD';
        $dataArray['customer_id'] = $customerId;
        $dataArray['customer_group'] = $customerGroup;
        $dataArray['customer_address']['country'] = 'IN';
        $dataArray['customer_address']['post_code'] = $pincode;
        $dataArray['customer_address']['geo_location']['latitude'] = '';
        $dataArray['customer_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['country'] = 'IN';
        $dataArray['shipping_address']['post_code'] = $pincode;
        $dataArray['shipping_address']['geo_location']['latitude'] = '';
        $dataArray['shipping_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['email_id'] = $customerEmail;
        $dataArray['shipping_address']['phone_number']['country_code'] = '';
        $dataArray['shipping_address']['phone_number']['number'] = '';
        $dataArray['cart_value']['cent_amount'] = (!empty($cart->getPromiseShippingAmount()) && $cart->getPromiseShippingAmount()>0) ? ($cart->getGrandTotal() - $cart->getPromiseShippingAmount())*self::FRACTION : $cart->getGrandTotal()*self::FRACTION;
        $dataArray['cart_value']['fraction'] = self::FRACTION;
        $dataArray['cart_value']['currency'] = 'INR';
        $items = $cart->getAllItems();
        $tempArray = [];
        $i = 0;
        foreach ($items as $item) {
            if ($item->getProduct_type() == 'simple') {
                $product = $this->productRepository->get($item->getSku(), false, null, true);
                $unitePrice = $product->getPrice()*self::FRACTION;
                $categoryIds = "";

                if(!empty($product->getIboCategoryId())) {
                    $categoryIds = $product->getIboCategoryId();
                } else {
                    $levelOne = $product->getDepartment();
                    $levelTwo = $product->getClass();
                    $levelThree = $product->getSubclass();

                    $rootCategoryName = "Merchandising Category";
                    $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                    $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                    $id = $cateogyrProcessor;
                    if ($id) {
                        $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                        $categoryIds = $category->getCategoryId();
                    }
                }

                $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
                $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
                $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
                $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;

                $tempItemID = $cart->getId().$product->getId();
                $weight = ($item->getWeight())?$item->getWeight():1;
                $serviceCategory = ($item->getProduct()->getData('service_category')) ? $item->getProduct()->getData('service_category') : "NATIONAL";
                $tempArray[] = [
                    'promise_line_id' => $tempItemID,
                    'item' => [
                        'offer_id' => $item->getSku(),
                        'service_category' => $serviceCategory,
                        'category_id' => $categoryIds,
                        'requires_shipping' => true,
                        'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                    ],
                    'quantity' => [
                        'quantity_number' => $item->getQty(),
                        'quantity_uom' => 'EA'
                    ],
                    'unit_price' =>[
                        'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                        ],
                    'force_parent_line_quantity' => false,
                    'package_dimension' => [
                        'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                    ]
                ];
            }
        }
        $promiseLines['deliver_together'] = $deliveryTogether;
        $promiseLines['promise_lines'] = $tempArray;
        $dataArray['promise_groups'][] = $promiseLines;
        $payload = json_encode($dataArray);
        return $payload;
    }
    public function CreateCartRequestData($cart, $pincode)
    {

        if($cart->getCustomer()->getId()) {
            $customerId = $cart->getCustomer()->getId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            $customerEmail = $customer->getEmail();
        } else {
            $groupId = $this->getDefaultCustomerGroup();
            $customerGroup = $this->getGroupName($groupId);
            $customerId = '';
            $customerEmail = '';
        }

        $dataArray = [];
        $dataArray['order_type'] = 'CUSTOMER-ORDER';
        $dataArray['promise_type'] = 'FWD';
        $dataArray['customer_id'] = $customerId;
        $dataArray['customer_group'] = $customerGroup;
        $dataArray['customer_address']['country'] = 'IN';
        $dataArray['customer_address']['post_code'] = $pincode;
        $dataArray['customer_address']['geo_location']['latitude'] = '';
        $dataArray['customer_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['country'] = 'IN';
        $dataArray['shipping_address']['post_code'] = $pincode;
        $dataArray['shipping_address']['geo_location']['latitude'] = '';
        $dataArray['shipping_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['email_id'] = $customerEmail;
        $dataArray['shipping_address']['phone_number']['country_code'] = '';
        $dataArray['shipping_address']['phone_number']['number'] = '';
        $dataArray['cart_value']['cent_amount'] = $cart->getGrandTotal()*self::FRACTION;
        $dataArray['cart_value']['fraction'] = self::FRACTION;
        $dataArray['cart_value']['currency'] = 'INR';
        $items = $cart->getAllItems();
        $i = 0;
        $tempArray = [];
        foreach ($items as $item) {
            if ($item->getProduct_type() == 'simple') {
                $product = $this->productRepository->get($item->getSku(), false, null, true);
                $unitePrice = $product->getPrice()*self::FRACTION;

                $categoryIds = "";
                if(!empty($product->getIboCategoryId())) {
                    $categoryIds = $product->getIboCategoryId();
                } else {
                    $levelOne = $product->getDepartment();
                    $levelTwo = $product->getClass();
                    $levelThree = $product->getSubclass();

                    $rootCategoryName = "Merchandising Category";
                    $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                    $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                    $id = $cateogyrProcessor;
                    if ($id) {
                        $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                        $categoryIds = $category->getCategoryId();
                    }
                }

                $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
            $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
            $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
            $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;

                $weight = ($product->getWeight())?$product->getWeight():1;
                $serviceCategory = ($product->getServiceCategory()) ? $product->getServiceCategory() : "NATIONAL";
                $tempItemID = $cart->getId().$product->getId();
                $tempArray[] = [
                    'promise_line_id' => $tempItemID,
                    'item' => [
                    'offer_id' => $product->getSku(),
                    'category_id' => $categoryIds,
                    'esin' => $product->getEsin(),
                    'seller_id' => $product->getSellerId(),
                    'ebo_title'=> $product->getName(),
                    'brand_id'=> $product->getAttributeText('brand_id'),
                    'brand_name'=>$product->getAttributeText('brand_id'),
                    'primary_image_url'=>'',
                    'is_bom' => ($product->getIsBom())?true:false,
                    'non_catalog_sku'=>'',
                    'is_lot_controlled' => ($product->getIsLotControlled())?true:false,
                    'is_dangerous'=>$product->getAttributeText('is_dangerous'),
                    'service_category' => $serviceCategory,
                    'requires_shipping' => true,
                    'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                    ],
                    'quantity' => [
                        'quantity_number' => $item->getQty(),
                        'quantity_uom' => 'EA'
                    ],
                    'unit_price' =>[
                        'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                        ],
                    'force_parent_line_quantity' => false,
                    'package_dimension' => [
                        'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                    ]
                ];
            }
        }
        $dataArray['promise_lines'] = $tempArray;
        $payload = json_encode($dataArray);
        return $payload;
    }
    public function UpdateCartRequestData($cart, $pincode,$itemSku,$itemQty)
    {

        if($cart->getCustomer()->getId()) {
            $customerId = $cart->getCustomer()->getId();
            $customer = $this->customerRepository->getById($customerId);
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            $customerEmail = $customer->getEmail();
        } else {
            $groupId = $this->getDefaultCustomerGroup();
            $customerGroup = $this->getGroupName($groupId);
            $customerId = '';
            $customerEmail = '';
        }

        $dataArray = [];
        $dataArray['order_type'] = 'CUSTOMER-ORDER';
        $dataArray['promise_type'] = 'FWD';
        $dataArray['customer_id'] = $customerId;
        $dataArray['customer_group'] = $customerGroup;
        $dataArray['customer_address']['country'] = 'IN';
        $dataArray['customer_address']['post_code'] = $pincode;
        $dataArray['customer_address']['geo_location']['latitude'] = '';
        $dataArray['customer_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['country'] = 'IN';
        $dataArray['shipping_address']['post_code'] = $pincode;
        $dataArray['shipping_address']['geo_location']['latitude'] = '';
        $dataArray['shipping_address']['geo_location']['longitude'] = '';
        $dataArray['shipping_address']['email_id'] = $customerEmail;
        $dataArray['shipping_address']['phone_number']['country_code'] = '';
        $dataArray['shipping_address']['phone_number']['number'] = '';
        $dataArray['cart_value']['cent_amount'] = $cart->getGrandTotal()*self::FRACTION;
        $dataArray['cart_value']['fraction'] = self::FRACTION;
        $dataArray['cart_value']['currency'] = 'INR';
        $items = $cart->getAllItems();
        $i = 0;
        $tempArray = [];
        foreach ($items as $item) {

            if($item->getSku() == $itemSku) {
                $ItemQty = $itemQty;
            } else {
                $ItemQty = $item->getQty();
            }

            if ($item->getProduct_type() == 'simple') {
                $product = $this->productRepository->get($item->getSku(), false, null, true);
                $unitePrice = $product->getPrice()*self::FRACTION;

                $categoryIds = "";
                if(!empty($product->getIboCategoryId())) {
                    $categoryIds = $product->getIboCategoryId();
                } else {
                    $levelOne = $product->getDepartment();
                    $levelTwo = $product->getClass();
                    $levelThree = $product->getSubclass();

                    $rootCategoryName = "Merchandising Category";
                    $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                    $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                    $id = $cateogyrProcessor;
                    if ($id) {
                        $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                        $categoryIds = $category->getCategoryId();
                    }
                }

                $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
            $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
            $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
            $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;

                $weight = ($product->getWeight())?$product->getWeight():1;
                $serviceCategory = ($product->getServiceCategory()) ? $product->getServiceCategory() : "NATIONAL";
                $tempItemID = $cart->getId().$product->getId();
                $tempArray[] = [
                    'promise_line_id' => $tempItemID,
                    'item' => [
                    'offer_id' => $product->getSku(),
                    'category_id' => $categoryIds,
                    'esin' => $product->getEsin(),
                    'seller_id' => $product->getSellerId(),
                    'ebo_title'=> $product->getName(),
                    'brand_id'=> $product->getAttributeText('brand_id'),
                    'brand_name'=>$product->getAttributeText('brand_id'),
                    'primary_image_url'=>'',
                    'is_bom' => ($product->getIsBom())?true:false,
                    'non_catalog_sku'=>'',
                    'is_lot_controlled' => ($product->getIsLotControlled())?true:false,
                    'is_dangerous'=>$product->getAttributeText('is_dangerous'),
                    'service_category' => $serviceCategory,
                    'requires_shipping' => true,
                    'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                    ],
                    'quantity' => [
                        'quantity_number' => $ItemQty,
                        'quantity_uom' => 'EA'
                    ],
                    'unit_price' =>[
                        'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                        ],
                    'force_parent_line_quantity' => false,
                    'package_dimension' => [
                        'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                    ]
                ];
            }
        }
        $dataArray['promise_lines'] = $tempArray;
        $payload = json_encode($dataArray);
        return $payload;
    }
    public function CreateProductRequestData($productSku, $pincode)
    {
        $product = $this->productRepository->get($productSku, false, null, true);

        $categoryIds = "";
        if(!empty($product->getIboCategoryId())) {
            $categoryIds = $product->getIboCategoryId();
        } else {
            $levelOne = $product->getDepartment();
            $levelTwo = $product->getClass();
            $levelThree = $product->getSubclass();

            $rootCategoryName = "Merchandising Category";
            $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
            $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
            $id = $cateogyrProcessor;
            if ($id) {
                $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                $categoryIds = $category->getCategoryId();
            }
        }

        $dataArray = [];
        $tempArray = [];
        if ($product->getId()) {
            $weight = ($product->getWeight())?$product->getWeight():1;
            $dataArray['channel'] = '';
            $dataArray['order_type'] = 'CUSTOMER-ORDER';
            $dataArray['promise_type'] = 'FWD';
            $dataArray['customer_address']['country'] = 'IN';
            $dataArray['customer_address']['post_code'] = $pincode;
            $dataArray['customer_address']['geo_location']['latitude'] = '';
            $dataArray['customer_address']['geo_location']['longitude'] = '';

            $unitePrice = $product->getPrice()*self::FRACTION;

            $package_height = ($product->getPackageHeightInCm()) ? $product->getPackageHeightInCm() : 0;
            $package_length = ($product->getPackageLengthInCm()) ? $product->getPackageLengthInCm() : 0;
            $package_width = ($product->getPackageWidthInCm()) ? $product->getPackageWidthInCm() : 0;
            $package_weight = ($product->getPackageWeightInKg()) ? $product->getPackageWeightInKg() : 0;
            $serviceCategory = ($product->getServiceCategory()) ? $product->getServiceCategory() : "NATIONAL";
            $i=1;
            $tempArray[] = [
                'promise_line_id' => $i++,
                'item' => [
                    'offer_id' => $product->getSku(),
                    'category_id' => $categoryIds,
                    'esin' => $product->getEsin(),
                    'seller_id' => $product->getSellerId(),
                    'ebo_title'=> $product->getName(),
                    'brand_id'=> $product->getAttributeText('brand_id'),
                    'brand_name'=>$product->getAttributeText('brand_id'),
                    'primary_image_url'=>'',
                    'is_bom' => ($product->getIsBom())?true:false,
                    'non_catalog_sku'=>'',
                    'is_lot_controlled' => ($product->getIsLotControlled())?true:false,
                    'is_dangerous'=>$product->getAttributeText('is_dangerous'),
                    'service_category' => $serviceCategory,
                    'requires_shipping' => true,
                    'courier_type' => ($product->getCourierType())?$product->getCourierType(): 'F'
                ],
                'quantity' => [
                    'quantity_number' => ($product->getQtyIncrements())?$product->getQtyIncrements():1,
                    'quantity_uom' => 'EA'
                ],
                'unit_price' =>[
                    'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                    'currency' => "INR",
                    'fraction' => self::FRACTION,
                    ],
                'force_parent_line_quantity' => false,
                'package_dimension' => [
                    'height_in_cm' => $package_height,
                    'length_in_cm' => $package_length,
                    'width_in_cm' => $package_width,
                    'weight_in_kg' => $package_weight
                ]
            ];
            $dataArray['promise_lines'] = $tempArray;
        }
        $payload = json_encode($dataArray);

        return $payload;
    }

    public function CurlExecute($cart, $pincode)
    {
        $returnResult = '';
        $url = $this->getPromiseApi();
        $params = $this->CreateJsonRequestData($cart, $pincode);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);

        try {
            $this->addLog('Curl Initiated');
            $this->curl->post($url, $params);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        if ($resultData) {
            $this->addLog('Curl Executed');

            if (isset($resultData['errors'])) {
                $this->addLog($resultData['errors']);
                $returnResult = "There is some error";
            } else {
                $data = $resultData['delivery_groups'];
                $arr = [];
                foreach ($data as $deliveryData) {
                    $delOptionData = $deliveryData['delivery_group_lines'];
                    foreach ($delOptionData as $d) {
                        if (($d['fulfillable_quantity'] == null) || ($d['fulfillable_quantity']['quantity_number'] != $d['quantity']['quantity_number'])) {
                            $arr[] = $d['item']['offer_id'];
                        }
                    }
                }

                if (!empty($arr)) {
                    $items = $cart->getAllItems();
                    foreach ($items as $item) {
                        if (in_array($item->getSku(), $arr)) {
                            $item->setEbo_inventory_flag(0);
                            $item->save();
                        }
                    }
                }
            }
        }

        if ($returnResult != '') {
            return 'Error thrown';
        } else {
            return "success";
        }
    }

    public function cartQuantityCheck($cart, $pincode)
    {
        $returnResult = [];
        $returnResult['status'] = 0;
        $url = $this->getCartPromiseApi();
        $params = $this->CreateCartRequestData($cart, $pincode);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);
        try {
            $this->addLog('Curl Initiated for Cart'.json_encode($params));
            $this->curl->post($url, $params);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult['error'] = "Error : ".$e->getMessage();
        }

        if ($resultData) {
            $this->addLog('Cart Curl Result'.json_encode($resultData));

            if (isset($resultData['errors'])) {
                $this->addLog(json_encode($resultData['errors']));
                $returnResult['error'] = "Error : ";
            } else {
                $data = $resultData['promise_lines'];
                $arr_item = [];
                foreach ($data as $deliveryData) {
                    if (($deliveryData['fulfillable_quantity'] == null) || ($deliveryData['fulfillable_quantity']['quantity_number'] != $deliveryData['quantity']['quantity_number'])) {
                        $arr_item[$deliveryData['item']['offer_id']] = $deliveryData['item']['offer_id'];
                    }
                }
                if (!empty($arr_item)) {
                    $items = $cart->getAllItems();
                    foreach ($items as $item) {
                        if (array_key_exists($item->getSku(), $arr_item)) {
                            $item->setEbo_inventory_flag(0);
                            $item->save();
                        }
                    }
                }
                $returnResult['status'] = 1;
                $returnResult['data'] = $resultData;
            }
        } else {
            $returnResult['error'] = "Error: No response";
        }
        return $returnResult;
    }

    public function productQuantityCheck($sku, $pincode)
    {
        $returnResult = [];
        $returnResult['status'] = 0;
        $url = $this->getCartPromiseApi();
        $params = $this->CreateProductRequestData($sku, $pincode);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);

        try {
            $this->addLog('Curl Initiated for PDP');
            $this->addLog(json_encode($params));
            $this->curl->post($url, $params);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
            $this->addLog(json_encode($resultData));
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult['error'] = "Error : ".$e->getMessage();
        }

        if ($resultData) {
            $this->addLog('PDP Curl Result'.json_encode($resultData));

            if (isset($resultData['errors'])) {
                $this->addLog($resultData['errors']);
                $returnResult['error'] = "Error : ";
            } else {
                if (is_array($resultData) && count($resultData) > 0) {
                    $deliveryData = $resultData;
                    $returnResult['status'] = 1;
                    $returnResult['data'] = $deliveryData;
                } else {
                    $returnResult['error'] = "Error : No Data";
                }
            }
        } else {
            $returnResult['error'] = "Error: No response";
        }
        return $returnResult;
    }
    // public function processstatusCheck($cart, $args)
    // {
    //     $data = $this->StatusExecute($cart, $args);
    //      return $data;
    // }
    public function processQuantityStatus($args)
    {
        $tempArray = [
            'promise_id' => $args['promise_id'],
            'cart_id' => $args['cart_id'],
            'order_number' => $args['order_number'],
            'delivery_groups' => [[
            'delivery_group_id' => $args['delivery_group_id'],
            'promise_option_selected' => [
            'delivery_method' => $args['delivery_method'],
            'delivery_option' => $args['delivery_option'],
            'node_id' => $args['node_id'],
            'slot_id' => $args['slot_id']

                ],
            ],],
        ];
        $payload = json_encode($tempArray);
        return $payload;
    }
    public function StatusExecute($args)
    {
        $returnResult = [];
        $returnResult['status'] = 0;
        $promise_id = $args['promise_id'] . "/selection";
        $url = $this->getPromiseApi() ."/". $promise_id ;
        $params = $this->processQuantityStatus($args);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);
        try {
            $this->addLog('Selection Curl Initiated');
            $this->addLog(json_encode($params));
            $this->curl->post($url, $params);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult['error'] = "Error : ".$e->getMessage();
        }

        if ($resultData) {
            $this->addLog('PDP Curl Result'.json_encode($resultData));

            if (isset($resultData['errors'])) {
                $this->addLog($resultData['errors']);
                $returnResult['error'] = "Error :";
            } else {
                if (is_array($resultData) && count($resultData) > 0) {
                    $deliveryData = $resultData;
                    $returnResult['status'] = 1;
                    $returnResult['data'] = $deliveryData;
                    $this->addLog(json_encode($resultData));
                } else {
                    $returnResult['error'] = "Error : No Data";
                }
            }
        } else {
            $returnResult['error'] = "Error: No response";
        }
        return $returnResult;
    }

    // public function updateShipping($quote)
    // {

    //     $cart = $this->cartInterface->get($quote);
    //     $postcode = $cart->getShippingAddress()->getPostcode();
    //     return $this->getShipData($cart, $postcode);
    // }

    // public function getShipData($cart, $pincode)
    // {
    //     $returnResult = '';
    //     $url = $this->getPromiseApi();
    //     $params = $this->CreateJsonRequestData($cart, $pincode);
    //     $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
    //     $this->curl->setOption(CURLOPT_POST, true);
    //     $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
    //     $this->curl->setHeaders($headers);
    //     $deliveryDatas = [];
    //     try {
    //         $this->addLog('Curl Initiated');
    //         $this->curl->post($url, $params);
    //         $result = $this->curl->getBody();
    //         $resultData = json_decode($result, true);
    //     } catch (\Exception $e) {
    //         $this->addLog($e->getMessage());
    //         $returnResult = "There is some error";
    //     }

    //     if ($resultData) {
    //         $this->addLog('Curl Executed');
    //         //$this->addLog('Datas'.print_r($resultData,true));
    //         if (isset($resultData['errors'])) {
    //             $this->addLog($resultData['errors']);
    //             $returnResult['error'] = "Error : ";
    //         } else {
    //             $data = $resultData['delivery_groups'];

    //             foreach ($data as $deliveryData) {
    //                 $deliveryDatas = [];
    //                 if (!empty($deliveryData['promise_options'])) {
    //                     foreach ($deliveryData['promise_options'] as $promiseOption) {
    //                         $deliveryDatas['delivery_method'] = $promiseOption['delivery_method'];
    //                         foreach ($promiseOption['promise_delivery_info'] as $delivery) {
    //                             $deliveryDatas['cent_amount'] = $delivery['delivery_cost']['cent_amount'];
    //                             $deliveryDatas['fraction'] = $delivery['delivery_cost']['fraction'];
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     } else {
    //         $this->addLog("Error: No response");
    //     }
    //     //$this->addLog('Data'.$deliveryDatas);
    //     return $deliveryDatas;
    // }

    public function getPromiseData($cart, $pincode,$deliveryTogether = false)
    {
        $returnResult = [];
        $url = $this->getPromiseApi();
        $params = $this->CreateJsonRequestData($cart, $pincode,$deliveryTogether);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);
        $deliveryDatas = [];
        try {
            
            $this->addLog('Promise Data Call Initiated');
            $this->addLog(json_encode($params));

            $startTime = microtime(true);
            $this->addLog("Promise call Start time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$startTime);

            $this->curl->post($url, $params);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);

            $endTime = microtime(true);
            $this->addLog("Promise call End time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$endTime. ", Difference in milliseconds:
                        ".number_format($endTime - $startTime, 5)/1000);
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult['error'] = 'Error';
            $returnResult['status'] = 0;
        }
        if ($resultData) {
            if (isset($resultData['errors'])) {
                $this->addLog($resultData['errors']);
                $returnResult['error'] = "Error : ";
                $returnResult['status'] = 0;
            } else {
                $returnResult['status'] = 1;
                $returnResult['data'] = $resultData;
                $this->addLog(json_encode($resultData));
            }
        }

        return $returnResult;
    }

    public function getStatusCount()
    {
        $setOrderCountLimit = $this->_scopeConfig->getValue("Order_success_status/order_count_settings/Order_status_count_id");
        return $setOrderCountLimit;
    }

    private function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }
    // private function getApproveStatus($approvalStatus)
    // {
    //     $aprroveId = $this->customerRepository->getById($approvalStatus);
    //     return $aprroveId->getCode();
    // }

    private function getCustomerAttributeValue($customer, $attributeCode)
    {
        $customerStatus =$customer->getCustomAttribute($attributeCode);
        $optionValues = [];
        if (!empty($customerStatus)) {
            $this->attributeCollection->setIdFilter(explode(',', $customer->getCustomAttribute($attributeCode)->getValue()))
            ->setStoreFilter();
            $options = $this->attributeCollection->toOptionArray();
            if (!empty($options)) {
                array_walk($options, function ($value, $key) use (&$optionValues) {
                    $optionValues[] = $value['label'];
                });
            }
        }
        return implode(',', $optionValues);
    }

    private function getCountryname($countryCode)
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }
    public function getStateId($stateCode) {
        $states = array();
        $states['JK'] = "01";
        $states['HP'] = "02";
        $states['PB'] = "03";
        $states['CH'] = "04";
        $states['UT'] = "05";
        $states['HR'] = "06";
        $states['DL'] = "07";
        $states['RJ'] = "08";
        $states['UP'] = "09";
        $states['BR'] = "10";
        $states['SK'] = "11";
        $states['AR'] = "12";
        $states['NL'] = "13";
        $states['MN'] = "14";
        $states['MZ'] = "15";
        $states['TR'] = "16";
        $states['ML'] = "17";
        $states['AS'] = "18";
        $states['WB'] = "19";
        $states['JH'] = "20";
        $states['OD'] = "21";
        $states['OR'] = "21";
        $states['CG'] = "22";
        $states['MP'] = "23";
        $states['GJ'] = "24";
        $states['DN'] = "26";
        $states['DD'] = "26";
        $states['MH'] = "27";
        //$states['AP'] = "28";
        $states['KA'] = "29";
        $states['GA'] = "30";
        $states['LD'] = "31";
        $states['KL'] = "32";
        $states['TN'] = "33";
        $states['PY'] = "34";
        $states['AN'] = "35";
        $states['TS'] = "36";
        $states['AP'] = "37";
        $states['LA'] = "38";
             if(isset($stateCode) && array_key_exists($stateCode, $states)){
                 return $states[$stateCode];
             }
             else{
                 return;
             }
        }
    public function getPaymentIntentId($orderId)
    {
        $response = "";
        $paymentUrl = $this->getPaymentApiUrl().$orderId;
        $token = ($this->getPaymentIntentToken())?$this->getPaymentIntentToken():$this->getPaymentIntentTokenAlt();
        $this->addLog('Payment Request, Response');
        $this->addLog($paymentUrl);
        $this->addLog('Header:'.substr($token, 0,5));
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $paymentUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$token, 'x-channel-id: WEB']);
        $result = curl_exec($curl);

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        //$pincodes = '';
        $this->addLog(json_encode($http_code));
        $this->addLog(json_encode($result));
        $result = json_decode($result,true);
        if(isset($result) && isset($result['payment_intent_id'])) {
            $response = $result['payment_intent_id'];
        }

        return $response;
    }
    public function orderRequestBody($orderId)
    {
        $this->addLog("one" . $orderId);
        $payload = [];
        $order_data = $this->orderRepository->get($orderId);
        if($order_data->getId()){
            //This is for update customer first_time_promo_applied attribute
            if($order_data->getAppliedRuleIds()) {
                $this->updateFirstTimePromoAttribute($order_data->getAppliedRuleIds(),
                    $order_data->getCustomerId(), true);
            }
            $billingaddress = $order_data->getBillingAddress();
            $shippingaddress = $order_data->getShippingAddress();
            $orderItems = $order_data->getAllItems();
            $custFirsrName = $order_data->getCustomerFirstname();
            $custLastName = $order_data->getCustomerLastname();
            $billingStreet = $billingaddress->getStreet();
            $shippingStreet = $shippingaddress->getStreet();
            $customer = $this->customerRepository->getById($order_data->getCustomerId());
            $customerGroup = '';
            $isB2BCustomer = false;
            $approvalStatus =false;
            $latitude = '';
            $longitude = '';
            $docNo = rand();
            if($customer->getId()){
                $groupId = $customer->getGroupId();
                $customerGroup = $this->getGroupName($groupId);
                $isB2BCustomer = ($customerGroup == 'B2B') ? true : false;
                $optionlabel = "";
                if(null!== ($customer->getCustomAttribute('approval_status'))){
                    $approvalStatus = $customer->getCustomAttribute('approval_status')->getValue();
                    $attribute = $this->eavConfig->getAttribute('customer', "approval_status");
                    $optionlabel = $attribute->getSource()->getOptionText($approvalStatus);
                }
                $approvalStatus = (isset($approvalStatus))? true : false;
                // $this->addLog($approvalStatus->getValue());
                if(null!== ($customer->getCustomAttribute('latitude'))){
                    $latitude = $customer->getCustomAttribute('latitude')->getValue();
                }
                if(null!== ($customer->getCustomAttribute('longitude'))){
                    $longitude = $customer->getCustomAttribute('longitude')->getValue();
                }
                $docNo = $order_data->getCustomerTaxvat();

            }

            $paymentIntentId = $this->getPaymentIntentId($order_data->getIncrementId());
            $paymentMethod = ($order_data->getPayment()->getMethod() == 'cashondelivery' || $order_data->getPayment()->getMethod() == 'free')?'POD':'PREPAID';

            // $method = $payment->getMethodInstance();
            // $paymentMethod = $method->getTitle(); // Cash On Delivery
            // $paymentCode = $method->getCode(); // cashondelivery
            $appliedRule = $order_data->getAppliedRuleIds();
            $promiseOptions = json_decode($order_data->getPromise_options(), true);
            $deliveryGroup = json_decode($order_data->getDelivery_group(), true);
            $deliverGroupLine = [];
            $deliveryParners = [];
            if (!(empty($deliveryGroup))) {
                foreach ($deliveryGroup as $newkey => $delivery) {
                    foreach($delivery['delivery_group_lines'] as $deliveygroup_line){
                        $offerId = $deliveygroup_line['item']['offer_id'];
                        $deliverGroupLine[$offerId] = isset($deliveygroup_line['promise_line_id']) ? $deliveygroup_line['promise_line_id'] : '';
                        if(isset($delivery['deliverPartner'])) {
                            $deliveryParners[$offerId]['assisted_delivery'] = ($delivery['deliverPartner'] == 'DELIVERY_PICKUP') ? true : false;
                        }
                    }
                }
            }

            $cart_origin = "AND";
            $nodeId = '';
            $orderChennelInfo = [
                [
                    'group' => "",
                    'name' => "",
                    'values' => [],
                ],
            ];
            $isPartialPayment = false;
            $orderType = "CUSTOMER-ORDER";
            if($order_data->getOrderChannel() == 'STORE') {
                $cart_origin = "STORE";
                $orderSubtype = "POS";
                if (!(empty($promiseOptions))) {
                    foreach ($promiseOptions as $newkey => $promiseOption) {
                        $nodeId = isset($promiseOptions[$newkey]['node_id']) ? $promiseOptions[$newkey]['node_id'] : '';
                    }
                }
                $posOrderData = $this->getPosOrderData($orderId);
                if(!empty($posOrderData)) {
                    $holdTime = "00:00";
                    if(isset($posOrderData['hold_time']) && $posOrderData['hold_time']) {
                        $hours = (int)($posOrderData['hold_time'] / 60);
                        $minutes = fmod($posOrderData['hold_time'], 60);
                        $holdTime = sprintf("%02d", $hours) . ":" . sprintf("%02d", $minutes);
                    }
                    $orderChennelInfo = [
                        [
                            'group' => "STORE_ORDER",
                            'name' => "cashier_id",
                            'values' => isset($posOrderData['pos_user_id']) ? [$posOrderData['pos_user_id']] : [],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "terminal_id",
                            'values' => isset($posOrderData['pos_terminal_id']) ? [$posOrderData['pos_terminal_id']] : [],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "store_id",
                            'values' => isset($posOrderData['store_id']) ? [$posOrderData['store_id']] : [],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "order_device_type",
                            'values' => isset($posOrderData['order_device_type']) ? [$posOrderData['order_device_type']] : [],
                        ],
                        // [
                        //     'group' => "STORE_ORDER",
                        //     'name' => "sales_associate_1",
                        //     'values' => isset($posOrderData['order_sales_associate_1']) ? [$posOrderData['order_sales_associate_1']] : [],
                        // ],
                        // [
                        //     'group' => "STORE_ORDER",
                        //     'name' => "sales_associate_2",
                        //     'values' => isset($posOrderData['order_sales_associate_2']) ? [$posOrderData['order_sales_associate_2']] : [],
                        // ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "quote_creation_time",
                            'values' => isset($posOrderData['quote_creation_time']) ? [$this->timezone->date(new \DateTime($posOrderData['quote_creation_time']))->format('Y-m-d h:i:s A')] : [],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "order_creation_time",
                            'values' => isset($posOrderData['order_creation_time']) ? [$this->timezone->date(new \DateTime($posOrderData['order_creation_time']))->format('Y-m-d h:i:s A')] : [],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "is_hold_cart",
                            'values' => isset($posOrderData['is_hold_cart']) ? [(bool)$posOrderData['is_hold_cart']] : [false],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "hold_time",
                            'values' => [$holdTime],
                        ],
                        [
                            'group' => "STORE_ORDER",
                            'name' => "hold_cart_additional_data",
                            'values' => isset($posOrderData['hold_cart_additional_data']) ? [$posOrderData['hold_cart_additional_data']] : [],
                        ]
                    ];
                    $salesAssociateId = $salesAssociateName = $salesAssociatePhone =  "";
                    if(isset($posOrderData['order_additional_data']) && !empty($posOrderData['order_additional_data'])) {
                        $posOrderadditionalData = (array)json_decode($posOrderData['order_additional_data']);
                        $orderSubtype = isset($posOrderadditionalData['order_subtype']) ? $posOrderadditionalData['order_subtype'] : "POS";
                        if(isset($posOrderadditionalData['quotation_number']) && $posOrderadditionalData['quotation_number']) {
                            $orderType = "QUOTE-ORDER";
                            $orderChennelInfo[] = [
                                'group' => "STORE_ORDER",
                                'name' => "quotation_number",
                                'values' => [$posOrderadditionalData['quotation_number']],
                            ];
                        }
                        if(isset($posOrderadditionalData['sales_associate']) && $posOrderadditionalData['sales_associate']) {
                            $salesAssociate = (array)$posOrderadditionalData['sales_associate'];
                            $salesAssociateNameDetails = isset($salesAssociate['executive_name']) ? (array)$salesAssociate['executive_name'] : "";
                            $salesAssociateNumber = isset($salesAssociate['executive_phone_number']) ? (array)$salesAssociate['executive_phone_number'] : "";
                            $salesAssociateId = isset($salesAssociate['executive_id']) ? $salesAssociate['executive_id'] : "";
                            $salesAssociateName = isset($salesAssociateNameDetails['first_name']) ? $salesAssociateNameDetails['first_name'] : "";
                            $salesAssociatePhone = isset($salesAssociateNumber['number']) ? $salesAssociateNumber['number'] : "";
                        }
                    }

                    $orderChennelInfo[] = [
                        'group' => "STORE_ORDER",
                        'name' => "executive_id",
                        'values' => [$salesAssociateId]
                    ];
                    $orderChennelInfo[] = [
                        'group' => "STORE_ORDER",
                        'name' => "executive_name",
                        'values' => [$salesAssociateName]
                    ];
                    $orderChennelInfo[] = [
                        'group' => "STORE_ORDER",
                        'name' => "executive_phone_number",
                        'values' => [$salesAssociatePhone]
                    ];
                }

                $posOrderAdditionalJsonData = $order_data->getAdditionalData();
                if(!empty($posOrderAdditionalJsonData)) {
                    $posOrderAdditionalDetails = (array)json_decode($posOrderAdditionalJsonData);
                    $isPartialPayment = (isset($posOrderAdditionalDetails['is_partial_payment']) && ($posOrderAdditionalDetails['is_partial_payment'] == "true")) ? true : false;
                }
            }

            $this->addLog('Order Channel Value :'.$order_data->getOrderChannel());
            $this->addLog('Order ChannelInfo Value : '.$order_data->getOrderChannelInfo());
            if($order_data->getOrderChannel() == 'ONLINE') {
                // if(($order_data->getOrderChannelInfo() == 'Android') || ($order_data->getOrderChannelInfo() == 'iOS')) {
                //     $cart_origin = "APP";
                // } else {
                //     $cart_origin = "WEB";
                // }
                $cart_origin = $order_data->getOrderChannelInfo();
                $orderSubtype = 'DWH';
                if ($order_data->getExecutiveId() !== null && $order_data->getExecutiveId() != '') {
                    $orderSubtype = 'AST';
                }
            }

            $customerType = $this->getCustomerAttributeValue($customer, "customer_type");
            $date = strtotime($order_data->getCreatedAt());
            $createdDate = date('Y-m-d\TH:i:s\Z', $date);
            $municipal = '';
            $dataArray = [];
            $dataArray['fulfilment_order']['order_number'] = $order_data->getIncrementId();
            $dataArray['fulfilment_order']['is_partial_payment'] = $isPartialPayment;
            $dataArray['fulfilment_order']['version'] = "v1";
            $dataArray['fulfilment_order']['order_type'] = $orderType;
            $dataArray['fulfilment_order']['order_subtype'] = $orderSubtype;
            $dataArray['fulfilment_order']['created_at'] = $createdDate;
            $dataArray['fulfilment_order']['cart_id'] = $order_data->getQuoteId();
            $dataArray['fulfilment_order']['cart_origin'] = $cart_origin;
            $dataArray['fulfilment_order']['customer']['customer_id'] = !empty($order_data->getCustomerId()) ? $order_data->getCustomerId() : '';
            if(isset($optionlabel) && strtolower($optionlabel) == "approved") {
                $dataArray['fulfilment_order']['customer']['customer_group'] =!empty($customerGroup) ? $customerGroup : '';
                $dataArray['fulfilment_order']['customer']['customer_type'] = ($customerType)?$customerType:"Individual";
            }else{
                $dataArray['fulfilment_order']['customer']['customer_group'] = 'B2C';
                $dataArray['fulfilment_order']['customer']['customer_type'] = "Individual";

            }
            if($order_data->getOrderChannel() == 'STORE') {
                $customerEntityType = $this->posHelper->getCustomerAttribute($this->connection, $customer, 'entity_type');
                if(!empty($customerEntityType)) {
                    $dataArray['fulfilment_order']['customer']['entity'] = array(
                        "entity_type" => $customerEntityType,
                        "entity_id" => !empty($order_data->getCustomerId()) ? $order_data->getCustomerId() : '',
                        "entity_name" => "CUSTOMER"
                    );
                }
            }
            // $dataArray['fulfilment_order']['customer']['account_id'] = "123e4567-e8d9-12d3-a456-556642440000";
            $dataArray['fulfilment_order']['customer']['customer_name']['salutation'] = '';
            $dataArray['fulfilment_order']['customer']['customer_name']['first_name'] =$shippingaddress->getFirstName();
            $dataArray['fulfilment_order']['customer']['customer_name']['middle_name'] = '';
            $dataArray['fulfilment_order']['customer']['customer_name']['last_name'] =!empty($shippingaddress->getLastName()) ? $shippingaddress->getLastName() : '';
            $dataArray['fulfilment_order']['customer']['customer_name']['suffix'] = !empty($order_data->getCustomerSuffix()) ?  $order_data->getCustomerSuffix() : '';
            $dataArray['fulfilment_order']['customer']['email_id'] = !empty($order_data->getCustomerEmail()) ?  $order_data->getCustomerEmail() : '';
            $dataArray['fulfilment_order']['customer']['phone_number'] = [
                'country_code' => "+91",
                'number' => !empty($customer->getCustomAttribute('mobilenumber')->getValue()) ? $customer->getCustomAttribute('mobilenumber')->getValue() : ''
            ];
            $dataArray['fulfilment_order']['customer']['is_b2b_customer'] = $isB2BCustomer;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['entity_id'] = $customer->getId();
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['type'] ="GST";
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['country'] = "IN";
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['number'] = $docNo;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['is_verified'] =  $approvalStatus;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['trade_name'] = $billingaddress->getFirstName()." ".$billingaddress->getLastName();
            $dataArray['fulfilment_order']['shipping_address']['address_id'] =!empty($shippingaddress->getId()) ? $shippingaddress->getId() : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line1'] = !empty($shippingStreet[0]) ? $shippingStreet[0] : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line2']= !empty($shippingStreet[1]) ? $shippingStreet[1] : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line3']= !empty($shippingStreet[2]) ? $shippingStreet[2] : '';
            $dataArray['fulfilment_order']['shipping_address']['landmark'] = !empty($shippingaddress->getLandmark()) ? $shippingaddress->getLandmark() : '';
            $dataArray['fulfilment_order']['shipping_address']['municipal'] = !empty($municipal) ? $municipal : '';
            $dataArray['fulfilment_order']['shipping_address']['city'] = !empty($shippingaddress->getCity()) ? $shippingaddress->getCity() : '';
            $dataArray['fulfilment_order']['shipping_address']['state_code'] = $this->getStateId($shippingaddress->getRegionCode());
            $dataArray['fulfilment_order']['shipping_address']['state'] = !empty($shippingaddress->getRegionCode()) ? $shippingaddress->getRegionCode() : '';
            $dataArray['fulfilment_order']['shipping_address']['country_code'] = !empty($shippingaddress->getCountryId()) ? $shippingaddress->getCountryId() : '';
            $dataArray['fulfilment_order']['shipping_address']['country'] = !empty($shippingaddress) ? $this->getCountryname($shippingaddress->getCountryId()) : '';
            $dataArray['fulfilment_order']['shipping_address']['post_code'] =!empty($shippingaddress->getPostcode()) ?  $shippingaddress->getPostcode() : '';
            $dataArray['fulfilment_order']['shipping_address']['geo_location']['latitude'] = $latitude;
            $dataArray['fulfilment_order']['shipping_address']['geo_location']['longitude'] = $longitude;
            $dataArray['fulfilment_order']['shipping_address']['email_id'] = !empty($shippingaddress->getEmail()) ?  $shippingaddress->getEmail() : '';
            $dataArray['fulfilment_order']['shipping_address']['phone_number']['country_code'] = "+91";
            $dataArray['fulfilment_order']['shipping_address']['phone_number']['number'] = !empty($shippingaddress->getTelephone()) ?  $shippingaddress->getTelephone() : '';
            $dataArray['fulfilment_order']['shipping_address']['fax']['country_code'] = "";
            $dataArray['fulfilment_order']['shipping_address']['fax']['number'] = "";
            $dataArray['fulfilment_order']['pickup']['node_id'] = !empty($nodeId) ? $nodeId  : '';

            if ($orderSubtype === 'AST') {
                $orderChennelInfo[] = [
                    'group' => 'ASSISTED_ORDER',
                    'name' => 'executive_id',
                    'values' => [$order_data->getExecutiveId()]
                ];
            }

            $dataArray['fulfilment_order']['order_channel'] = [
                'channel' => $cart_origin,
                'channel_info' => $orderChennelInfo
            ];
                $ruleArr = [];
                $orderAplliety = [];
                if ($order_data->getBaseGrandTotal() <> $order_data->getGrandTotal()) {
                    $roundAmnt = $order_data->getBaseGrandTotal() - $order_data->getGrandTotal();
                    $orderAplliety["ROUND_OFF"]= "00400500";
                    $ruleArr[] = [
                        'type' => "PROMOTION",
                        'applicability' => "CART",
                        'reference' => 'ROUND_OFF',
                        'reference_code' => "00400500",
                        'tax_included_in_amount' => true,
                        'description' => '',
                        'multiplier' => -1,
                        'amount' =>[
                            'cent_amount' => round($roundAmnt*self::FRACTION),
                            'currency' => "INR",
                            'fraction' =>  self::FRACTION
                        ]
                    ];
                }
                $orderAplliety["SHIPPING"]= "00500600";
                $ruleArr[] = [
                    'type' => "CHARGE",
                    'applicability' => "SHIPPING",
                    'reference' => "SHIPPING",
                    'reference_code' => "00500600",
                    'tax_included_in_amount' => true,
                    'description' => "",
                    'multiplier' => 1,
                    'amount' =>[
                    'cent_amount' => $order_data->getShippingInclTax()*self::FRACTION,
                    'currency' => "INR",
                    'fraction' => self::FRACTION
                    ]
                ];
                $dataArray['fulfilment_order']['order_adjustments']= $ruleArr;
                $orderAdjustments = array_keys($orderAplliety);
                foreach ($orderItems as $itemKey=>$item) {
                    $storeId = $this->storeManager->getStore()->getId();
                    $product = $this->productRepository->get($item->getSku(), false, null, true);
                    $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
                    $imageUrl = $this->imageHelper->init($product, 'product_base_image')->getUrl();
                    $baseImgUrl = '';

                    $customImageSource = $this->_scopeConfig->getValue("core_media/service/use_custom_source");
                    if($customImageSource) {
                        if($product->getBaseImageCustom() != '') {
                            $baseImgUrl = $product->getBaseImageCustom();
                        } else {
                            $baseImgUrl = $imageUrl;
                        }
                    } else {
                        $baseImgUrl = $imageUrl;
                    }

                    $rowAmount = $item->getBaseRowTotalInclTax()*self::FRACTION;
                    $baseAmount = $item->getBaseTaxAmount()*self::FRACTION;
                    //$unitePrice = $item->getPrice()*self::FRACTION;
                    $unitePrice = $rowAmount/$item->getQtyOrdered();
                    $taxAmount = ($item->getTaxAmount() > 0)?$item->getTaxAmount()/$item->getQtyOrdered():$item->getTaxAmount();
                    $taxAmount = number_format($taxAmount,4,'.','');
                    $taxAmount = $taxAmount*self::FRACTION;
                    $appliedRule = $item->getAppliedRuleIds();

                    $levelOne = $product->getDepartment();
                    $levelTwo = $product->getClass();
                    $levelThree = $product->getSubclass();

                    $categoryIds = "";
                    if(!empty($product->getIboCategoryId())) {
                        $categoryIds = $product->getIboCategoryId();
                    } else {
                        $rootCategoryName = "Merchandising Category";
                        $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree;
                        $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                        $id = $cateogyrProcessor;
                        if ($id) {
                            $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                            $categoryIds = $category->getCategoryId();
                        }
                    }

                    $quantityUom = $product->getAttributeText('sale_uom');

                    $lotInfo = [];
                    if($order_data->getOrderChannel() == 'STORE') {
                        $quantityUom = $product->getData('sale_uom');
                        if($item->getOrderFulfilmentType() == 'CNC') {
                            if(!empty($item->getLotInfo())) {
                                $lotData = (array)json_decode($item->getLotInfo());
                                foreach ($lotData as $key => $lot) {
                                    $lot = (array)$lot;
                                    $lotInfo[] = array(
                                        "lot_id" => isset($lot['lot_id']) ? $lot['lot_id'] : "",
                                        "lot_params" => isset($lot['lot_params']) ? $lot['lot_params'] : "",
                                        "quantity" => isset($lot['allotted_qty']) ? $lot['allotted_qty'] : ""
                                    );
                                }
                            }
                        }

                        if($item->getFreeShipping()) {
                            if(isset($orderAplliety["SHIPPING"])) {
                                unset($orderAplliety["SHIPPING"]);
                            }
                        } else {
                            $orderAplliety["SHIPPING"]= "00500600";
                        }
                        $orderAdjustments = array_keys($orderAplliety);
                    }

                    $totalDiscount=0;
                    if($order_data->getDiscountAmount() != null){
                        $discountAmount = $order_data->getDiscountAmount() * (-1);
                        $totalDiscount = $discountAmount*self::FRACTION;
                    }
                    $itemCoupn = [];
                    if (!empty($appliedRule)) {
                        $ItemDiscountAmount = $item->getDiscountAmount();
                        $ItemDiscountAmount = ($ItemDiscountAmount >0)?$ItemDiscountAmount/$item->getQtyOrdered():$ItemDiscountAmount;
                        $ItemDiscountAmount = number_format($ItemDiscountAmount,4,'.','');
                        $ItemDiscountAmount = $ItemDiscountAmount*self::FRACTION;
                        $itemCoupn[] =
                            [
                                'type' => "PROMOTION",
                                'applicability' => "ITEM",
                                'reference' => 'MAGENTO_AGGREGATED',
                                'reference_code' => $appliedRule,
                                'description' =>  '',
                                'tax_included_in_amount' => ($order_data->getTaxInclInPromo())?true:false,
                                'multiplier' => -1,
                                'amount' =>[
                                'cent_amount' => $ItemDiscountAmount,
                                'currency' => "INR",
                                'fraction' => self::FRACTION
                                ],
                            ];
                    }
                    $order_line_adjustments = $itemCoupn;

                    $tempArray[$itemKey]= [
                        'order_line_number' =>  !empty($deliverGroupLine) ?  $deliverGroupLine[$item->getSku()] : '',
                        'order_line_id' =>  !empty($item->getItemId()) ?  $item->getItemId() : '',
                        'store_fulfilment_mode' => !empty($item->getOrderFulfilmentType()) ?  $item->getOrderFulfilmentType() : 'DWH',
                        'cart_line_id' =>  !empty($item->getItemId()) ?  $item->getItemId() : '',
                        'item' =>[
                        'fulfillment_class' => $this->getProductFulfillmentClass($product),
                        'offer_id' => !empty($item->getSku()) ?  $item->getSku() : '',
                        'esin' => !empty($product->getEsin()) ?  $product->getEsin() : $item->getSku(),
                        'seller_id' =>!empty($product->getSellerId()) ?  $product->getSellerId() : '',
                        'seller_name' => !empty($product->getSellerName()) ?  $product->getSellerName() : '',
                        'seller_sku_id' => !empty($item->getSku()) ?  $item->getSku() : '',
                        'esin_url' => $this->getEsinUrl($product->getSlug(), $product->getEsin()),
                        'product_origin' => $product->getAttributeText('country_of_origin'),
                        'ebo_title' => !empty($item->getName()) ?  $item->getName() : '',
                        'short_name' => $item->getName(),
                        'brand_id' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                        'brand_name' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                        'product_type' => $product->getEboProductType(),
                        'primary_image_url' =>$baseImgUrl,
                        'category_id' => $categoryIds,
                        'class_id' => substr($categoryIds, 0, 4),
                        'sub_class_id' => $categoryIds,
                        'category_desc' => $levelOne,
                        'class_desc' => $levelTwo,
                        'sub_class_desc' => $levelThree,
                        'is_returnable' => ($order_data->getOrderChannelInfo() == 'CNC') ? false : (($product->getIsReturnable()) ? true : false),
                        // 'is_returnable' => true,
                        'return_window_in_days' =>!empty($product->getReturnWindowInDays()) ?  $product->getReturnWindowInDays() : 0,
                        'requires_shipping' => true,
                        'is_bom' => ($product->getIsBom()) ? true : false,
                        'non_catalog_sku' =>false,
                        'is_lot_controlled' => ($product->getIsLotControlled())?true:false,
                        //'selected_promise_option' =>!empty($nodeId) ? true : false,
                        ],
                        'lots' => $lotInfo,
                        //'parent_line_id' => !empty($item->getItemId()) ?  $item->getItemId() : '',
                        'quantity' =>[
                        'quantity_number' => !empty((int)$item->getQtyOrdered()) ?  (int)$item->getQtyOrdered() : 0,
                        'quantity_uom' => !empty($quantityUom) ?  $quantityUom : "",
                        ],
                        'price_group' => $customerGroup,
                        'price_type' => "DEFAULT",
                        'unit_price' =>[
                        'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                        ],
                        'unit_fulfilled_mrp' => [
                            'cent_amount' => !empty($item->getIboMrp()) ? $item->getIboMrp() * self::FRACTION : 0,
                            'currency' => 'INR',
                            'fraction' => self::FRACTION
                        ],
                        'tax_included_in_price' => ($order_data->getTaxInclInItem())?true:false,
                        'tax_code' => $product->getHsnCode(),
                        'taxes' =>[[
                        'tax_rate' => floatval($item->getTaxPercent()),
                        'type' => "GST",
                                'unit_amount' =>[
                            'currency' => "INR",
                            'cent_amount' =>!empty($taxAmount) ?  $taxAmount : 0,
                            'fraction' => self::FRACTION ],
                                ]],
                        'applicable_order_adjustments' =>  $orderAdjustments,
                        'order_line_adjustments' => $order_line_adjustments,
                        // 'incentives' =>[
                        //     'customer_loyalty' =>[
                        //         'loyalty_type' => "",
                        //         'loyalty_points' => ""
                        //     ],

                        //     'influencer_commission' => [
                        //         '' => ""
                        //     ],
                        //     'fos_commission' => [
                        //         '' => ""
                        //     ],
                        // ],
                    ];

                    if(isset($deliveryParners[$item->getSku()]['assisted_delivery'])) {
                        $tempArray[$itemKey]['assisted_delivery'] = $deliveryParners[$item->getSku()]['assisted_delivery'];
                    }

                    if($orderType == "QUOTE-ORDER") {
                        $itemAdditionalData = !empty($item->getAdditionalData()) ? (array)json_decode($item->getAdditionalData()) : [];
                        if(isset($itemAdditionalData['vendor_code'])) {
                            $tempArray[$itemKey]['vendor_code'] = $itemAdditionalData['vendor_code'];
                        }
                        if(isset($itemAdditionalData['unit_cost_price'])) {
                            $tempArray[$itemKey]['unit_cost_price'] = [
                                'cent_amount' => $itemAdditionalData['unit_cost_price'] * self::FRACTION,
                                'currency' => 'INR',
                                'fraction' => self::FRACTION
                            ];
                        }
                    }

                    $this->appEmulation->stopEnvironmentEmulation();
                }
        $dataArray['fulfilment_order']['order_lines'] = $tempArray;

        $dataArray['fulfilment_order']['totals'] =[
            [
                'type' => "SHIPPING_TOTAL",
                'amount' => [
                  'cent_amount' => $order_data->getShippingAmount()*self::FRACTION,
                  'currency' => "INR",
               'fraction' =>  self::FRACTION
                ],
              ],
              [
                'type' => "DISCOUNT_TOTAL",
                'amount' => [
                  'cent_amount' =>$totalDiscount,
                  'currency' => "INR",
               'fraction' =>  self::FRACTION
                ],
              ],
              [
                'type' => "ITEM_TOTAL",
                'amount' => [
                  'cent_amount' => $order_data->getSubtotalInclTax()*self::FRACTION,
                  'currency' => "INR",
               'fraction' =>  self::FRACTION
                ],
              ],
              [
                'type' => "TAX_TOTAL",
                'amount' => [
                  'cent_amount' => $order_data->getTaxAmount()*self::FRACTION,
                  'currency' => "INR",
               'fraction' =>  self::FRACTION
                ],
              ],
              [
                'type' => "GRAND_TOTAL",
                'amount' => [
                  'cent_amount' => $order_data->getGrandTotal()*self::FRACTION,
                  'currency' => "INR",
               'fraction' =>  self::FRACTION
                ],
              ],


        ];


        $dataArray['fulfilment_order']['payment']['payment_status'] = "SUCCESS";
        $dataArray['fulfilment_order']['payment']['payment_intent_id'] = $paymentIntentId;
        $dataArray['fulfilment_order']['payment']['payment_intent_methods'] =[[
        'payment_option'=> [
        'payment_option_id' => $paymentMethod,
        'type' =>  $paymentMethod,
         'provider' => '',
         'issuer' => ""
        ],
        'transaction_amount' =>[
            'type' => "CHARGE", //CHARGE,REFUND
            'psp_transaction_reference_id' => $paymentIntentId,
            'amount'=> [
                'currency' => "INR",
                'cent_amount' =>  $order_data->getGrandTotal()*self::FRACTION,
                'fraction' => self::FRACTION
            ],
            'transaction_datetime' => $createdDate
        ]
        ],
        ];
        $dataArray['fulfilment_order']['billing_instruction']['invoice_type'] = "";
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['address_id'] = !empty($order_data->getBillingAddressId()) ? $order_data->getBillingAddressId() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['address_line1'] = !empty($billingStreet[0]) ? $billingStreet[0] : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['address_line2'] = !empty($billingStreet[1]) ? $billingStreet[1] : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['address_line3'] = !empty($billingStreet[2]) ? $billingStreet[2] : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['landmark'] = !empty($billingaddress->getLandmark()) ? $billingaddress->getLandmark() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['municipal'] = "";
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['city'] = !empty($billingaddress->getCity()) ? $billingaddress->getCity() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['state_code'] = $this->getStateId($billingaddress->getRegionCode());
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['state'] = !empty($billingaddress->getRegionCode()) ? $billingaddress->getRegionCode() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['country_code'] = !empty($billingaddress->getCountryId()) ? $billingaddress->getCountryId() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['country'] = !empty($billingaddress) ? $this->getCountryname($billingaddress->getCountryId()) : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['post_code'] =  !empty($billingaddress->getPostcode()) ? $billingaddress->getPostcode() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['geo_location']['latitude'] = $latitude;
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['geo_location']['longitude'] = $longitude;
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['email_id'] = !empty($billingaddress->getEmail()) ? $billingaddress->getEmail() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['phone_number']['country_code'] = "+91";
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['phone_number']['number'] = !empty($billingaddress->getTelephone()) ? $billingaddress->getTelephone() : '';
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['fax']['country_code'] = "+91";
        $dataArray['fulfilment_order']['billing_instruction']['billing_address']['fax']['number'] = "";
        $dataArray['fulfilment_order']['gift_info']['is_gift'] =false;
        $dataArray['fulfilment_order']['gift_info']['gift_wrap'] = false;
        $dataArray['fulfilment_order']['gift_info']['gift_message'] = "";
        $dataArray['fulfilment_order']['gift_info']['invoice_type'] = "";

        $dataArray['fulfilment_order']['custom_info'] = [];
        if($customerGroup == 'B2P') {
            $incentiveMode = "cash-back";
            $orderAdditionalJsonData = $order_data->getAdditionalData();
            if(!empty($orderAdditionalJsonData)) {
                $orderAdditionalData = (array)json_decode($orderAdditionalJsonData);
                if(($orderAdditionalData['on_invoice_promotion']) && $orderAdditionalData['on_invoice_promotion']) {
                    $incentiveMode = "on-invoice";
                }
            }
            $dataArray['fulfilment_order']['custom_info'][] = [
                "group" => "b2p-incentive",
                "id" => "incentive-mode",
                "values" => [$incentiveMode],
                "additional_info" => ""
            ];
        }

        if($order_data->getProfessionalNumber()) {
            $referredCustomerId = $referrercustomerGroup = '';
            $customerReferrerData = $this->validateMobile->isMobileAssociatedToCustomer($order_data->getProfessionalNumber());
            if($customerReferrerData->getId()) {
                $referredCustomerId = $customerReferrerData->getId();
                $referrercustomerGroup = $this->getGroupName($customerReferrerData->getGroupId());
            }
            $dataArray['fulfilment_order']['custom_info'][] = [
                "group" => "referrer",
                "id" => "customer_id",
                "values" => [$referredCustomerId],
                "additional_info" => ""
            ];
            $dataArray['fulfilment_order']['custom_info'][] = [
                "group" => "referrer",
                "id" => "customer_group",
                "values" => [$referrercustomerGroup],
                "additional_info" => ""
            ];
            $dataArray['fulfilment_order']['custom_info'][] = [
                "group" => "referrer",
                "id" => "phone_number",
                "values" => [$order_data->getProfessionalNumber()],
                "additional_info" => ""
            ];
        }

        if(empty($dataArray['fulfilment_order']['custom_info']))  {
            $dataArray['fulfilment_order']['custom_info'] = [[
                "group" => "1",
                "id" => "1",
                "values" => [],
                "additional_info" => ""
            ]];
        }

        $dataArray['fulfilment_order']['device_fingerprint'] =[
            'fp' => [[
                'fp_type' => "",
                'fp_value' => "",
            ],],

         'fp_time' => ""

        ];
        $dataArray['fulfilment_order']['audit']['api_version'] = "";
        $dataArray['fulfilment_order']['audit']['created_at'] = "";
        $dataArray['fulfilment_order']['audit']['created_by'] = "";
        $dataArray['fulfilment_order']['audit']['last_modified_at'] = "";
        $dataArray['fulfilment_order']['audit']['last_modified_by'] = "";

        $payload = json_encode($dataArray);

    }
        return $payload;
    }

    public function SuccessOrderExecute($orderID)
    {
        $resultData = [];
        $date = date("y_m_d");
        $this->addLog("Enter Succes order Execute " .$orderID,"oms-promise.log");
        $url = $this->getFulFillmentApiEndPoint();
        $params = $this->orderRequestBody($orderID);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_POST, true);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);
        try {
            $this->addLog('Order Selection Curl Initiated',"oms-promise.log");
             $this->addLog($params,"oms-promise.log");

            $startTime = microtime(true);
            $this->addLog("Promise call Start time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$startTime, "oms-promise.log");

            $this->curl->post($url, $params);

            $endTime = microtime(true);
            $this->addLog("Promise call End time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$endTime. ", Difference in milliseconds:
                        ".number_format($endTime - $startTime, 5)/1000,
                "oms-promise.log");

            // Code to update oms_status_flag afte order push
            $order = $this->OrderInterface->load($orderID);
            $oms_status_flag = $order->getData('oms_status_flag');
            $this->addLog(" oms_status_flag ".$oms_status_flag,"oms-promise.log");
            $count = $this->getStatusCount(); // count need to set the admin
             $this->addLog(" count ".$count,"oms-promise.log");
            if(!empty($order->getData('coupon_code'))){
                $this->irOrderModel->create(['order_id' => $orderID,'partner_type' => 'ir_order_conversion']);
            }

            $iboOrderNumber = $order->getIncrementId();
            if ($oms_status_flag < $count) {
                $oms_status_flag++;
                $order->setData('oms_status_flag', $oms_status_flag);
                $order->save();
                $this->addLog("Status Updated in Flag","oms-promise.log");
            // $this->addLog($resultData['errors']);
            }
            if($oms_status_flag == $count) {
                $this->sendRetryOrderToOms($orderID, $iboOrderNumber);
            }
            $returnResult['error'] = "Error :";

            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
        } catch (\Exception $e) {
            $this->addLog($e->getMessage(),"oms-promise.log");
            $returnResult['error'] = "Error : ".$e->getMessage();
        }

        if (!empty($resultData) && is_array($resultData)) {
            $this->addLog('Order Curl Result'.json_encode($resultData),"oms-promise.log");
            // if (isset($resultData['errors'])) {
            //     $order = $this->OrderInterface->load($orderID);
            //      $oms_status_flag = $order->getData('oms_status_flag');
            //       $count = $this->getStatusCount(); // count need to set the admin

            //     if ($oms_status_flag < $count) {
            //         $oms_status_flag++;
            //         $order->setData('oms_status_flag', $oms_status_flag);
            //         $order->save();
            //         $this->addLog("Status Updated in Flag","oms-promise.log");
            //        // $this->addLog($resultData['errors']);
            //     }
            //     $returnResult['error'] = "Error :";
            // } else {
                if (isset($resultData['success']) && isset($resultData['success']['order_number']) && !empty($resultData['success']['order_number'])) {
                    $order = $this->OrderInterface->load($orderID);
                    $order->setData('oms_status_flag', 200);
                    // if($order->getPayment()->getMethod() == 'cashondelivery' || $order->getPayment()->getMethod() == 'free') {
                    //     $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    //     $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    // }
                    $order->addStatusToHistory($order->getStatus(), 'Order pushed to OMS successfully.');
                    $order->save();
                    $this->addLog('Order Id succesfully Generated - ' . $orderID,"oms-promise.log");
                }
           // }
        } else {
            $this->addLog("Error : No ResultData " . $orderID,"oms-promise.log");
        }
        //return $returnResult;
    }

    public function OrderFillFullmentResponse()
    {
        $date = date("y_m_d");
      $currentTime = date("Y-m-d H:i:s", strtotime("-1 minutes"));
        $this->addLog("Enter Order Fullfillment Response", "oms-promise.log");
       $count = ($this->getStatusCount()) ? $this->getStatusCount() : 0;
        $statuses = ['pending_payment','payment_failed','canceled'];
        $collection = $this->_orderCollectionFactory->create()
        ->addAttributeToSelect('entity_id')->addFieldToFilter('oms_status_flag', array('lt' =>  $count))->addFieldToFilter('status', ['nin' => $statuses])->addFieldToFilter('created_at', array('lt' =>  $currentTime)); //Add condition if
        $collection->getSelect()->limit(1);
        
        if (!(empty($collection))) {
            foreach ($collection as $data) {
                 $orderId = $data->getId();
                 $order = $this->OrderInterface->load($orderId);
                  $this->addLog($orderId, "oms-promise.log");
                 if($order->getStatus() == 'processing'){
                     $this->addLog($order->getStatus(), "oms-promise.log");
                     $this->SuccessOrderExecute($orderId);
                //  } else if($order->getPayment()->getMethod() == 'cashondelivery' && $order->getStatus() == 'pending'){
                //      $this->SuccessOrderExecute($orderId);
                //  } else if($order->getPayment()->getMethod() == 'free' && $order->getStatus() == 'pending'){
                //     $this->SuccessOrderExecute($orderId);
                }else{
                    $this->addLog("Unable to push order ".$orderId,"oms-promise.log");

                    $oms_status_flag = $order->getData('oms_status_flag');
                    $count = $this->getStatusCount(); // count need to set the admin
                    $order->setData('oms_status_flag', $count + 1);
                    $order->save();
                 }
            }
        } else {
            $this->addLog("No order created this times","oms-promise.log");
        }
    }
    public function pinCodeSeriveCheck($pincodes) {
        $pincodeUrl = $this->getPincodeServiceCheckAPI();
        //$pincodes = '';
        $this->addLog('PinCode Check Request, Response');
        $url = $pincodeUrl.'?post-code='.$pincodes;
        $this->addLog($url);
        $headers = ["Content-Type" => "application/json", "trace_id" => $this->getTraceId(), "client_id" => $this->getClientId()];
        $this->curl->setHeaders($headers);
        $this->curl->get($url);
        $result = $this->curl->getBody();
        $this->addLog(json_decode($result,true));

        return $result;
    }

    public function getCustomerOrderInfo()
    {
        $hourRecord = !empty($this->_scopeConfig->getValue("ebo_customer_export/moengage_cron/order_records_per_hour")) ? $this->_scopeConfig->getValue("ebo_customer_export/moengage_cron/order_records_per_hour") : "24 hour";
        $salesOrderTable = $this->connection->getTableName('sales_order');
        $query = "SELECT o.entity_id,o.applied_rule_ids  FROM " . $salesOrderTable . " o "
                 . "WHERE o.order_channel = 'STORE' AND moengage_status_flag = 0 "
                 . "AND status = 'processing' AND (created_at >= date_sub(now(),interval " . $hourRecord . "))";

        return $this->connection->fetchAll($query);
    }

    public function getRuleNames($ruleIds){
        $query = "SELECT GROUP_CONCAT(name) AS rule_name FROM salesrule WHERE rule_id IN (" . $ruleIds . ")";

        return $this->connection->fetchAll($query);
    }


    private function resoruceConnection(){
        if(!$this->connection){
            $this->connection = $this->resourceConnection->getConnection();
        }

        return $this->connection;
    }

    public function setMoengageFlag($orderIds){
        $this->connection->query("UPDATE sales_order SET moengage_status_flag = 1 WHERE entity_id IN (" . implode(',', $orderIds) .")");
    }

    public function addLog($logData, $filename = "promise-engine-api.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {

        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }

    private function getPosOrderData($orderId) {
        $posOrderTable = $this->resourceConnection->getTableName('ah_supermax_pos_orders');
        $posOutletTable = $this->resourceConnection->getTableName('ah_supermax_pos_outlet');
        $posUserTable = $this->resourceConnection->getTableName('ah_supermax_pos_user');
        $posTerminalTable = $this->resourceConnection->getTableName('ah_supermax_pos_terminals');
        $quoteTable = $this->resourceConnection->getTableName('quote');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $posQuoteTable = $this->resourceConnection->getTableName('ah_supermax_pos_quote');
        $query = "SELECT pu.username as pos_user_id, pt.code as pos_terminal_id, ot.store_id, po.device_type as order_device_type, po.additional_data as order_additional_data, (SELECT username FROM $posUserTable WHERE pos_user_id = po.sales_associate_1) AS order_sales_associate_1, (SELECT username FROM $posUserTable WHERE pos_user_id = po.sales_associate_2) AS order_sales_associate_2, (SELECT created_at FROM $salesOrderTable WHERE entity_id = $orderId) AS order_creation_time, (SELECT created_at FROM $quoteTable WHERE reserved_order_id = (SELECT increment_id FROM $salesOrderTable WHERE entity_id = $orderId)) AS quote_creation_time, (SELECT COUNT(*) FROM $posQuoteTable WHERE quote_id = (SELECT quote_id FROM $salesOrderTable WHERE entity_id = $orderId)) AS is_hold_cart, (SELECT hold_time FROM $posQuoteTable WHERE quote_id = (SELECT quote_id FROM $salesOrderTable WHERE entity_id = $orderId)) AS hold_time, (SELECT additional_data FROM $posQuoteTable WHERE quote_id = (SELECT quote_id FROM $salesOrderTable WHERE entity_id = $orderId)) AS hold_cart_additional_data FROM $posOrderTable as po LEFT JOIN $posOutletTable as ot ON(po.pos_outlet_id = ot.pos_outlet_id) LEFT JOIN $posUserTable as pu ON(po.pos_user_id = pu.pos_user_id) LEFT JOIN $posTerminalTable as pt ON(po.pos_terminal_id = pt.pos_terminal_id) WHERE po.order_id= $orderId";
        return $this->connection->query($query)->fetch();
    }

    /**
     * @ticket: MAG-1621 First time promotion
     * @desc : Update customer attribute first_time_promo_applied
     *         if first time promo used in order.
     *
     */
    public function updateFirstTimePromoAttribute($appledRuleIds, $customerId, $attrValue=true) {
        $isModuleEnable = $this->_scopeConfig->getValue(
            "first_time_promotion/first_time_promotion_group/first_time_promotion_enabled"
        );

        if($isModuleEnable) {
            $firstTimePromoRuleIds = $this->_scopeConfig->getValue(
                "first_time_promotion/first_time_promotion_group/first_time_promotion_rule_id"
            );
            $this->addLog('Appled Rule ids: '. $appledRuleIds, "first_time_promo.log");
            if(!empty($firstTimePromoRuleIds)) {
                //Rule ids from configuration
                $firstTimePromoRuleIdsArray = explode(',', $firstTimePromoRuleIds);
                //Rule ids applied in order
                $appledRuleIds = explode(',', $appledRuleIds);
                //Find Matching rule id
                $appliedFirstTimeRuleId = array_intersect($appledRuleIds,
                    $firstTimePromoRuleIdsArray);
                
                if(!empty($appliedFirstTimeRuleId) && count($appliedFirstTimeRuleId) > 0) {
                    $this->addLog('Customer Id: '.$customerId, "first_time_promo.log");
                    $this->addLog('First Time Promo applied in order', "first_time_promo.log");
                    $custmer = $this->customerRepository->getById($customerId);
                    $customerData = Array(
                       'first_time_promo_applied' => $attrValue
                    );
                    $this->updateCustomerAccount->execute($custmer, $customerData,
                        $this->storeManager->getStore());
                }
            }
        }
    }

    public function sendRetryOrderToOms($orderId, $iboOrderNumber) {
        $this->addLog("iboOrderNumber " . $iboOrderNumber, "orderToPushoms.log");
        $duplicatedLineItems = $this->getOrderDuplicateItems($orderId);
        $Orderdata = !empty($duplicatedLineItems) ? ($iboOrderNumber . ": Duplicate line items issue.") : $iboOrderNumber;
        $this->addLog("Orderdata " . $Orderdata . " entity id " . $orderId, "orderToPushoms.log");    
        $this->addLog("==========================================", "orderToPushoms.log");
        $this->addLog("Start order failure email sending", "orderToPushoms.log");
        $receiverEmails = $this->_scopeConfig->getValue("email_reciever/order_reciever_cron/order_reciever_email",\Magento\Store\Model\ScopeInterface::SCOPE_STORE, null);
        $receiverName = $this->_scopeConfig->getValue("email_reciever/order_reciever_cron/order_reciever_name",\Magento\Store\Model\ScopeInterface::SCOPE_STORE, null);
        $receiver_emails = $this->email_info->setReceiverEmail($receiverEmails);
        $receiver_Name = $this->email_info->setReceiverName($receiverName);
        $subject = $this->email_info->setSubject('Order not push in Oms');
        $bcc = $this->email_info->setBcc("");
        $cc = $this->email_info->setCc("");
        $attachmentUrl = $this->email_info->setAttachmentUrl("");
        $Content = $this->email_info->setContent($Orderdata);
        $this->addLog("order Ids to be sent in the email: " . $iboOrderNumber, "orderToPushoms.log");
        $this->emailhelper->sendGridMail($receiver_emails, $receiver_Name, $subject, $bcc, $cc, $attachmentUrl, $Content);
        $this->addLog("End order failure email sending", "orderToPushoms.log");
        $this->addLog("==========================================", "orderToPushoms.log");
    }


    private function getEsinUrl($slug,$esin) {
        if(($slug != '') && ($esin != '')) {
            $baseuRl = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/feed_product_base_url");
            $canonicalUrl = $baseuRl.$slug.'/p/'.$esin;
        } else {
            $canonicalUrl = '';
        }
        return $canonicalUrl;
    }

    private function getOrderDuplicateItems($orderId) {
        $salesOrderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $result = $this->connection->query("SELECT * FROM $salesOrderItemTable WHERE order_id = $orderId GROUP BY sku, product_id HAVING COUNT(*) > 1")->fetchAll();
        return $result;
    }

    private function getProductFulfillmentClass($product) {
        $categoryFulfillmentClass = "";
        $productIboCategoryId = $product->getIboCategoryId();
        $category = $this->categoryCollection->create()->getCollection()
                        ->addAttributeTofilter('category_id',array($productIboCategoryId))
                         ->addAttributeTofilter('category_type',"MERCHANDISING");
        if (isset($category->getData()[0]['entity_id'])) {
            $categoryObj = $this->categoryRepository->get($category->getData()[0]['entity_id'], $this->storeManager->getStore()->getId());
            $categoryFulfillmentClass = !empty($categoryObj->getCategoryFulfillmentClass()) ? $categoryObj->getCategoryFulfillmentClass() : "";
        }
        return $categoryFulfillmentClass;
    }
}
