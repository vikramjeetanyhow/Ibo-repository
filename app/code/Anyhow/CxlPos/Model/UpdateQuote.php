<?php

/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento Cxl Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/


namespace Anyhow\CxlPos\Model;

use Magento\Framework\DataObject;
class UpdateQuote extends DataObject implements \Anyhow\CxlPos\Api\UpdateQuoteInterface
{    
    protected $coupon;
    protected $saleRule; 
    private $error;

    public function __construct( 
        \Anyhow\CxlPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $cartItem,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Customer\Model\Group $group,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\SalesRule\Model\Rule $quoteRules,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->cartItem = $cartItem;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->quoteFactory = $quoteFactory;
        $this->resource = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->ruleFactory = $ruleFactory;
        $this->group = $group;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
        $this->quoteRules = $quoteRules;
        $this->error = "";
        $this->groupRepository = $groupRepository;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function updateQuote() {
        $result = array();
        $error = false;
        try {

                $params = $this->helper->getParams();
                if(isset($params['quote_id']) && $params['quote_id']) {
                        $quoteId = $params['quote_id'];
                        $quoteAdditionalData = array();
                        $quote = $this->quoteFactory->create()->load($quoteId);
                        $connection = $this->resource->getConnection();
                        $storeId = $this->storeManager->getStore()->getId();
                        $storeData = $this->storeManager->getStore($storeId);
                        $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                        $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();

                        $rules = $this->ruleFactory->create()->getCollection()
                            ->addFieldToSelect('*')
                            ->addFieldToFilter("is_active", array('eq'=> "1"));                     
                        $groupId = 0;
                        if(isset($params['customer_id']) && $params['customer_id']) {
                            $customer = $this->customerRepository->getById($params['customer_id']);
                            $customerEmail = filter_var($customer->getEmail(), FILTER_VALIDATE_EMAIL) ? $customer->getEmail() : $this->helper->getCustomerCustomEmail($customer->getId());
                            $customer->setEmail($customerEmail);
                            $quote->assignCustomer($customer);
                            $quote->setCustomerIsGuest(0);
                            $rules->addCustomerGroupFilter($customer->getGroupId()); 
                            $groupId = $customer->getGroupId();
                        } else {
                            $existingGroup = $this->group->load('NOT LOGGED IN', 'customer_group_code');
                            $rules->addCustomerGroupFilter($existingGroup->getCustomerGroupId()); 
                            $quote->setCustomerIsGuest(1);
                            $groupId = $existingGroup->getCustomerGroupId();
                        }
                        $remoteAddress = $this->remoteAddress->getRemoteAddress();
                        if ($remoteAddress !== false) {
                            $quote->setRemoteIp($remoteAddress);
                            $quote->setXForwardedFor(
                                $this->request->getServer('HTTP_X_FORWARDED_FOR')
                            );
                        }
                        $quote->setStoreId($storeId);
                        $quote->setBaseCurrencyCode($storeBaseCurrencyCode);
                        $quote->setQuoteCurrencyCode($storeCurrencyCode);
                        $quote->setStoreCurrencyCode($storeCurrencyCode);
                
                        if(isset($params['products']) && !empty($params['products'])) {
                            $quoteItems = $quote->getAllItems();
                            $quoteProductIds = array();

                            if(!empty($quoteItems)) {
                                foreach($quoteItems as $item) {
                                    $quoteProductIds[$item->getSku()] = $item->getItemId();
                                }
                            }

                            $postProductIds = $this->addProductToQuote($params, $quote, $storeCurrencyCode, $storeBaseCurrencyCode, $quoteProductIds);
            
                            // Delete Quote Items
                            if(!empty($quoteProductIds)) {
                                foreach($quoteProductIds as $key => $value) {
                                    if(!in_array($key, $postProductIds)) {
                                        $quoteItem = $quote->getItemById($quoteProductIds[$key]);
                                        $quoteItem->delete();
                                    }
                                }
                            }
                        }

                       $quote->save(); 
                        
                       $couponCode = '';
                       if(isset($params['couponcode']) && !empty($params['couponcode'])) {
                           // $quote->setCouponCode($params['couponcode']); 
                           $couponCode = $params['couponcode'];
                       }
                       
                       $removeCoupon = false;
                       if(isset($params['removecoupon'])) { 
                           $removeCoupon = $params['removecoupon'];
                       }

                       $applicableCoupons = $this->getCoupons($quote, $couponCode, $removeCoupon, $groupId);

                       $shippingCharges = 0;
                       $shippingTitle = "Flat Rate - Fixed";
                       if(isset($params['shipping_charges']) && !empty($params['shipping_charges'])) {
                           $grandTotal = $quote->getGrandTotal() + $params['shipping_charges'];
                           $baseGrandTotal = $quote->getBaseGrandTotal() + $params['shipping_charges'];
                            $quote->setExtShippingInfo(json_encode(array("shipping_charge" => $params['shipping_charges'])));
                            $quote->setGrandTotal($grandTotal);
                            $quote->setBaseGrandTotal($baseGrandTotal);
                            $quote->getShippingAddress()->setGrandTotal($grandTotal);
                            $quote->getShippingAddress()->setBaseGrandTotal($baseGrandTotal);
                            $quote->getShippingAddress()->setShippingAmount($params['shipping_charges']);
                            $quote->getShippingAddress()->setBaseShippingAmount($params['shipping_charges']);
                            $quote->getShippingAddress()->setShippingInclTax($params['shipping_charges']);
                            $quote->getShippingAddress()->setBaseShippingInclTax($params['shipping_charges']);
                            $shippingCharges = $params['shipping_charges'];
                            $shippingTitle = "HOME_DELIVERY";
                        }

                        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
                        $quote->getShippingAddress()->setShippingDescription($shippingTitle);
                        $quote->getShippingAddress()->setSameAsBilling(false);
                        $quote->getShippingAddress()->setCustomerId($quote->getCustomerId());
                        $quote->getShippingAddress()->setEmail($quote->getCustomerEmail());
                        $quote->getBillingAddress()->setCustomerId($quote->getCustomerId());
                        $quote->getBillingAddress()->setEmail($quote->getCustomerEmail());
                        
                        $shippingAddressId = $quote->getShippingAddress()->getEntityId();
                        $this->updateShippingRate($shippingAddressId, $shippingCharges, $shippingTitle);
                        $quote->save();
                        $appliedPromos = array();

                        $salesruleIds = explode(',', $quote->getAppliedRuleIds());
                        $allRules = $rules->getData();
                        if(!empty($allRules)) {
                            foreach($allRules as $rule) {
                                $couponCode = $rule['code'];
                                if(!empty($couponCode)) {
                                    if(in_array($rule['rule_id'], $salesruleIds)) {
                                        $quote->setCouponCode($couponCode)->save();
                                    }
                                }
                                if(in_array($rule['rule_id'], $salesruleIds)) {
                                    // if($quoteCouponCode) {
                                    $discountType = '';
                                    if($rule['simple_action'] == 'by_percent') {
                                        $discountType = 'P';
                                    } elseif($rule['simple_action'] == 'by_fixed') {
                                        $discountType = 'F';
                                    } elseif($rule['simple_action'] == 'cart_fixed') {
                                        $discountType = 'CF';
                                    }
                                    $appliedPromos[$rule['rule_id']] = array(
                                        "coupon_id" => $rule['rule_id'],
                                        "code" => $rule['code'],
                                        "name" => $rule['name'],
                                        "description" => $rule['description'],
                                        "type" => $discountType,
                                        "discount" => $rule['discount_amount'],
                                        'sortorder' => $rule['sort_order'],
                                        "applied" => true,
                                        "type" => "promotion"
                                    );
                                    // }
                                }
                                // $quote->setCouponCode('')->collectTotals()->save();
                                // }
                            }
                        }
                        if(!empty($applicableCoupons)) {
                            foreach ($applicableCoupons as $key => $coupon) {
                                $applicableCoupons[$key]['applied'] = ($coupon['code'] == $quote->getCouponCode()) ? true : false;
                            }
                        }
                       // $quote->setCouponCode('')->collectTotals();
                        $quote->save();
                        $result = $this->getQuoteData($quote);
                        $result['applicable_coupons'] = $applicableCoupons;
                        $result['applied_coupon'] =  html_entity_decode($quote->getCouponCode());
             
                } else {

                    $result = array();
                    $result['applicable_coupons'] = array();
                    $result['applied_coupon'] = "";
                }
        
        } catch (\Exception $e) {
            $this->helper->addDebuggingLogData("---- Update quote Debugger Catch Error : " . $e->getMessage());
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result, 'msgCode'=> $this->error);
        return json_encode($data);
    }


    public function addProductToQuote($params, $quote, $storeCurrencyCode, $storeBaseCurrencyCode, $quoteProductIds = array()) {
        $postProductIds = array();
        foreach($params['products'] as $productData) { 
            $product = $this->productRepository->get($productData['sku']);
            if($product->getstatus() == 1) {
                $postProductIds[] = $productData['sku'];
                $quoteId = $quote->getId();
                $qty = $productData['quantity'];
                $originalPrice = (float)$productData['baseCost'];
                $itemPrice = (float)$productData['baseCost'];
                $baseOriginalPrice = (float)$originalPrice;
                $additionalData = array(
                    "original_price" => $originalPrice,
                    'base_original_price' => $baseOriginalPrice,
                    'baseCost' => $productData['baseCost'],
                    'cost' => $productData['cost'],
                );

                if(isset($productData['added_discount'])) {
                    $additionalData['added_discount'] = $productData['added_discount'];
                }
                if(isset($productData['price'])) {
                    $additionalData['price'] = $productData['price'];
                }
      

                $quoteItem = array_key_exists($productData['sku'], $quoteProductIds) ? $quote->getItemById($quoteProductIds[$productData['sku']]) : $this->cartItem->create();

                $quoteItem->setProductId($product->getId())
                    ->setSku($product->getSku())
                    ->setProductType($product->getTypeId())
                    ->setName($product->getName())
                    ->setQuoteId($quoteId)
                    ->setQty($qty)
                    ->setAdditionalData(json_encode($additionalData));
                
                
                $quoteItem->setPrice($itemPrice);
                $quoteItem->setPriceInclTax($originalPrice);
                $quoteItem->setCustomPrice($originalPrice);
                $quoteItem->setOriginalCustomPrice($originalPrice);
                // $quoteItem->getProduct()->setIsSuperMode(true);
                

                if(array_key_exists($productData['sku'], $quoteProductIds)) {
                    $quoteItem->save();
                } else {
                    $quote->addItem($quoteItem); 
                }
            } else {
                if(array_key_exists($productData['sku'], $quoteProductIds)) {
                    $quoteItem = $quote->getItemById($quoteProductIds[$productData['sku']]);
                    $quoteItem->delete();
                }
            }

            $quote->save();
        }

        return $postProductIds;
    }

    private function getQuoteData($quote) {
        $quoteId = $quote->getId();
        $taxAmount = 0;
        $data = array(
            "items_count" => $quote->getItemsCount(),
            "items_qty" => $quote->getItemsQty(),
            "grand_total" => $quote->getGrandTotal(),
            "discount_total" => -1 * abs($quote->getGrandTotal() - $quote->getSubtotal()),
            "base_grand_total" => $quote->getBaseGrandTotal(),
            "applied_rule_ids" => $quote->getAppliedRuleIds(),
            "reserved_order_id" => $quote->getReservedOrderId(),
            "subtotal" => $quote->getSubtotal(),
            "base_subtotal" => $quote->getBaseSubtotal(),
            "subtotal_with_discount" => $quote->getSubtotalWithDiscount(),
            "base_subtotal_with_discount" => $quote->getbaseSubtotalWithDiscount(),
            "quote_id" => $quoteId,
        );
    
        $items = $quote->getAllItems();
        if(!empty($items)) {
            foreach ($items as $item) {
                $data['items'][] = array(
                    "item_id" => (int)$item->getItemId(),
                    "product_name" => (string)$item->getName(),
                    "product_id" => (int)$item->getProductId(),
                    "sku" => (string)$item->getSku(),
                    "applied_rule_ids" => $item->getAppliedRuleIds(),
                    "qty" => (int)$item->getQty(),
                    "price" => (float)$item->getPrice(),
                    "base_price" => (float)$item->getBasePrice(),
                    "discount_percent" => (float)$item->getDiscountPercent(),
                    "discount_amount" => (float)$item->getDiscountAmount(),
                    "base_discount_amount" => (float)$item->getBaseDiscountAmount(),
                    "tax_percent" => (float)$item->getTaxPercent(),
                    "tax_amount" => (float)$item->getTaxAmount(),
                    "base_tax_amount" => (float)$item->getBaseTaxAmount(),
                    "row_total" => (float)$item->getRowTotal(),
                    "base_row_total" => (float)$item->getBaseRowTotal(),
                    "row_total_with_discount" => (float)$item->getRowTotalWithDiscount(),
                    "base_tax_before_discount" => (float)$item->getBaseTaxBeforeDiscount(),
                    "tax_before_discount" => (float)$item->getTaxBeforeDiscount(),
                    "base_cost" => (float)$item->getBaseCost(),
                    "price_incl_tax" => (float)$item->getPriceInclTax(),
                    "base_price_incl_tax" => (float)$item->getBasePriceInclTax(),
                    "row_total_incl_tax" => (float)$item->getRowTotalInclTax(),
                    "base_row_total_incl_tax" => (float)$item->getBaseRowTotalInclTax()
                );
                $taxAmount += $item->getTaxAmount();
            }
        } else {
            $data['items'] = array();
        }

        $data['total_tax_amount'] = $taxAmount;

        return $data;
    }

    private function updateShippingRate($shippingAddressId, $shippingCharges, $shippingTitle) {
        $connection = $this->resource->getConnection();
        $quoteShippingRateTable = $this->resource->getTableName('quote_shipping_rate');
        $shippingRateData = $connection->query("SELECT * FROM $quoteShippingRateTable WHERE address_id = '" . (int)$shippingAddressId . "'")->fetch();
        if(!empty($shippingRateData)) {
            $connection->query("UPDATE $quoteShippingRateTable SET carrier = 'flatrate', carrier_title = '$shippingTitle', code = 'flatrate_flatrate', method = 'flatrate', price = '$shippingCharges', error_message = '', method_title = '$shippingTitle' WHERE address_id = '" . (int)$shippingAddressId . "'");
        }
    }

    private function getCoupons($quote, $code, $removeCoupon, $groupId = 0) {
        $eligibleCoupons = array();
        $getRules = $this->quoteRules->getCollection();
        if(!empty($getRules)) {
           
            foreach ($getRules as $rule) {
                $ruleCouponUseIn = !empty($rule->getCouponUseIn()) ? explode(",", $rule->getCouponUseIn()): array();
                if($rule->getCode() && $rule->getIsActive() && $rule->getIsShowCoupon()) {
                    $eligibleCoupons[] = array(
                        "rule_id" => $rule->getRuleId(),
                        "name" => $rule->getName(),
                        "description" => $rule->getDescription(),
                        "code" => $rule->getCode(),
                        "applied" => ($code == $quote->getCouponCode()) ? true : false
                    );
                }
            }
        }
        if($removeCoupon) {
            $quote->setCouponCode("")->collectTotals()->save(); 
        } else {
        
            $quote->setCouponCode($code)->collectTotals()->save(); 
        }
        
        $quote->save();
    
        return $eligibleCoupons;
    }

}