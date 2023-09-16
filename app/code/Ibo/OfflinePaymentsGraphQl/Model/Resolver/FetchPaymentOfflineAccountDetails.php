<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ibo\OfflinePaymentsGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * Customers Payment Tokens resolver, used for GraphQL request processing.
 */
class FetchPaymentOfflineAccountDetails implements ResolverInterface
{

    /**
     * @var \Magento\Payment\Model\Config
     */
    private $paymentConfig;

    /**
     * @var CustomerRepositoryInterface
     */
    private $_customerRepository;

    /**
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        \Magento\Payment\Model\Config $paymentConfig,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->paymentConfig = $paymentConfig;
        $this->_customerRepository = $customerRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }
        $paymentMethods = $this->paymentConfig->getActiveMethods();
        $getOfflinePayment = !empty($paymentMethods['offlinepayment']) ? $paymentMethods['offlinepayment'] : [];
        $result = [
            'enable_offline_payment' => false,
            'virtual_customer_account' => "",
            'virtual_account_ifsc' => "",
            'virtual_account_bank' => ""
        ];
        if(!empty($getOfflinePayment)){
            //get customer bank details
            $customer = $this->_customerRepository->getById($context->getUserId());
            $customerIfsc = $customer->getCustomAttribute('virtual_account_ifsc');
            $customerBank = $customer->getCustomAttribute('virtual_account_bank');
            $virtualAc = $customer->getCustomAttribute('virtual_customer_account');
            $offlinePayment = $customer->getCustomAttribute('enable_offline_payment');
            
            //get offline payment bank details
            $paymentIfscCode = !empty($customerIfsc) ? $customerIfsc->getValue() : $getOfflinePayment->getConfigData('virtual_account_ifsc');
            $paymentBankName = !empty($customerBank) ? $customerBank->getValue() : $getOfflinePayment->getConfigData('virtual_account_bank');
            $paymentBNPrefix = $getOfflinePayment->getConfigData('virtual_customer_account_prefix');
            $virtualCustomerAccount = empty($virtualAc) ? $paymentBNPrefix . $customer->getId() : $virtualAc->getValue();

            $result = [
                'enable_offline_payment' => !empty($offlinePayment) ? $offlinePayment->getValue() : false,
                'virtual_customer_account' => $virtualCustomerAccount,
                'virtual_account_ifsc' => $paymentIfscCode,
                'virtual_account_bank' => $paymentBankName
            ];

            //set if bank details not found for customer
            $isCustomerSave = false;
            if(empty($customerIfsc) && !empty($paymentIfscCode)){
                $customer->setCustomAttribute('virtual_account_ifsc', $paymentIfscCode);
                $isCustomerSave = true;
            }
            if(empty($customerBank) && !empty($paymentBankName)){
                $customer->setCustomAttribute('virtual_account_bank', $paymentBankName);
                $isCustomerSave = true;
            }
            if(empty($virtualAc) && !empty($virtualCustomerAccount)){
                $customer->setCustomAttribute('virtual_customer_account', $virtualCustomerAccount);
                $isCustomerSave = true;
            }

            if(!empty($isCustomerSave)){
                $this->_customerRepository->save($customer);
            }
        }
        return $result;
    }
}
