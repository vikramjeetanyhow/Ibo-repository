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

use Magento\Framework\DataObject;

class UpdateQuote extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\UpdateQuoteInterface
{    
    protected $coupon;
    protected $saleRule; 
    private $error;

    public function __construct( 
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $cartItem,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Customer\Model\Group $group,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\SalesRule\Model\Rule $quoteRules,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Embitel\SalesRule\Helper\Data $salesRuledata
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->cartItem = $cartItem;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->quoteFactory = $quoteFactory;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->ruleFactory = $ruleFactory;
        $this->group = $group;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
        $this->quoteRules = $quoteRules;
        $this->salesRuledata = $salesRuledata;
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
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $result['coupons'] = array();
                if(isset($params['quote_id']) && $params['quote_id']) {
                    $quoteId = $params['quote_id'];
                    $checkoutCall = isset($params['checkoutcall']) ? $params['checkoutcall'] : false;
                    $updateFetchQuoteStatus = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_update_fetch_quote_status", $storeId = null);
                    if($checkoutCall || ($updateFetchQuoteStatus != 'by_simple_sql')) {
                        $quoteAdditionalData = array();
                        $quote = $this->quoteFactory->create()->load($quoteId);
                        $postalCode = isset($params['order_postcode']) ? $params['order_postcode'] : "";
                        $quoteAdditionalData['customer_sez'] = isset($params['customer_sez']) ? $params['customer_sez'] : false;
                        $quote->setAdditionalData(json_encode($quoteAdditionalData))->save();
                        $lotInfo = isset($params['lot_detail']) ? $params['lot_detail'] : [];
                        $connection = $this->resource->getConnection();
                        $userId = $this->supermaxSession->getPosUserId();
                        $userData = $this->joinUserData($connection, $userId);
                        $user = $connection->query($userData)->fetch();
                        $storeId = !empty($user) ? $user['store_view_id'] : 0;
                        $outletStoreId = !empty($user) ? $user['outlet_store_id'] : "";
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
                        $quote->setChannelInfo($outletStoreId);
                        $quote->setBaseCurrencyCode($storeBaseCurrencyCode);
                        $quote->setQuoteCurrencyCode($storeCurrencyCode);
                        $quote->setStoreCurrencyCode($storeCurrencyCode);
                        $quote->setPostalCode($postalCode);
                        $quote->setLotInfo(json_encode($lotInfo));

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
                        $onInvoicePromoRuleId = $this->helper->getConfig("referrer_promotion/special_on_invoice_promotion/on_invoice_rule_id", $storeId = null);
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
                                        "type" => ($onInvoicePromoRuleId == $rule['rule_id']) ? "special-promotion" : "promotion"
                                    );
                                    // }
                                }
                                // $quote->setCouponCode('')->collectTotals()->save();
                                // }
                            }
                        }
                        
                        if(in_array($onInvoicePromoRuleId, $salesruleIds)) {
                            $quoteAdditionalData['on_invoice_promotion'] = true;
                            if(isset($params['override'])) {
                                $quoteAdditionalData['override']['on_invoice_promotion'] = $params['override'];
                            }
                        } else {
                            $quoteAdditionalData['on_invoice_promotion'] = false;
                        }
                        $quote->setAdditionalData(json_encode($quoteAdditionalData));
                        $quote->save();
                        $onInvoiceApplicable = $this->isOnInvoicePromoApplicable($groupId, $onInvoicePromoRuleId, $appliedPromos);
                        if($onInvoiceApplicable) {
                            $salesRuleTable = $this->resource->getTableName('salesrule');
                            $ruleDetails = $connection->query("SELECT * FROM $salesRuleTable WHERE rule_id = $onInvoicePromoRuleId")->fetch();
                            if(!empty($ruleDetails)) {
                                $appliedPromos[$ruleDetails['rule_id']] = array(
                                    "coupon_id" => $ruleDetails['rule_id'],
                                    "code" => null,
                                    "name" => $ruleDetails['name'],
                                    "description" => $ruleDetails['description'],
                                    "type" => 'P',
                                    "discount" => $ruleDetails['discount_amount'],
                                    'sortorder' => $ruleDetails['sort_order'],
                                    "applied" => false,
                                    "type" => "special-promotion"
                                );
                            }
                        }

                        if(!empty($applicableCoupons)) {
                            foreach ($applicableCoupons as $key => $coupon) {
                                $applicableCoupons[$key]['applied'] = ($coupon['code'] == $quote->getCouponCode()) ? true : false;
                            }
                        }

                        $result = ($updateFetchQuoteStatus == 'disable' || $checkoutCall ) ? $this->getQuoteData($quote) : array();
                        $result['applicable_coupons'] = $applicableCoupons;
                        $result['coupons'] = $appliedPromos;
                        $result['applied_coupon'] = ($quote->getProfessionalNumber()) ? $quote->getProfessionalNumber() : html_entity_decode($quote->getCouponCode());

                        // $result['quote_id'] = $quoteId;
                        // $result['reserved_order_id'] = $quote->getReservedOrderId();
                    } else {
                        if(isset($params['products']) && !empty($params['products'])) {
                            $postProductIds = $this->addProductToQuoteBySql($quoteId, $params['products']);
                        }

                        $result = array();
                        $result['applicable_coupons'] = array();
                        $result['coupons'] = array();
                        $result['applied_coupon'] = "";
                    }
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $this->helper->addDebuggingLogData("---- Update quote Debugger Catch Error : " . $e->getMessage());
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result, 'msgCode'=> $this->error);
        return json_encode($data);
    }

    public function joinUserData($connection, $userId) {
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'pos_outlet_id', 'store_view_id']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['email', 'source_code', 'outlet_store_id' => 'store_id']
        )->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    public function addProductToQuote($params, $quote, $storeCurrencyCode, $storeBaseCurrencyCode, $quoteProductIds = array()) {
        $postProductIds = array();
        foreach($params['products'] as $productData) { 
            $product = $this->productRepository->get($productData['offer_id']);
            if($product->getstatus() == 1) {
                $postProductIds[] = $productData['offer_id'];
                $quoteId = $quote->getId();
                $qty = $productData['quantity'];
                $originalPrice = (float)$productData['baseCost'];
                $baseOriginalPrice = (float)$this->helper->convert($originalPrice, $storeCurrencyCode, $storeBaseCurrencyCode);
                $additionalData = array(
                    "original_price" => $originalPrice,
                    'base_original_price' => $baseOriginalPrice,
                    'baseCost' => $productData['baseCost'],
                    'cartKey' => $productData['cartKey'],
                    'cost' => $productData['cost'],
                    'mrp' => $productData['mrp'],
                    'mrp_overridded_price' => $productData['mrp_overridded_price'],
                    'overridedPrice' => $productData['overridedPrice'],
                    'overrideprice' => $productData['overrideprice'],
                    'override_data' => $productData['override_data'],
                    'store_fulfilment_mode' => $productData['store_fulfilment_mode'],
                );

                if(isset($productData['overridded_price'])) {
                    $additionalData['overridded_price'] = $productData['overridded_price'];
                }
                if(isset($productData['added_discount'])) {
                    $additionalData['added_discount'] = $productData['added_discount'];
                }
                if(isset($productData['price'])) {
                    $additionalData['price'] = $productData['price'];
                }
                if(isset($productData['total_discount_amount'])) {
                    $additionalData['total_discount_amount'] = $productData['total_discount_amount'];
                }
                if(isset($productData['total_discount_percent'])) {
                    $additionalData['total_discount_percent'] = $productData['total_discount_percent'];
                }
                if(isset($productData['base_cost_formatted'])) {
                    $additionalData['base_cost_formatted'] = $productData['base_cost_formatted'];
                }
                if(isset($productData['updateQuantity'])) {
                    $additionalData['updateQuantity'] = $productData['updateQuantity'];
                }
                if(isset($productData['mrpOverriddedPrice'])) {
                    $additionalData['mrpOverriddedPrice'] = $productData['mrpOverriddedPrice'];
                }

                if(isset($productData['unit_cost_price']) && $productData['unit_cost_price']) {
                    $additionalData['unit_cost_price'] = $productData['unit_cost_price'];
                }

                if(isset($productData['vendor_code']) && $productData['vendor_code']) {
                    $additionalData['vendor_code'] = $productData['vendor_code'];
                }

                $quoteItem = array_key_exists($productData['offer_id'], $quoteProductIds) ? $quote->getItemById($quoteProductIds[$productData['offer_id']]) : $this->cartItem->create();

                $quoteItem->setProductId($product->getId())
                    ->setSku($product->getSku())
                    ->setProductType($product->getTypeId())
                    ->setName($product->getName())
                    ->setQuoteId($quoteId)
                    ->setQty($qty)
                    ->setAdditionalData(json_encode($additionalData))
                    ->setLotInfo(json_encode($productData['lot_detail']));
                
                if($productData['overrideprice']) {
                    $overridedPrice = $productData['overridded_price'];
                    $quoteItem->setPriceInclTax($overridedPrice);
                    $quoteItem->setCustomPrice($overridedPrice);
                    $quoteItem->setOriginalCustomPrice($overridedPrice);
                    // $quoteItem->getProduct()->setIsSuperMode(true);
                } elseif(isset($productData['mrp_overridded_price']) && $productData['mrp_overridded_price']) {
                    $overridedPrice = $productData['mrp_overridded_price'];
                    $quoteItem->setPriceInclTax($overridedPrice);
                    $quoteItem->setCustomPrice($overridedPrice);
                    $quoteItem->setOriginalCustomPrice($overridedPrice);
                    // $quoteItem->getProduct()->setIsSuperMode(true);
                } else {
                    $quoteItem->setPriceInclTax($originalPrice);
                    $quoteItem->setCustomPrice($originalPrice);
                    $quoteItem->setOriginalCustomPrice($originalPrice);
                    // $quoteItem->getProduct()->setIsSuperMode(true);
                }

                if(array_key_exists($productData['offer_id'], $quoteProductIds)) {
                    $quoteItem->save();
                } else {
                    $quote->addItem($quoteItem); 
                }
            } else {
                if(array_key_exists($productData['offer_id'], $quoteProductIds)) {
                    $quoteItem = $quote->getItemById($quoteProductIds[$productData['offer_id']]);
                    $quoteItem->delete();
                }
            }

            $quote->save();
        }

        return $postProductIds;
    }

    private function getQuoteData($quote) {
        $quoteId = $quote->getId();
        $data = array(
            "items_count" => $quote->getItemsCount(),
            "items_qty" => $quote->getItemsQty(),
            "grand_total" => $quote->getGrandTotal(),
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
                    "product_id" => (int)$item->getProductId(),
                    "offer_id" => (int)$item->getSku(),
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
            }
        } else {
            $data['items'] = array();
        }

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
            $posOutletStoreId = $quote->getChannelInfo();
            foreach ($getRules as $rule) {
                $ruleCouponUseIn = !empty($rule->getCouponUseIn()) ? explode(",", $rule->getCouponUseIn()): array();
                if($rule->getCode() && $rule->getIsActive() && $rule->getIsShowCoupon() && (in_array($posOutletStoreId, $ruleCouponUseIn) || in_array("all", $ruleCouponUseIn))) {
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
            $quote->setIsProfessionalReferralApplied(false);
            if($quote->getProfessionalNumber()) {
                $quote->setProfessionalNumber("");
                $this->error = "removed";
            }
            $quote->setCouponCode("")->collectTotals()->save(); 
        } else {
            if (!empty($this->salesRuledata->validateMobileNumber($code))) {
                $applySpecialPromo = $this->salesRuledata->applySpecialPromotion($code, $quote->getCustomerId(), $quote, "rest");
                if (isset($applySpecialPromo['applied_special_promo']) && $applySpecialPromo['applied_special_promo']) {
                    $quote->setProfessionalNumber($code);
                    $origCouponCode = isset($applySpecialPromo['origional_coupon']) ? $applySpecialPromo['origional_coupon'] : "";
                    $quote->setCouponCode($origCouponCode)->collectTotals()->save();
                    if($quote->getCouponCode() == $origCouponCode) {
                        $quote->setIsProfessionalReferralApplied(true);
                        $quote->save();
                    } else {
                        $quote->setProfessionalNumber("");
                        $quote->setIsProfessionalReferralApplied(false);
                    }
                } else {
                    $quote->setIsProfessionalReferralApplied(false);
                    if($this->salesRuledata->error != "") {
                        $this->error = $this->salesRuledata->error;
                    }
                }
            } else {
                $quote->setProfessionalNumber("");
                $quote->setIsProfessionalReferralApplied(false);
                if(($code != $this->salesRuledata->getOrginalCouponCode()) && ($code != $this->salesRuledata->getC2CCouponCode())) {
                    $quote->setCouponCode($code)->collectTotals()->save(); 
                }
            }
        }
        
        $quote->save();
    
        return $eligibleCoupons;
    }

    public function getCouponDetails() {
        $connection = $this->resource->getConnection();
        $salesRuleTable = $this->resource->getTableName('salesrule');
        $salesRuleCouponTable = $this->resource->getTableName('salesrule_coupon');

        $allCouponsData = $connection->query("SELECT sr.rule_id, sr.name, sr.description, sc.code FROM $salesRuleTable AS sr LEFT JOIN $salesRuleCouponTable AS sc ON(sr.rule_id = sc.rule_id) WHERE sc.code != '' AND is_active=1")->fetchAll();
        
        return $allCouponsData;
    }

    public function addProductToQuoteBySql($quoteId, $products) {
        if(!empty($products)) {
            $connection = $this->resource->getConnection();
            $quoteItemTable = $this->resource->getTableName('quote_item');
            $postProductIds = array();
            foreach ($products as $product) {
                $productData = $this->productRepository->get($product['offer_id']);
                $productId = $productData->getId();
                $productOfferId = $product['offer_id'];
                $postProductIds[] = $product['offer_id'];
                $qty = $product['quantity'];
                $quoteItemData = $connection->query("SELECT * FROM $quoteItemTable WHERE quote_id = $quoteId AND sku = '$productOfferId'")->fetch();
                if(!empty($quoteItemData)) {
                    $connection->query("UPDATE $quoteItemTable SET qty = $qty WHERE quote_id = $quoteId AND sku = '$productOfferId'");
                } else {
                    $connection->query("INSERT INTO $quoteItemTable SET quote_id = $quoteId, product_id = $productId, qty = $qty, sku = '$productOfferId'");
                }
            }
        }

        if(!empty($postProductIds)) {
            $connection->query("DELETE FROM $quoteItemTable WHERE quote_id = $quoteId AND sku NOT IN ( '" . implode( "', '" , $postProductIds ) . "' )");
        }
    }

    private function isOnInvoicePromoApplicable($customerGroupId, $onInvoicePromoRuleId, $appliedPromos) {
        $isOnInvoiceApplicable = false;
        $onInvoicePromoEnabled = (bool)$this->helper->getConfig("referrer_promotion/special_on_invoice_promotion/on_invoice_enabled", $storeId = null);
        if($onInvoicePromoEnabled) {
            if(!array_key_exists($onInvoicePromoRuleId, $appliedPromos)) {
                $applicableCustomerGroups = $this->helper->getConfig("referrer_promotion/special_on_invoice_promotion/on_invoice_customer_apply_group", $storeId = null);
                $applicableCustomerGroupIds = explode(',', $applicableCustomerGroups);
                if(in_array($customerGroupId, $applicableCustomerGroupIds)) {
                    $isOnInvoiceApplicable = true;
                }
            }
        }
        return $isOnInvoiceApplicable;
    }
}
