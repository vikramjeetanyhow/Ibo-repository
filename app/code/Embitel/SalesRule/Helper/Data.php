<?php

namespace Embitel\SalesRule\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Quote\Api\CouponManagementInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $scopeConfig;
    
    public $error = "";

    public $isReferralPromotion;

    public $isReferralPromotionApplied;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        groupRepository $groupRepository,
        \Magento\Eav\Model\Config $eavConfig,
        TotalsCollector $totalsCollector,
        RuleRepositoryInterface $ruleRepository,
        RuleFactory $ruleFactory,
        CouponManagementInterface $couponManagement,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->groupRepository = $groupRepository;
        $this->eavConfig = $eavConfig;
        $this->totalsCollector = $totalsCollector;
        $this->ruleRepository = $ruleRepository;
        $this->ruleFactory = $ruleFactory;
        $this->isReferralPromotion = false;
        $this->isReferralPromotionApplied = false;
        $this->couponManagement = $couponManagement;
        $this->orderFactory = $orderFactory;
    }

    // public function isValidB2PMobile($mobileNumber, $applyUserId)
    // {
    //     $collection = $this->customerCollectionFactory->create()
    //                ->addAttributeToFilter('mobilenumber', $mobileNumber)
    //                ->setPageSize(1);
    //     if ($collection->getSize() > 0) {
    //             $customer = $collection->getFirstItem();
    //             $customerData = $this->customerRepository->getById($customer->getId());
    //             $referralgroupId = $customer->getGroupId();
    //             $customerGroup = $this->getGroupName($referralgroupId);

    //             $approvalId = ($customerData->getCustomAttribute('approval_status') != '') ?
    //                                 $customerData->getCustomAttribute('approval_status')->getValue() : '';
    //         if ($approvalId != '') {
    //             $approvalStatus = $this->getCustomerAttributeValue(
    //                 'approval_status',
    //                 $customerData->getCustomAttribute('approval_status')->getValue()
    //             );
    //         } else {
    //             $approvalStatus = '';
    //         }

    //             $applyCustomerData = $this->customerRepository->getById($applyUserId);
    //             $applyUserGroupId = $applyCustomerData->getGroupId();
    //             $applyCustomerGroup = $this->getGroupName($applyUserGroupId);

    //             $referralAppliedCustomerGroups = $this->getReferralCustomerGroups();
    //             $promoApplyGroup = $this->getPromoApplyGroups();

    //             $this->addLog('Coupon mobile number : '.$mobileNumber);
    //             $this->addLog('Referral customer group : '.$customerGroup);
    //             $this->addLog('Referral Approval Status : '.$approvalStatus);
    //             $this->addLog('Coupon user customer group : '.$applyCustomerGroup);

    //         if (in_array($referralgroupId, $referralAppliedCustomerGroups) && $approvalStatus == 'approved'
    //                                                     && in_array($applyUserGroupId, $promoApplyGroup)) {
    //             $this->addLog("The user related condition for applying special Promo is satisfied");
    //             return true;
    //         } else {
    //             $this->addLog("The user related condition for applying special Promo is not satisfied");
    //             return false;
    //         }

    //     } else {
    //         $this->addLog("Mobile number is not satisfying the customer mobile collection");
    //         return false;
    //     }
    // }

    public function isSpecialB2PDiscountApply($quote, $hitType="graphQl") {
        $specialRuleDiscount = $this->getSpecialrulediscount($quote);
        $discountAppliedOnCart = $this->getDiscountOnCart($quote, $hitType);

        $this->addLog('Applied Coupons Discount : '.$discountAppliedOnCart);
        $this->addLog('Special Promo Discount : '.$specialRuleDiscount);

        if (($specialRuleDiscount > $discountAppliedOnCart) || (bccomp($specialRuleDiscount, $discountAppliedOnCart)==0)) {
            $this->isReferralPromotion = true;
            $this->addLog("The special Promo discount is greater than the applied discount");
            return true;
        } else {
            $this->addLog("The special Promo discount is less than the applied discount");
            return false;
        }
    }


    public function isSpecialDiscountApply($quote, $hitType="graphQl") {
        if($quote->getProfessionalNumber()) {
            $mobileNumber = $quote->getProfessionalNumber();
            $collection = $this->customerCollectionFactory->create()
                    ->addAttributeToFilter('mobilenumber', $mobileNumber)
                    ->setPageSize(1);
            if($collection->getSize() > 0) {
                $customer = $collection->getFirstItem();
                $customerData = $this->customerRepository->getById($customer->getId());
                $referralCustomergroupId = $customer->getGroupId();
                $b2pReferralCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_referrer_promotion/referral_customer_group");
                if(($this->getB2PSpecialPromoStatus() && in_array($referralCustomergroupId, $b2pReferralCustomerGroupIds))) {
                    $specialRuleDiscount = $this->getSpecialrulediscount($quote);
                    $discountAppliedOnCart = $this->getDiscountOnCart($quote, $hitType);
                    $this->addLog('Applied Coupons Discount : '.$discountAppliedOnCart);
                    $this->addLog('Special Promo Discount : '.$specialRuleDiscount);
                    if ($specialRuleDiscount >= $discountAppliedOnCart) {
                        $ruleId = $this->getConfig("referrer_promotion/special_referrer_promotion/referral_promotion_rule_id");
                        $couponCode = $this->getSpecialPromoCouponCode("referrer_promotion/special_referrer_promotion/referral_promotion_rule_id");
                        $this->setIboSpecialPromo($quote, $couponCode, $ruleId, "B2P");
                        $this->addLog("The special Promo discount is greater than the applied discount");
                    } else {
                        $this->addLog("The special Promo discount is less than the applied discount");
                    }
                } else {
                    $c2cReferralCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_c2c_referrer_promotion/referral_customer_group");
                    if(($this->getC2CSpecialPromoStatus() && in_array($referralCustomergroupId, $c2cReferralCustomerGroupIds))) {
                        $refferalCustomerFirstOrder = $this->getIsCustomerFirstOrder($customer->getId(), true);
                        $applyCustomerFirstOrder = $this->getIsCustomerFirstOrder($quote->getCustomerId());
                        if($this->getCartSubtotalInclTax($quote) >= $this->getConfig("referrer_promotion/special_c2c_referrer_promotion/min_cart_total") && !$refferalCustomerFirstOrder && $applyCustomerFirstOrder) {
                            $ruleId = $this->getConfig("referrer_promotion/special_c2c_referrer_promotion/referral_promotion_rule_id");
                            $couponCode = $this->getSpecialPromoCouponCode("referrer_promotion/special_c2c_referrer_promotion/referral_promotion_rule_id");
                            $this->setIboSpecialPromo($quote, $couponCode, $ruleId, "C2C");
                            $this->addLog("C2C referral conditions are satisfied");
                        } else {
                            $quote->setProfessionalNumber("");
                            $quote->setIsProfessionalReferralApplied(false);
                            $this->addLog("C2C referral conditions are not satisfied");
                        }  
                    }
                }
            }
        }
        $quote->save();
    }

    private function getCartSubtotalInclTax($quote) {
        $quote->setCartFixedRules([]);
        $cartTotals = $this->totalsCollector->collectQuoteTotals($quote);
        $subtotalInclTax = $cartTotals->getSubtotalInclTax();
        return $subtotalInclTax;
    }

    public function getSpecialrulediscount($quote) {
        $quote->setCartFixedRules([]);
        $cartTotals = $this->totalsCollector->collectQuoteTotals($quote);
        $subtotalInclTax = $cartTotals->getSubtotalInclTax();
        $specialPromoDiscountAmount = '';
        $ruleId = $this->getSpecialPromoRuleId();

        if ($ruleId != '') {
            $ruleData = $this->ruleRepository->getById($ruleId);
            if ($ruleData->getSimpleAction() == 'by_percent') {
                $discountPercent = $ruleData->getDiscountAmount();
                $specialPromoDiscountAmount = ($discountPercent / 100) * $subtotalInclTax;
            }
        }
        return $specialPromoDiscountAmount;
    }

    public function getDiscountOnCart($cart, $hitType="graphQl") {
        $cartItems = $cart->getItems();
        if($hitType == "rest") {
            $cartItems = $cart->getAllItems();
        }
        $totalCartRuleDiscount = 0;
        foreach ($cartItems as $item) {
            $totalCartRuleDiscount += $item->getDiscountAmount();
        }
        return $totalCartRuleDiscount;
    }

    public function getSpecialPromoRuleId() {
        $ruleid = $this->_scopeConfig->getValue(
            "referrer_promotion/special_referrer_promotion/referral_promotion_rule_id"
        );
        return $ruleid;
    }

    // private function getReferralCustomerGroups() {
    //     $referralCustomerGroups = $this->_scopeConfig->getValue(
    //         "referrer_promotion/special_referrer_promotion/referral_customer_group"
    //     );
    //     return explode(',', $referralCustomerGroups);
    // }

    // private function getPromoApplyGroups() {
    //     $referralCustomerGroups = $this->_scopeConfig->getValue(
    //         "referrer_promotion/special_referrer_promotion/customer_apply_group"
    //     );
    //     return explode(',', $referralCustomerGroups);
    // }

    private function getGroupName($groupId) {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCustomerAttributeValue($attributeCode, $value) {
        if (!empty($value)) {
            $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
            return $attribute->getSource()->getOptionText($value);
        } else {
            return '';
        }
    }

    public function getOrginalCouponCode() {
        $ruleId = $this->getSpecialPromoRuleId();
        if ($ruleId != '') {
            $couponCodeData = $this->ruleFactory->create()->load($ruleId);
            return $couponCodeData->getCouponCode();
        }
    }

    public function setIboSpecialPromo($cart, $couponCode, $ruleId, $type="B2P") {
        $this->addLog('Entered to set the special promo for order: ' . $cart->getReservedOrderId());
        $cartId = $cart->getId();
        // $couponCode = $this->getOrginalCouponCode();
        $this->couponManagement->set($cartId, $couponCode);
        $cart->setIsProfessionalReferralApplied(true);
        $cart->save();
        if($type != "C2C") {
            $cart->setAppliedRuleIds($ruleId);
            $cart->save();
        }
    }

    public function checkIboSpecialCoupon($cart) {
        $this->addLog('Check for the special promo enabled flag condition');
        $isSpecialPromoEnable = ($this->getB2PSpecialPromoStatus() || $this->getC2CSpecialPromoStatus()) ? true : false;
        if ($isSpecialPromoEnable && $this->isSpecialPromoAppliedOnCart($cart)) {
            //$this->couponManagement->remove($cart->getId());
            $cart->setCouponCode('');
            $cart->setIsProfessionalReferralApplied(false);
            $this->addLog('Speccial promo removed');
            $this->isReferralPromotionApplied = true;
        }
    }

    public function isSpecialPromoAppliedOnCart($cart) {
        return $cart->getIsProfessionalReferralApplied();
    }

    public function addLog($logdata) {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog(){
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = ($this->getB2PSpecialPromoStatus() || $this->getC2CSpecialPromoStatus()) ? true : false;
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ibo_special_promotion.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

    // New code
    public function validateMobileNumber($mobileNumber) {
        return preg_match('/^[5-9]\d{9}$/', $mobileNumber);
    }

    private function getConfig($path) {
        $configData = $this->_scopeConfig->getValue($path);
        return $configData;
    }

    private function getApplicableCustomerGroups($path) {
        return explode(',', $this->getConfig($path));
    }

    private function getSpecialPromoCouponCode($path) {
        $ruleId = $this->getConfig($path);
        if ($ruleId != '') {
            $couponCodeData = $this->ruleFactory->create()->load($ruleId);
            return $couponCodeData->getCouponCode();
        }
    }

    private function B2PReferralApplied($mobileNumber, $applyCustomerId, $cart, $customerData, $hitType="graphQl") {
        $result = array(
            "origional_coupon" => "",
            "applied_special_promo" => 0
        );
        $refferalCustomerApprovalId = ($customerData->getCustomAttribute('approval_status') != '') ? $customerData->getCustomAttribute('approval_status')->getValue() : '';
        $referralCustomerApprovalStatus = '';
        if($refferalCustomerApprovalId != '') {
            $customerAttribute = $this->eavConfig->getAttribute('customer', 'approval_status');
            $referralCustomerApprovalStatus = $customerAttribute->getSource()->getOptionText($refferalCustomerApprovalId);
        }

        $applyCustomerData = $this->customerRepository->getById($applyCustomerId);
        $applyCustomerGroupId = $applyCustomerData->getGroupId();
        $applyCustomerGroup = $this->getGroupName($applyCustomerGroupId);
        $allRefereeCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_referrer_promotion/customer_apply_group");

        $this->addLog('Referral Approval Status : ' . $referralCustomerApprovalStatus);
        $this->addLog('Apply customer group : ' . $applyCustomerGroup);

        if ($referralCustomerApprovalStatus == 'approved' && in_array($applyCustomerGroupId, $allRefereeCustomerGroupIds)) {
            $this->addLog("The user related condition for applying B2P special Promo is satisfied");
            $this->addLog("Special Promo conditions satisfied");
            $applySpecialPromo = $this->isSpecialB2PDiscountApply($cart, $hitType);
            $cart->setProfessionalNumber($mobileNumber);
            if ($applySpecialPromo) {
                $this->addLog("Customer Eligible for Special Promo");
                $result['origional_coupon'] = $this->getSpecialPromoCouponCode("referrer_promotion/special_referrer_promotion/referral_promotion_rule_id");
                $result['applied_special_promo'] = 1;
            } else {
                $this->error = "Congratulations! Partner code is applied and you are getting the maximum discount.";
                $this->addLog("Not Eligible. Special promo discount less then applied discount");
            }
            $cart->save();
        } else {
            $this->addLog("The user related condition for applying B2P special Promo is not satisfied");
        }

        return $result;
    }

    public function getC2CCouponCode() {
        return $this->getSpecialPromoCouponCode("referrer_promotion/special_c2c_referrer_promotion/referral_promotion_rule_id");
    }

    private function C2CReferralApplied($mobileNumber, $applyCustomerId, $cart, $referralCustomerId) {
        $result = array(
            "origional_coupon" => "",
            "applied_special_promo" => 0
        );
        
        $refferalCustomerFirstOrder = $this->getIsCustomerFirstOrder($referralCustomerId, true);
        $applyCustomerData = $this->customerRepository->getById($applyCustomerId);
        $applyCustomerGroupId = $applyCustomerData->getGroupId();
        $applyCustomerGroup = $this->getGroupName($applyCustomerGroupId);
        $applyCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_c2c_referrer_promotion/customer_apply_group", null);
        $applyCustomerFirstOrder = $this->getIsCustomerFirstOrder($applyCustomerId);
        $this->addLog('Referral previous success order status : ' . !$refferalCustomerFirstOrder);
        $this->addLog('Apply customer group : ' . $applyCustomerGroup);
        $this->addLog('Apply customer first-order status : ' . $applyCustomerFirstOrder);

        if (in_array($applyCustomerGroupId, $applyCustomerGroupIds) && !$refferalCustomerFirstOrder && $applyCustomerFirstOrder) {
            $this->addLog("The user related condition for applying C2C special Promo is satisfied");
            $minCartValue = $this->getConfig("referrer_promotion/special_c2c_referrer_promotion/min_cart_total");
            if($this->getCartSubtotalInclTax($cart) >= $minCartValue) {
                $this->addLog("Min. cart total condition is satisfied. Current cart total is: " . $this->getCartSubtotalInclTax($cart));
                $this->addLog("Min. cart total: " . $minCartValue);
                $result['origional_coupon'] = $this->getSpecialPromoCouponCode("referrer_promotion/special_c2c_referrer_promotion/referral_promotion_rule_id");
                $result['applied_special_promo'] = 1;
            } else {
                $cart->setProfessionalNumber("");
                $this->error = "Order value should be greater than Rs. ". $minCartValue . " for this coupon";
                $this->addLog("Not Eligible. Min. cart total condition is not satisfied. Current cart total is: " . $this->getCartSubtotalInclTax($cart));
            }
        } else {
            if($refferalCustomerFirstOrder) {
                $this->error = "Invalid Coupon Code. The code is not eligible for referral";
            }
            if(!$applyCustomerFirstOrder) {
                $this->error = "Coupon code is only applicable on first order";

            }
            
            $this->addLog("The user related condition for applying C2C special Promo is not satisfied");
        }
        $cart->save();
        return $result;
    }

    private function getIsCustomerFirstOrder($customerId, $isReferral = false) {
        $orderCollection = $this->orderFactory->create()->getCollection()
            ->addAttributeToSelect('customer_id')
            ->addFieldToFilter('customer_id', array('eq'=> $customerId));
            if($isReferral) {
                $orderCollection->addFieldToFilter('status', array('eq'=> 'processing'));
            }
        $order = $orderCollection->getFirstItem();
        $isCustomerFirstOrder = $order->getCustomerId() ? false : true;
        return $isCustomerFirstOrder;
    }

    private function getB2PSpecialPromoStatus() {
        return $this->getConfig("referrer_promotion/special_referrer_promotion/promo_log_enabled");
    }

    private function getC2CSpecialPromoStatus() {
        return $this->getConfig("referrer_promotion/special_c2c_referrer_promotion/promo_log_enabled");
    }


    public function applySpecialPromotion($mobileNumber, $applyCustomerId, $cart, $hitType="graphQl") {
        $this->error = "";
        $result = array(
            "origional_coupon" => "",
            "applied_special_promo" => 0
        );
        if($this->getB2PSpecialPromoStatus() || $this->getC2CSpecialPromoStatus()) {
            $this->addLog("=================== Entered the special rule check conditions for order: " . $cart->getReservedOrderId(). " ====================");
            $this->addLog('User Id: ' . $applyCustomerId);
            $this->addLog("Coupon code: " . $mobileNumber);
            $collection = $this->customerCollectionFactory->create()
                    ->addAttributeToFilter('mobilenumber', $mobileNumber)
                    ->setPageSize(1);
            if($collection->getSize() > 0) {
                $customer = $collection->getFirstItem();
                $customerData = $this->customerRepository->getById($customer->getId());
                $referralCustomergroupId = $customer->getGroupId();
                $referralCustomerGroup = $this->getGroupName($referralCustomergroupId);
                $b2pReferralCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_referrer_promotion/referral_customer_group");
                $this->addLog('Coupon mobile number : ' . $mobileNumber);
                $this->addLog('Referral customer group : ' . $referralCustomerGroup);
                if(($this->getB2PSpecialPromoStatus() && in_array($referralCustomergroupId, $b2pReferralCustomerGroupIds))) {
                    $this->addLog("Special B2P promotion Enabled and checking the valid customer conditions");
                    $result = $this->B2PReferralApplied($mobileNumber, $applyCustomerId, $cart, $customerData, $hitType);
                } else {
                    $c2cReferralCustomerGroupIds = $this->getApplicableCustomerGroups("referrer_promotion/special_c2c_referrer_promotion/referral_customer_group");
                    if(($this->getC2CSpecialPromoStatus() && in_array($referralCustomergroupId, $c2cReferralCustomerGroupIds))) {
                        $this->addLog("Special C2C promotion Enabled and checking the valid customer conditions");
                        $result = $this->C2CReferralApplied($mobileNumber, $applyCustomerId, $cart, $customer->getId());
                    } else {
                        $this->addLog("Referral customer group not satisfied the available customer groups conditions.");
                    }
                }
            } else {
                $this->error = "No customer is registered with the given mobile number.";
                $this->addLog("No customer is registered with the given mobile number.");
            }
        } else {
            $this->addLog("None of special referral promotion is enabled.");
        }
        
        return $result;
    }
}
