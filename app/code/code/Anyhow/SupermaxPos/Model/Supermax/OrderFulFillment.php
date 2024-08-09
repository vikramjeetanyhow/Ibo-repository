<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class OrderFulFillment implements \Anyhow\SupermaxPos\Api\Supermax\OrderFulfillmentInterface
{
    CONST FRACTION = 10000;

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $attributeCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Embitel\ProductImport\Model\CategoryProcessor $categoryProcessor,
        \Embitel\ProductImport\Model\Import\ProductFieldProcessor $productFieldProcessor,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Sales\Model\Order $orderData,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Embitel\Quote\Helper\Data $embitelHelper
    ){
        $this->helper = $helper;
        $this->resource = $resource;
        $this->customerRepository = $customerRepository;
        $this->orderRepository = $orderRepository;
        $this->groupRepository = $groupRepository;
        $this->eavConfig = $eavConfig;
        $this->attributeCollection = $attributeCollection;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->imageHelper = $imageHelper;
        $this->categoryProcessor = $categoryProcessor;
        $this->categoryRepository = $categoryRepository;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->countryFactory = $countryFactory;
        $this->orderData = $orderData;
        $this->supermaxSession = $supermaxSession;
        $this->embitelHelper = $embitelHelper;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function orderFulfillment() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                // $userId = $this->supermaxSession->getPosUserId();
                // $nodeId = $this->getUserData($userId);
                $params = $this->helper->getParams();
                $orderId = $params['orderId'];
                $this->embitelHelper->SuccessOrderExecute($orderId); 

                // $data = $this->getOrderFulfillmentPayload($orderId, $nodeId);
                // $clientId = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_client_id", $storeId = null);
                // $header = array(
                //     "Content-Type: application/json",
                //     "client_id:" . $clientId,
                //     "trace_id:" . $clientId
                // );
                
                // $orderFulfillmentApiUrl = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_order_status", $storeId = null);

                // $apiResponse = $this->helper->curlPostRequest($orderFulfillmentApiUrl, $data, $header);
                // $result = json_decode($apiResponse);           
                // if($result->success) {
                //     $this->updateOrder($orderId);
                // }        
            } else {
                $error = true;  
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    private function getOrderFulfillmentPayload($orderId, $nodeId) {
        $dataArray = [];
        $order_data = $this->orderRepository->get($orderId);
        if($order_data->getId()) { 
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
            $approvalStatus = false;
            $latitude = '';
            $longitude = '';
            $docNo = rand();
            if($customer->getId()) {
                $groupId = $customer->getGroupId();
                $customerGroup = $this->getGroupName($groupId);
                $isB2BCustomer = ($customerGroup == 'B2B') ? true : false;
                $optionlabel = ""; 
                if(null !== ($customer->getCustomAttribute('approval_status'))) { 
                    $approvalStatus = $customer->getCustomAttribute('approval_status')->getValue();  
                    $attribute = $this->eavConfig->getAttribute('customer', "approval_status"); 
                    $optionlabel = $attribute->getSource()->getOptionText($approvalStatus); 
                }
                $approvalStatus = (isset($approvalStatus)) ? true : false;
                // $this->addLog($approvalStatus->getValue());
                if(null !== ($customer->getCustomAttribute('latitude'))) {
                    $latitude = $customer->getCustomAttribute('latitude')->getValue();
                }
                if(null !== ($customer->getCustomAttribute('longitude'))) {
                    $longitude = $customer->getCustomAttribute('longitude')->getValue();
                }
                $docNo = $order_data->getCustomerTaxvat();
            }
                
            $paymentData = $this->getPaymentData($orderId);
            $paymentIntentId = $paymentData['payment_intent_id'];
            $paymentMethod = ($paymentData['payment_code'] == 'PAY-ON-DELIVERY') ? 'POD' : 'PREPAID';
			
            // $method = $payment->getMethodInstance();
            // $paymentMethod = $method->getTitle(); // Cash On Delivery
            // $paymentCode = $method->getCode(); // cashondelivery
            $appliedRule = $order_data->getAppliedRuleIds();
            $promiseOptions = json_decode($order_data->getPromise_options(), true);
            $deliveryGroup = json_decode($order_data->getDelivery_group(), true);
            $deliverGroupLine = [];
            if (!(empty($deliveryGroup))) {
                foreach ($deliveryGroup as $newkey => $delivery) {
                    foreach($delivery['delivery_group_lines'] as $deliveygroup_line) {
                        $offerId = $deliveygroup_line['item']['offer_id'];
                        $deliverGroupLine[$offerId] = isset($deliveygroup_line['promise_line_id']) ? $deliveygroup_line['promise_line_id'] : '';
                    }
                }
            }

            // $nodeId = '';
            // if (!(empty($promiseOptions))) {
            //     foreach ($promiseOptions as $newkey => $promiseOption) {
            //         $nodeId = isset($promiseOptions[$newkey]['node_id']) ? $promiseOptions[$newkey]['node_id'] : '';
            //     }
            // } 
            $customerType = $this->getCustomerAttributeValue($customer, "customer_type");
            $date = strtotime($order_data->getCreatedAt());
            $createdDate = date('Y-m-d\TH:i:s\Z', $date);
            $municipal = '';
            
            $dataArray['fulfilment_order']['order_number'] = $order_data->getIncrementId();
            $dataArray['fulfilment_order']['version'] = "v1";
            $dataArray['fulfilment_order']['order_type'] = "CUSTOMER-ORDER"; 
            $dataArray['fulfilment_order']['order_subtype'] = "CNC"; 
            $dataArray['fulfilment_order']['created_at'] = $createdDate;
            $dataArray['fulfilment_order']['cart_id'] = $order_data->getQuoteId();
            $dataArray['fulfilment_order']['cart_origin'] = 'STORE';
            $dataArray['fulfilment_order']['customer']['customer_id'] = !empty($order_data->getCustomerId()) ? $order_data->getCustomerId() : '';
            if(isset($optionlabel) && strtolower($optionlabel) == "approved") { 
                $dataArray['fulfilment_order']['customer']['customer_group'] = !empty($customerGroup) ? $customerGroup : ''; 
                $dataArray['fulfilment_order']['customer']['customer_type'] = ($customerType) ? $customerType : "Individual";
            } else { 
                $dataArray['fulfilment_order']['customer']['customer_group'] = 'B2C'; 
                $dataArray['fulfilment_order']['customer']['customer_type'] = "Individual";

            }
            // $dataArray['fulfilment_order']['customer']['account_id'] = "123e4567-e8d9-12d3-a456-556642440000";
            $dataArray['fulfilment_order']['customer']['customer_name']['salutation'] = '';
            $dataArray['fulfilment_order']['customer']['customer_name']['first_name'] = $shippingaddress->getFirstName();
            $dataArray['fulfilment_order']['customer']['customer_name']['middle_name'] = '';
            $dataArray['fulfilment_order']['customer']['customer_name']['last_name'] = $shippingaddress->getLastName();
            $dataArray['fulfilment_order']['customer']['customer_name']['suffix'] = !empty($order_data->getCustomerSuffix()) ?  $order_data->getCustomerSuffix() : '';
            $dataArray['fulfilment_order']['customer']['email_id'] = !empty($order_data->getCustomerEmail()) ?  $order_data->getCustomerEmail() : '';
            $dataArray['fulfilment_order']['customer']['phone_number'] = [
                'country_code' => "+91",
                'number' => !empty($billingaddress->getTelephone()) ? $billingaddress->getTelephone() : ''
            ];
            $dataArray['fulfilment_order']['customer']['is_b2b_customer'] = $isB2BCustomer;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['entity_id'] = $customer->getId();
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['type'] = "GST";
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['country'] = "IN";
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['number'] = $docNo;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['document']['is_verified'] =  $approvalStatus;
            $dataArray['fulfilment_order']['customer']['b2b_customer']['trade_name'] = $billingaddress->getFirstName()." ".$billingaddress->getLastName();
            $dataArray['fulfilment_order']['shipping_address']['address_id'] = !empty($shippingaddress->getId()) ? $shippingaddress->getId() : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line1'] = !empty($shippingStreet[0]) ? $shippingStreet[0] : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line2'] = !empty($shippingStreet[1]) ? $shippingStreet[1] : '';
            $dataArray['fulfilment_order']['shipping_address']['address_line3'] = !empty($shippingStreet[2]) ? $shippingStreet[2] : '';
            $dataArray['fulfilment_order']['shipping_address']['landmark'] = !empty($shippingaddress->getLandmark()) ? $shippingaddress->getLandmark() : '';
            $dataArray['fulfilment_order']['shipping_address']['municipal'] = !empty($municipal) ? $municipal : '';
            $dataArray['fulfilment_order']['shipping_address']['city'] = !empty($shippingaddress->getCity()) ? $shippingaddress->getCity() : '';
            $dataArray['fulfilment_order']['shipping_address']['state_code'] = $this->getStateId($shippingaddress->getRegionCode());
            $dataArray['fulfilment_order']['shipping_address']['state'] = !empty($shippingaddress->getRegionCode()) ? $shippingaddress->getRegionCode() : '';
            $dataArray['fulfilment_order']['shipping_address']['country_code'] = !empty($shippingaddress->getCountryId()) ? $shippingaddress->getCountryId() : '';
            $dataArray['fulfilment_order']['shipping_address']['country'] = !empty($shippingaddress) ? $this->getCountryname($shippingaddress->getCountryId()) : '';
            $dataArray['fulfilment_order']['shipping_address']['post_code'] = !empty($shippingaddress->getPostcode()) ?  $shippingaddress->getPostcode() : '';
            $dataArray['fulfilment_order']['shipping_address']['geo_location']['latitude'] = $latitude;
            $dataArray['fulfilment_order']['shipping_address']['geo_location']['longitude'] = $longitude;
            $dataArray['fulfilment_order']['shipping_address']['email_id'] = !empty($shippingaddress->getEmail()) ?  $shippingaddress->getEmail() : '';
            $dataArray['fulfilment_order']['shipping_address']['phone_number']['country_code'] = "+91";
            $dataArray['fulfilment_order']['shipping_address']['phone_number']['number'] = !empty($shippingaddress->getTelephone()) ?  $shippingaddress->getTelephone() : '';
            $dataArray['fulfilment_order']['shipping_address']['fax']['country_code'] = "";
            $dataArray['fulfilment_order']['shipping_address']['fax']['number'] = "";
            // $dataArray['fulfilment_order']['pickup']['node_id'] = !empty($nodeId) ? $nodeId  : ''; 
            $dataArray['fulfilment_order']['order_channel'] = [ 
                'channel' => "STORE", 
                'channel_info' =>[
                    [ 
                        'group' => "", 
                        'name' => "", 
                        'values' => [], 
                    ], 
                ], 
            ]; 
            
            $dataArray['fulfilment_order']['pickup']['node_id'] = $nodeId;

            $ruleArr = []; 
            $orderAplliety = []; 
            if ($order_data->getBaseGrandTotal() <> $order_data->getGrandTotal()) { 
                $roundAmnt = $order_data->getBaseGrandTotal() - $order_data->getGrandTotal();
                $orderAplliety["ROUND_OFF"] = "00400500";
                $ruleArr[] = [ 
                    'type' => "PROMOTION", 
                    'applicability' => "CART",
                    'reference' => 'ROUND_OFF',
                    'reference_code' => "00400500",
                    'tax_included_in_amount' => true,
                    'description' => '',
                    'multiplier' => -1,
                    'amount' => [
                        'cent_amount' => round($roundAmnt * self::FRACTION),
                        'currency' => "INR",
                        'fraction' =>  self::FRACTION
                    ]
                ]; 
            } 
            $orderAplliety["SHIPPING"] = "00500600"; 
            $ruleArr[] = [
                'type' => "CHARGE",
                'applicability' => "SHIPPING",
                'reference' => "SHIPPING",
                'reference_code' => "00500600", 
                'tax_included_in_amount' => true,
                'description' => "",
                'multiplier' => 1,
                'amount' => [
                    'cent_amount' => $order_data->getShippingInclTax() * self::FRACTION,
                    'currency' => "INR",
                    'fraction' => self::FRACTION
                ]
            ];  

            $dataArray['fulfilment_order']['order_adjustments'] = $ruleArr; 
            $orderAdjustments = array_keys($orderAplliety); 
            foreach ($orderItems as $item) {
                $storeId = $this->storeManager->getStore()->getId();
                $product = $this->productRepository->getById($item->getProductId(), false, null, true);
                $imageUrl = $this->imageHelper->init($product, 'product_base_image')->getUrl();
                $rowAmount = $item->getBaseRowTotalInclTax() * self::FRACTION;
                $baseAmount = $item->getBaseTaxAmount() * self::FRACTION;
                $unitePrice = ($order_data->getTaxInclInItem()) ? $item->getPriceInclTax() * self::FRACTION : $item->getPrice() * self::FRACTION;
                $taxAmount = ($item->getTaxAmount() > 0) ? $item->getTaxAmount()/$item->getQtyOrdered() : $item->getTaxAmount(); 
                $taxAmount = number_format($taxAmount, 4, '.', '');
                $taxAmount = $taxAmount * self::FRACTION;
                $appliedRule = $item->getAppliedRuleIds();
                $categoryIds = ""; 
                $levelOne = $product->getDepartment();
                $levelTwo = $product->getClass();
                $levelThree = $product->getSubclass();

                $rootCategoryName = "Merchandising Category"; 
                $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree; 
                $id = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                if($id) { 
                    $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                    $categoryIds = $category->getCategoryId();
                }

                $quantityUom = $product->getAttributeText('sale_uom');
               
                $totalDiscount=0;
                if($order_data->getDiscountAmount() != null) { 
                    $discountAmount = $order_data->getDiscountAmount() * (-1);
                    $totalDiscount = $discountAmount * self::FRACTION;
                }
                $itemCoupn = [];
                if (!empty($appliedRule)) { 
                    $ItemDiscountAmount = $item->getDiscountAmount();
                    $ItemDiscountAmount = ($ItemDiscountAmount >0) ? $ItemDiscountAmount/$item->getQtyOrdered() : $ItemDiscountAmount; 
                    $ItemDiscountAmount = number_format($ItemDiscountAmount, 4, '.', '');
                    $ItemDiscountAmount = $ItemDiscountAmount * self::FRACTION;
                    $itemCoupn[] = [   
                        'type' => "PROMOTION",
                        'applicability' => "ITEM",
                        'reference' => 'MAGENTO_AGGREGATED',
                        'reference_code' => $appliedRule,
                        'description' =>  '',
                        'tax_included_in_amount' => ($order_data->getTaxInclInPromo()) ? true : false,
                        'multiplier' => -1,
                        'amount' => [
                            'cent_amount' => $ItemDiscountAmount,
                            'currency' => "INR",
                            'fraction' => self::FRACTION
                        ],
                    ];
                } 
                $order_line_adjustments = $itemCoupn;
                         
                $tempArray[]= [
                    'order_line_number' =>  !empty($deliverGroupLine) ?  $deliverGroupLine[$item->getSku()] : '',  
                    'order_line_id' =>  !empty($item->getItemId()) ?  $item->getItemId() : '',
                    'cart_line_id' =>  !empty($item->getItemId()) ?  $item->getItemId() : '',
                    'store_fulfilment_mode' => 'CNC',
                    'order_subtype' => "CNC", 
                    'item' => [
                        'offer_id' => !empty($item->getSku()) ?  $item->getSku() : '',
                        'esin' => !empty($product->getEsin()) ?  $product->getEsin() : $item->getSku(),
                        'seller_id' =>!empty($product->getSellerId()) ?  $product->getSellerId() : '',
                        'seller_name' => !empty($product->getSellerName()) ?  $product->getSellerName() : '',
                        'seller_sku_id' => !empty($item->getSku()) ?  $item->getSku() : '',
                        'esin_url' => $product->getProductUrl(),
                        'product_origin' => $product->getAttributeText('country_of_origin'),
                        'ebo_title' => !empty($item->getName()) ?  $item->getName() : '',
                        'short_name' => $item->getName(),
                        'brand_id' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                        'brand_name' => !empty($product->getAttributeText('brand_Id')) ? $this->productFieldProcessor->getBrandNameById(strtolower($product->getAttributeText('brand_Id'))) : '',
                        'product_type' => $product->getEboProductType(),
                        'primary_image_url' => !empty($imageUrl) ?  $imageUrl : '',
                        'category_id' => $categoryIds,
                        // 'is_returnable' => ($product->getIsReturnable()) ? true : false,
                        'is_returnable' => false,
                        'return_window_in_days' => !empty($product->getReturnWindowInDays()) ?  $product->getReturnWindowInDays() : 0,
                        'requires_shipping' => true,
                        'is_bom' => ($product->getIsBom()) ? true : false,
                        'non_catalog_sku' => false,
                        'is_lot_controlled' => ($product->getIsLotControlled()) ? true : false,
                        //'selected_promise_option' =>!empty($nodeId) ? true : false, 
                    ],
                    //'parent_line_id' => !empty($item->getItemId()) ?  $item->getItemId() : '',
                    'quantity' => [
                        'quantity_number' => !empty((int)$item->getQtyOrdered()) ?  (int)$item->getQtyOrdered() : 0,
                        'quantity_uom' => !empty($quantityUom) ?  $quantityUom : "", 
                    ],
                    'price_group' => ($isB2BCustomer) ? "B2B" : "B2P",
                    'price_type' => "DEFAULT",
                    'unit_price' => [
                        'cent_amount' => !empty($unitePrice) ?  $unitePrice : 0,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                    ],
                    'tax_included_in_price' => ($order_data->getTaxInclInItem()) ? true : false,
                    'tax_code' => $product->getHsnCode(), 
                    'taxes' =>[
                        [
                            'tax_rate' => floatval($item->getTaxPercent()),
                            'type' => "GST",
                            'unit_amount' => [
                                'currency' => "INR",
                                'cent_amount' => !empty($taxAmount) ?  $taxAmount : 0,
                                'fraction' => self::FRACTION 
                            ],
                        ]
                    ],
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
            }
            $dataArray['fulfilment_order']['order_lines'] = $tempArray;

            $dataArray['fulfilment_order']['totals'] = [
                [
                    'type' => "SHIPPING_TOTAL",
                    'amount' => [
                        'cent_amount' => $order_data->getShippingAmount() * self::FRACTION,
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
                        'cent_amount' => $order_data->getSubTotal() * self::FRACTION,
                        'currency' => "INR",
                        'fraction' =>  self::FRACTION
                    ], 
                ],
                [
                    'type' => "TAX_TOTAL",
                    'amount' => [
                        'cent_amount' => $order_data->getTaxAmount() * self::FRACTION,
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
            $dataArray['fulfilment_order']['payment']['payment_intent_methods'] = [
                [
                    'payment_option'=> [
                        'payment_option_id' => $paymentMethod,
                        'type' =>  $paymentMethod,
                        'provider' => '',
                        'issuer' => ""
                    ],
                    'transaction_amount' => [
                        'type' => "CHARGE", //CHARGE,REFUND
                        'psp_transaction_reference_id' => $paymentIntentId,
                        'amount'=> [ 
                            'currency' => "INR", 
                            'cent_amount' =>  $order_data->getGrandTotal() * self::FRACTION, 
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
            $dataArray['fulfilment_order']['custom_info'] = [
                [
                    'group' => "1",
                    'id' => "1",
                    'values' => [],
                    'additional_info' => ""
                ]
            ];
        
            $dataArray['fulfilment_order']['device_fingerprint'] = [
                'fp' => [
                    [
                        'fp_type' => "",
                        'fp_value' => "",
                    ],
                ],
                'fp_time' => ""
            ];
            $dataArray['fulfilment_order']['audit']['api_version'] = "";
            $dataArray['fulfilment_order']['audit']['created_at'] = "";
            $dataArray['fulfilment_order']['audit']['created_by'] = "";
            $dataArray['fulfilment_order']['audit']['last_modified_at'] = "";
            $dataArray['fulfilment_order']['audit']['last_modified_by'] = "";
        
        }

        return $dataArray;
    }

    private function getGroupName($groupId) {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCustomerAttributeValue($customer, $attributeCode) {
        $customerStatus = $customer->getCustomAttribute($attributeCode);
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

    public function getPaymentData($orderId) { 
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $data = $connection->query("SELECT * FROM $posOrderTable WHERE order_id=$orderId")->fetch();
        return $data;
    }

    public function getStateId($stateCode) {
        $states = array();
        $states['JK'] = "01";
        $states['HP'] = "02";
        $states['PB'] = "03";
        $states['CH'] = "04";
        $states['UK'] = "05";
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
        $states['DH'] = "26";
        $states['DNHDD'] = "26";
        $states['MH'] = "27";
        $states['AP'] = "28";
        $states['KA'] = "29";
        $states['GA'] = "30";
        $states['LD'] = "31";
        $states['KL'] = "32";
        $states['TN'] = "33";
        $states['PY'] = "34";
        $states['AN'] = "35";
        $states['TS'] = "36";
        $states['AD'] = "37";
        $states['LA'] = "38";

        if(isset($stateCode) && array_key_exists($stateCode, $states)) {
            return $states[$stateCode];
        } else {
            return;
        }
    }

    private function getCountryname($countryCode) {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }

    private function updateOrder($orderId) {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $data = $connection->query("UPDATE $orderTable set oms_status_flag=200 WHERE entity_id=$orderId");
        // $order = $this->orderData->load($orderId);
        // $orderState = 'complete';
        // $order->setState($orderState)->setStatus($orderState);
        // $order->save();
    }

    public function getUserData($userId) {
        $nodeId = "";
        $connection = $this->resource->getConnection();
        $userTable = $this->resource->getTableName('ah_supermax_pos_user');
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $userData = $connection->query("SELECT u.pos_user_id, o.inventory_node FROM $userTable as u LEFT JOIN $outletTable as o ON(u.pos_outlet_id = o.pos_outlet_id) WHERE u.pos_user_id = $userId")->fetch();
        if(!empty($userData)) {
            $nodeId = $userData['inventory_node'];
        }
        return $nodeId;
    }
}



