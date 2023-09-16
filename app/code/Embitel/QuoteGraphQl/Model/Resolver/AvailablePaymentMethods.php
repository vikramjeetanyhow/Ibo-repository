<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\Data\CartInterface;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;

/**
 * Get list of active payment methods resolver.
 */
class AvailablePaymentMethods implements ResolverInterface
{
    /**
     * @var PaymentInformationManagementInterface
     */
    private $informationManagement;

    /**
     * @var GetCustomer
     */
    private $getCustomer;

    /**
     * @param PaymentInformationManagementInterface $informationManagement
     */
    public function __construct(
        PaymentInformationManagementInterface $informationManagement,
        GetCustomer $getCustomer,
        \Magento\Eav\Model\Config $eavConfig
    ) {
        $this->informationManagement = $informationManagement;
        $this->getCustomer = $getCustomer;
        $this->eavConfig = $eavConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $cart = $value['model'];
        return $this->getPaymentMethodsData($cart, $context);
    }

    /**
     * Collect and return information about available payment methods
     *
     * @param CartInterface $cart
     * @return array
     */
    private function getPaymentMethodsData(CartInterface $cart, $context): array
    {
        $paymentInformation = $this->informationManagement->getPaymentInformation($cart->getId());
        $paymentMethods = $paymentInformation->getPaymentMethods();

        $paymentMethodsData = [];
        foreach ($paymentMethods as $paymentMethod) {
            if($paymentMethod->getCode() == 'offlinepayment'){
                $customer = $this->getCustomer->execute($context);
                $enableOfflineObject = $customer->getCustomAttribute('enable_offline_payment');
                if(empty($enableOfflineObject) || (!empty($enableOfflineObject) && empty($enableOfflineObject->getValue()))) continue;
            }
            if($paymentMethod->getCode() == 'BHARATPE'){
                $customer = $this->getCustomer->execute($context);
                $enablePayLaterObject = $customer->getCustomAttribute('enable_paylater_payment');
                $limitPaylaterObject = $customer->getCustomAttribute('pay_later_limit');

                $loanPartnerObject = $customer->getCustomAttribute('loan_partner');
                $attribute = $this->eavConfig->getAttribute('customer', 'loan_partner');
                $loanPartnerName = '';
                if(!empty($loanPartnerObject)) {
                    $loanPartnerName = $attribute->getSource()->getOptionText($loanPartnerObject->getValue());
                }

                if(empty($enablePayLaterObject) || (!empty($loanPartnerName) && $loanPartnerName != 'BHARATPE') || $loanPartnerName=='' || (!empty($enablePayLaterObject) && empty($enablePayLaterObject->getValue())))
                    continue;
            }

            if($paymentMethod->getCode() == 'SARALOAN'){
                $customer = $this->getCustomer->execute($context);
                $enablePayLaterObject = $customer->getCustomAttribute('enable_paylater_payment');
                $limitPaylaterObject = $customer->getCustomAttribute('pay_later_limit');

                $loanPartnerObject = $customer->getCustomAttribute('loan_partner');
                $attribute = $this->eavConfig->getAttribute('customer', 'loan_partner');
                $loanPartnerName = '';
                if(!empty($loanPartnerObject)) {
                    $loanPartnerName = $attribute->getSource()->getOptionText($loanPartnerObject->getValue());
                }

                if(empty($enablePayLaterObject) || (!empty($loanPartnerName) && $loanPartnerName != 'SARALOAN') || $loanPartnerName==''|| (!empty($enablePayLaterObject) && empty($enablePayLaterObject->getValue())))
                    continue;
            }
            $paymentMethodsData[] = [
                'title' => $paymentMethod->getTitle(),
                'code' => $paymentMethod->getCode(),
            ];
        }
        return $paymentMethodsData;
    }
}
