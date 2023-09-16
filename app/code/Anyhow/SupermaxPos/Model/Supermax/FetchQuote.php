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

class FetchQuote extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\FetchQuoteInterface
{    
    protected $coupon;
    protected $saleRule; 

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
        \Magento\SalesRule\Model\Rule $quoteRules
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
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function fetchQuote() {
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
                    $couponCode = $params['couponcode'];
                    $removeCoupon = $params['removecoupon'];
                    $quote = $this->quoteFactory->create()->load($quoteId);
                    $applicableCoupons = $this->getCoupons($quote, $couponCode, $removeCoupon);
                    if(!empty($applicableCoupons)) {
                        foreach ($applicableCoupons as $key => $coupon) {
                            $applicableCoupons[$key]['applied'] = ($coupon['code'] == $quote->getCouponCode()) ? true : false;
                        }
                    }

                    $result = $this->getQuoteData($quote);
                    $result['applicable_coupons'] = $applicableCoupons;
                    $result['coupons'] = $this->getAllAppliedPromotions($quote);
                    $result['applied_coupon'] = $quote->getCouponCode();
                    // $result['quote_id'] = $quoteId;
                    // $result['reserved_order_id'] = $quote->getReservedOrderId();
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
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

    public function getCoupons($quote, $code, $removeCoupon) {
        $eligibleCoupons = array();
        $getRules = $this->quoteRules->getCollection();
        if(!empty($getRules)) {
            foreach ($getRules as $rule) {
                if($rule->getCode() && $rule->getIsActive()) {
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

        $quote->setCouponCode($code)->collectTotals()->save(); 
        if($removeCoupon) {
            $quote->setCouponCode("")->collectTotals()->save(); 
        }
    
        return $eligibleCoupons;
    }

    public function getCouponDetails() {
        $connection = $this->resource->getConnection();
        $salesRuleTable = $this->resource->getTableName('salesrule');
        $salesRuleCouponTable = $this->resource->getTableName('salesrule_coupon');

        $allCouponsData = $connection->query("SELECT sr.rule_id, sr.name, sr.description, sc.code FROM $salesRuleTable AS sr LEFT JOIN $salesRuleCouponTable AS sc ON(sr.rule_id = sc.rule_id) WHERE sc.code != '' AND is_active=1")->fetchAll();
        
        return $allCouponsData;
    }

    public function getAllAppliedPromotions($quote) {
        $appliedPromos = array();
        $onInvoicePromoRuleId = $this->helper->getConfig("referrer_promotion/special_on_invoice_promotion/on_invoice_rule_id", null);
        $groupId = $quote->getCustomerGroupId();
        $salesruleIds = $quote->getAppliedRuleIds();
        $connection = $this->resource->getConnection();
        $salesRuleCouponTable = $this->resource->getTableName('salesrule_coupon');
        $salesRuleTable = $this->resource->getTableName('salesrule');
        if(!empty($salesruleIds)) {
            $allpromotionsData = $connection->query("SELECT sr.rule_id AS coupon_id, sr.name, sr.description, sr.simple_action AS type, sr.discount_amount AS discount, sr.sort_order AS sortorder, sc.code FROM $salesRuleTable AS sr LEFT JOIN $salesRuleCouponTable AS sc ON(sr.rule_id = sc.rule_id) WHERE sr.rule_id IN ($salesruleIds)")->fetchAll();
            if(!empty($allpromotionsData)) {
                foreach ($allpromotionsData as $key => $rule) {
                    $appliedPromos[$rule['coupon_id']] = array(
                        "coupon_id" => $rule['coupon_id'],
                        "code" => $rule['code'],
                        "name" => $rule['name'],
                        "description" => $rule['description'],
                        "type" => $rule['type'],
                        "discount" => $rule['discount'],
                        'sortorder' => $rule['sortorder'],
                        "applied" => true,
                        "type" => ($onInvoicePromoRuleId == $rule['coupon_id']) ? "special-promotion" : "promotion"
                    );
                }
            }
        }
        $onInvoiceApplicable = $this->isOnInvoicePromoApplicable($groupId, $onInvoicePromoRuleId, $appliedPromos);
        if($onInvoiceApplicable) {
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
        return $appliedPromos;
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
