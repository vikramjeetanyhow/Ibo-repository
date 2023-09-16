<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CouponManagementInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\App\ResourceConnection;
use Embitel\SalesRule\Helper\Data as SalesRuleData;
use Magento\Store\Model\ScopeInterface;

/**
 * @inheritdoc
 */
class ApplyCouponToCart implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var CouponManagementInterface
     */
    private $couponManagement;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    private ScopeConfigInterface $scopeConfigInterface;

    /**
     * @param GetCartForUser $getCartForUser
     * @param CouponManagementInterface $couponManagement
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CouponManagementInterface $couponManagement,
        ResourceConnection $resourceConnection,
        SalesRuleData $salesRuledata,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->couponManagement = $couponManagement;
        $this->resourceConnection = $resourceConnection;
        $this->salesRuledata = $salesRuledata;
        $this->scopeConfigInterface  = $scopeConfigInterface;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['input']['cart_id'];

        if (empty($args['input']['coupon_code'])) {
            throw new GraphQlInputException(__('Required parameter "coupon_code" is missing'));
        }
        $couponCode = $args['input']['coupon_code'];

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        $cartId = $cart->getId();

        $storeScope = ScopeInterface::SCOPE_STORE;
        $isSpecialPromoEnable =  $this->scopeConfigInterface->getValue(
            'referrer_promotion/special_referrer_promotion/referral_promotion_enabled',
            $storeScope
        );

        $specialPromoCode = $this->salesRuledata->getOrginalCouponCode();

        if (($specialPromoCode != '') && (($couponCode == $this->salesRuledata->getOrginalCouponCode()) || ($couponCode == $this->salesRuledata->getC2CCouponCode()))) {
            $this->salesRuledata->addLog("The original special promo code can't be used");
            throw new GraphQlInputException(
                __('The coupon code isn\'t valid. Verify the code and try again.')
            );
        }
        $appliedSpecialPromo = '';

        if (!empty($this->salesRuledata->validateMobileNumber($couponCode))) {
            $applySpecialPromo = $this->salesRuledata->applySpecialPromotion($couponCode, $currentUserId, $cart);
            if (isset($applySpecialPromo['applied_special_promo']) && $applySpecialPromo['applied_special_promo']) {
                $cart->setProfessionalNumber($couponCode);
                $couponCode = isset($applySpecialPromo['origional_coupon']) ? $applySpecialPromo['origional_coupon'] : "";
                $appliedSpecialPromo = 1;
                $this->salesRuledata->addLog("Original Promo Id: ".$couponCode);
                $cart->save();
            } else {
                if($this->salesRuledata->error != "") {
                    throw new GraphQlInputException(
                        __($this->salesRuledata->error)
                    );
                }
            }
            $this->salesRuledata->addLog("=================== End for the special rule check conditions==================== ");
        } else {
            if (!empty($this->salesRuledata->validateMobileNumber($couponCode))) {
                $connection = $this->resourceConnection->getConnection();
                $salesRuleTable = $connection->getTableName('salesrule');
                $salesRuleCouponTable = $connection->getTableName('salesrule_coupon');
                $couponTypeId = \Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC;
                $query = "SELECT src.`code` FROM " . $salesRuleTable . " sr"
                        . " INNER JOIN " . $salesRuleCouponTable . " src ON src.rule_id = sr.rule_id"
                        . " WHERE `coupon_type` = '" . $couponTypeId . "' AND `is_active` = 1 AND `is_referrer_coupon` = 1"
                        . " AND `referrer_mobile_number` LIKE '%" . $couponCode . "%'";
                $cartRuleInfo = $connection->fetchAll($query);
                $couponCode = !empty($cartRuleInfo) ? $cartRuleInfo[0]['code'] : $couponCode;
            }
        }

        /* Check current cart does not have coupon code applied */
        $appliedCouponCode = $this->couponManagement->get($cartId);
        if (!empty($appliedCouponCode)) {
            throw new GraphQlInputException(
                __('A coupon is already applied to the cart. Please remove it to apply another')
            );
        }

        try {
            $this->couponManagement->set($cartId, $couponCode);
            if ($appliedSpecialPromo != '') {
                $cart->setIsProfessionalReferralApplied(true);
                $cart->save();
            }
        } catch (NoSuchEntityException $e) {
            $message = $e->getMessage();
            if (preg_match('/The "\d+" Cart doesn\'t contain products/', $message)) {
                $message = 'Cart does not contain products.';
            }
            throw new GraphQlNoSuchEntityException(__($message), $e);
        } catch (CouldNotSaveException $e) {
            throw new LocalizedException(__($e->getMessage()), $e);
        }

        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);
        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }

}
