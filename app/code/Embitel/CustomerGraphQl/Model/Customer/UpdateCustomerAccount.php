<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\CustomerGraphQl\Model\Customer\SaveCustomer;
use Magento\CustomerGraphQl\Model\Customer\CheckCustomerPassword;
use Magento\CustomerGraphQl\Model\Customer\ValidateCustomerData;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\Notification\Model\SendSms;
use Embitel\Customer\Model\Wallet;

/**
 * Update customer account data
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - https://jira.corp.magento.com/browse/MC-18152
 */
class UpdateCustomerAccount extends \Magento\CustomerGraphQl\Model\Customer\UpdateCustomerAccount
{
    /**
     * @var SaveCustomer
     */
    private $saveCustomer;

    /**
     * @var CheckCustomerPassword
     */
    private $checkCustomerPassword;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var ValidateCustomerData
     */
    private $validateCustomerData;

    /**
     * @var array
     */
    private $restrictedKeys;

    /**
     * @var SubscriptionManagerInterface
     */
    private $subscriptionManager;

    /**
     * @var SendSms
     */
    private $sendSms;

    /**
     * @param SaveCustomer $saveCustomer
     * @param CheckCustomerPassword $checkCustomerPassword
     * @param DataObjectHelper $dataObjectHelper
     * @param ValidateCustomerData $validateCustomerData
     * @param SubscriptionManagerInterface $subscriptionManager
     * @param array $restrictedKeys
     * @param SendSms $sendSms
     */
    public function __construct(
        SaveCustomer $saveCustomer,
        CheckCustomerPassword $checkCustomerPassword,
        DataObjectHelper $dataObjectHelper,
        ValidateCustomerData $validateCustomerData,
        SubscriptionManagerInterface $subscriptionManager,
        \Magento\Customer\Model\Group $group,
        CustomerRepositoryInterface $customerRepository,
        ExtractCustomerAttribute $extractCustomerAttribute,
        array $restrictedKeys = [],
        SendSms $sendSms,
        Wallet $wallet
    ) {
        $this->saveCustomer = $saveCustomer;
        $this->checkCustomerPassword = $checkCustomerPassword;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->restrictedKeys = $restrictedKeys;
        $this->validateCustomerData = $validateCustomerData;
        $this->subscriptionManager = $subscriptionManager;
        $this->group = $group;
        $this->customerRepository = $customerRepository;
        $this->extractCustomerAttribute = $extractCustomerAttribute;
        $this->sendSms = $sendSms;
        $this->walletModel = $wallet;
    }

    /**
     * Update customer account
     *
     * @param CustomerInterface $customer
     * @param array $data
     * @param StoreInterface $store
     * @return void
     * @throws GraphQlAlreadyExistsException
     * @throws GraphQlAuthenticationException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function execute(CustomerInterface $customer, array $data, StoreInterface $store): void
    {
        
        if (isset($data['email']) && $customer->getEmail() !== $data['email']) {
            if (!isset($data['password']) || empty($data['password'])) {
                throw new GraphQlInputException(__('Provide the current "password" to change "email".'));
            }

            $this->checkCustomerPassword->execute($data['password'], (int)$customer->getId());
            $customer->setEmail($data['email']);
        }

        $this->validateCustomerData->execute($data);
        $filteredData = array_diff_key($data, array_flip($this->restrictedKeys));
        $this->dataObjectHelper->populateWithArray($customer, $filteredData, CustomerInterface::class);

        try {
            $customer->setStoreId($store->getId());
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
        }

        $this->saveCustomer->execute($customer);

        $this->addLog('The update customer profile request:');
        $this->addLog(json_encode($data));

        // Code to update customer profile informations
        $customerDetails = $this->customerRepository->getById($customer->getId());
        if (isset($data['customer_secondary_email'])) {
            $customerDetails->setCustomAttribute('customer_secondary_email',$data['customer_secondary_email']);
        }

        if (isset($data['my_profile']) && isset($data['customer_type'])) {
            $customertype_attribute = ['label' => $data['customer_type'], 'key' => 'customer_type'];
            $customertypeOptionValue = $this->extractCustomerAttribute->get($customertype_attribute);
            $customerDetails->setCustomAttribute('customer_type',$customertypeOptionValue);
            if(!empty($data['customer_type'])){
                $mobileNumber = $customerDetails->getCustomAttribute('mobilenumber')->getValue();
                $this->sendSms->sendUnapprovedSms($data['customer_type'], $mobileNumber);
            }
        }

        if(isset($data['my_profile']) && ($data['my_profile'] == 0)) {
            $cusGrpCode = 'B2C';
            $existingGroup = $this->group->load($cusGrpCode, 'customer_group_code');
            $groupId = $existingGroup->getData('customer_group_id');
            $customerDetails->setGroupId($groupId);
        }

        try {
            $this->customerRepository->save($customerDetails);

            //Only customer update from bisom, call wallet.
            if(empty($data['customer_secondary_email'])) {
                $walletData = [
                    'customer_id' => $customerDetails->getId(),
                    'customer_group_id' => $customerDetails->getGroupId(),
                    'mobilenumber' => $customerDetails->getCustomAttribute("mobilenumber")->getValue()
                        ?? '',
                    'approval_status' => $customerDetails->getCustomAttribute('approval_status')->getValue() ?? ''
                ];
                $this->walletModel->createWallet($walletData);
            }
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
        }

        if (isset($data['is_subscribed'])) {
            if ((bool)$data['is_subscribed']) {
                $this->subscriptionManager->subscribeCustomer((int)$customer->getId(), (int)$store->getId());
            } else {
                $this->subscriptionManager->unsubscribeCustomer((int)$customer->getId(), (int)$store->getId());
            }
        }
    }

    public function addLog($logData, $filename = "update-customer-profile.log")
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
}
