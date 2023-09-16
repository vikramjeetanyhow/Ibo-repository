<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Embitel\Customer\Model\Wallet;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Newsletter\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Magento\CustomerGraphQl\Model\Customer\UpdateCustomerAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\CustomerGraphQl\Model\Customer\Address\CreateCustomerAddress as CreateCustomerAddressModel;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\CustomerGraphQl\Model\Customer\Address\GetCustomerAddress;
use Magento\CustomerGraphQl\Model\Customer\Address\UpdateCustomerAddress as UpdateCustomerAddressModel;
use Embitel\Oodo\Helper\OodoPush;
use Embitel\CustomerGraphQl\Model\Customer\Address\PostCode;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;
use Embitel\Notification\Model\SendSms;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Update customer account resolver
 */
class UpdateEboCustomer implements ResolverInterface
{
    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;
    /**
     * @var CreateCustomerAccount
     */
    private $createCustomerAccount;
    /**
     * @var Config
     */
    private $newsLetterConfig;

    /**
     * @var ValidateMobile
     */
    protected $validateMobile;

    /**
     * @var CreateCustomerAddressModel
     */
    private $createCustomerAddress;

    /**
     * @var UpdateCustomerAccount
     */
    private $updateCustomerAccount;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerGroupCollection
     */
    private $customerGroupCollection;

    /**
     * @var OodoPush
     */
    private $oodoPush;

    private $date;

    private $file;

    private $dir;

    /**
     * @var PostCode
     */
    private $postCode;

    /**
     * @var ExtractCustomerAttribute
     */
    private $extractCustomerAttribute;

    /**
     * @var GetCustomerAddress
     */
    private $getCustomerAddress;

    /**
     * @var UpdateCustomerAddressModel
     */
    private $updateCustomerAddress;

    private $attributeCollection;

    /**
     * @var SendSms
     */
    private $sendSms;
    private $customer = null;
    private Wallet $wallet;
    private CustomerFactory $customerFactory;

    /**
     * CreateCustomer constructor.
     *
     * @param ExtractCustomerData $extractCustomerData
     * @param CreateCustomerAccount $createCustomerAccount
     * @param Config $newsLetterConfig
     * @param ValidateMobile $validateMobile
     * @param CreateCustomerAddressModel $createCustomerAddress
     * @param UpdateCustomerAccount $updateCustomerAccount
     * @param CustomerRepositoryInterface $customerRepository
     * @param File $file
     * @param DirectoryList $dir
     * @param TimezoneInterface $date
     * @param ExtractCustomerAttribute $extractCustomerAttribute
     * @param CustomerGroupCollection $customerGroupCollection
     * @param GetCustomerAddress $getCustomerAddress
     * @param UpdateCustomerAddressModel $updateCustomerAddress
     * @param OodoPush $oodoPush
     * @param PostCode $postCode
     * @param AttributeCollection $attributeCollection
     * @param CustomerMetadataInterface $metadataService
     * @param SendSms $sendSms
     * @param Wallet $wallet
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        ExtractCustomerData $extractCustomerData,
        CreateCustomerAccount $createCustomerAccount,
        Config $newsLetterConfig,
        ValidateMobile $validateMobile,
        CreateCustomerAddressModel $createCustomerAddress,
        UpdateCustomerAccount $updateCustomerAccount,
        CustomerRepositoryInterface $customerRepository,
        File $file,
        DirectoryList $dir,
        TimezoneInterface $date,
        ExtractCustomerAttribute $extractCustomerAttribute,
        CustomerGroupCollection $customerGroupCollection,
        GetCustomerAddress $getCustomerAddress,
        UpdateCustomerAddressModel $updateCustomerAddress,
        OodoPush $oodoPush,
        PostCode $postCode,
        AttributeCollection $attributeCollection,
        CustomerMetadataInterface $metadataService,
        SendSms $sendSms,
        Wallet $wallet,
        CustomerFactory $customerFactory
    ) {
        $this->newsLetterConfig = $newsLetterConfig;
        $this->extractCustomerData = $extractCustomerData;
        $this->createCustomerAccount = $createCustomerAccount;
        $this->validateMobile = $validateMobile;
        $this->createCustomerAddress = $createCustomerAddress;
        $this->updateCustomerAccount = $updateCustomerAccount;
        $this->customerRepository = $customerRepository;
        $this->extractCustomerAttribute = $extractCustomerAttribute;
        $this->customerGroupCollection = $customerGroupCollection;
        $this->getCustomerAddress = $getCustomerAddress;
        $this->updateCustomerAddress = $updateCustomerAddress;
        $this->oodoPush = $oodoPush;
        $this->date = $date;
        $this->file = $file;
        $this->postCode = $postCode;
        $this->attributeCollection = $attributeCollection;
        $this->metadataService = $metadataService;
        $this->sendSms = $sendSms;
        $this->wallet = $wallet;
        $this->customerFactory = $customerFactory;
        $this->dir = $dir;
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
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }
        $errorFields = [];

        $this->addLog('<---------->');
        $this->addLog('Update Payload - ' . json_encode($args['input']));

        if (!isset($args['input']['mobilenumber'])) {
            $errorFields[] = 'mobilenumber';
        }
        if (!empty($errorFields)) {
            throw new GraphQlInputException(__(implode('/', $errorFields) . ' is mandatory'));
        }
        if (empty($args['input']['customer_id'])) {
            throw new GraphQlInputException(__('Customer Id is a mandatory field'));
        }
        if (isset($args['input']['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['input']['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }
        $mobileNumber = $args['input']['mobilenumber'];

        if (empty($args['input']['email'])) {
            unset($args['input']['email']);
        }

        if (empty($args['input']['customer_type'])) {
            unset($args['input']['customer_type']);
        }

        if (empty($args['input']['approval_status'])) {
            unset($args['input']['approval_status']);
        }

        if (!empty($args['input']['customer_type'])) {
            $customerCustomType = $args['input']['customer_type'];
        }

        if (isset($args['input']['business_activities'])) {
            $baValue = [];
            foreach ($args['input']['business_activities'] as $businessValue) {
                $baAttribute = ['label' => $businessValue, 'key' => 'business_activities'];
                $baValue[] = !empty($this->extractCustomerAttribute->get($baAttribute)) ? current($this->extractCustomerAttribute->get($baAttribute)) : '';
            }
            $args['input']['business_activities'] = implode(',', $baValue);
        }

        //Get Zip code information from csv sheet
        $postalInfo = [];
        if (!empty($args['input']['billing_address']['postcode']) && !empty($args['input']['shipping_address']['postcode']) && $args['input']['billing_address']['postcode'] == $args['input']['shipping_address']['postcode']) {
            $postalInfo['billing'] = $postalInfo['shipping'] = $this->postCode->getCsvData($args['input']['billing_address']);
        }

        if (empty($postalInfo) && (!empty($args['input']['billing_address']['postcode']) || !empty($args['input']['shipping_address']['postcode']))) {
            $postalInfo['billing'] = !empty($args['input']['billing_address']) ? $this->postCode->getCsvData($args['input']['billing_address']) : [];
            $postalInfo['shipping'] = !empty($args['input']['shipping_address']) ? $this->postCode->getCsvData($args['input']['shipping_address']) : [];
        }

        if (!$this->newsLetterConfig->isActive(ScopeInterface::SCOPE_STORE)) {
            $args['input']['is_subscribed'] = false;
        }
        if (isset($args['input']['date_of_birth'])) {
            $args['input']['dob'] = $args['input']['date_of_birth'];
        }

        $customerDomainId = $args['input']['customer_id'];

        $customerId = $this->validateMobile->isEboMobileExist($args['input']['mobilenumber']);
        $isCustomerExistsWithDomainId = $this->validateMobile->isCustomerExists($customerDomainId);


        if ($customerId === $customerDomainId) {
            $this->updateCustomer($customerDomainId, $mobileNumber, $context, $args, $postalInfo);
        } elseif (!$customerId && !$isCustomerExistsWithDomainId) {
            $this->addLog('New Mobile Number: '. $mobileNumber .' Creating the new customer using customer_domain_id: ' . $customerDomainId);
            $customerData = $this->createCustomer($customerDomainId, $mobileNumber, $context, $args);
            $newCustomerId = $customerData['customer']['customer_increment_id'];
            $this->addLog('Created the new customer using customer_domain_id: ' . $newCustomerId);

            if ($newCustomerId != $customerDomainId) {
                throw new GraphQlInputException(__('Unable to create customer with given customer_doman_id: ' . $customerDomainId . ', Insted created new customer id: ' . $newCustomerId));
            }

            $this->addLog('Updating the new customer created using customer_domain_id: ' . $customerDomainId);
            $this->updateCustomer($newCustomerId, $mobileNumber, $context, $args, $postalInfo);
        } elseif ($customerId !== $customerDomainId && $isCustomerExistsWithDomainId) {
            $this->addLog('Magento customer domain id : ' . $customerDomainId . ' already exists. You are trying to re-create or update the customer with new mobile number ' . $args['input']['mobilenumber']);

            throw new GraphQlInputException(__('Magento customer id and customer_domain_id is not matched to update'));
        } else {
            $this->addLog('Magento customer id: ' . $customerId . ' and customer_domain_id: ' . $customerDomainId . ' is not matched to update with mobile number: ' . $args['input']['mobilenumber']);

            throw new GraphQlInputException(__('Magento customer id and customer_domain_id is not matched to update'));
        }

        $data = $this->extractCustomerData->execute($this->customer);
        if (!empty($customerId)) {
            $data['customer_increment_id'] = $customerId;
        }
        return ['customer' => $data];
    }

    public function addLog($logData)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        $logEnable = 1;
        if ($logEnable) {
            $filename = BP . '/var/log/ebo-customer-creation.log';
            $writer = new Stream($filename);
            $logger = new Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }

    /**
     * @throws GraphQlAuthorizationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws GraphQlAuthenticationException
     * @throws GraphQlInputException
     * @throws GraphQlAlreadyExistsException
     * @throws GraphQlNoSuchEntityException
     */
    private function updateCustomer($customerId, $mobileNumber, $context, $args, $postalInfo): array
    {
        if (!empty($args['input']['customer_type'])) {
            // if ($args['input']['customer_type'] == 'B2C') {
            //     $args['input']['customer_type'] = "Individual";
            // }

            if (empty($args['input']['customer_type'])) {
                $customerCustomType = $args['input']['customer_type'] = 'B2C';
            }

            $attribute = ['label' => $args['input']['customer_type'], 'key' => 'customer_type'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Customer Type is not valid'));
            }
            $customerTypeArray = explode(',', $args['input']['customer_type']);
            $args['input']['customer_type'] = $customerOptionValue;
            $customerGroup = $this->customerGroupCollection->toOptionArray();

            $customerGroupName = trim($args['input']['customer_group']);

            /* changed from B2C to B2P based on BAP-725 */
            $customerGroupKey = array_search($customerGroupName, array_column($customerGroup, 'label'));

            if ($customerGroupKey !== false) {
                $groupId = $customerGroup[$customerGroupKey]['value'];
            } else {
                throw new GraphQlInputException(__('Customer Group is not valid'));
            }
        }

        if(isset($args['input']['entity_type']) && !empty($args['input']['entity_type'])) {
            $attribute = ['label' => $args['input']['entity_type'], 'key' => 'entity_type'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Customer Entity Type is not valid'));
            }
            $args['input']['entity_type'] = !empty($customerOptionValue) ? current($customerOptionValue) : 0;
        }

        if (isset($args['input']['enable_paylater_payment'])) {
            if ($args['input']['enable_paylater_payment']) {
                if (empty($args['input']['loan_partner'])) {
                    throw new GraphQlInputException(__('Loan Partner is require if enable_paylater_payment is true'));
                }
                $attribute = ['label' => $args['input']['loan_partner'], 'key' => 'loan_partner'];
                $loanPartnerOptionValue = $this->extractCustomerAttribute->get($attribute);
                if (empty($loanPartnerOptionValue)) {
                    throw new GraphQlInputException(__('Loan Partner value is not valid'));
                }
                $args['input']['loan_partner'] = $loanPartnerOptionValue;
            } else {
                $args['input']['loan_partner'] = "";
            }
        }

        if (!empty($args['input']['approval_status'])) {
            $newApprovalStatus = $args['input']['approval_status'];
            $attribute = ['label' => $args['input']['approval_status'], 'key' => 'approval_status'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Approval status is not valid'));
            }
            $args['input']['approval_status'] = $customerOptionValue;
        }

        if (!empty($args['input']['email'])) {
            $args['input']['customer_secondary_email'] = $args['input']['email'];
            unset($args['input']['email']);
        }

        if (!empty($args['input']['relationship_with_nominee'])) {
            $attribute = ['label' => $args['input']['relationship_with_nominee'], 'key' => 'relationship_with_nominee'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Relationship With Nominee is not valid'));
            }
            $args['input']['relationship_with_nominee'] = $customerOptionValue;
        }

        if (!empty($args['input']['gender'])) {
            $attribute = ['label' => $args['input']['gender'], 'key' => 'gender'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Gender is not valid'));
            }
            $args['input']['gender'] = !empty($customerOptionValue) ? current($customerOptionValue) : 0;
        }



        $isSendSms = false;
        $this->addLog('Updating Customer Details for ' . $customerId);
        $this->customer = $this->customerRepository->getById($customerId);
        $approvalStatus = 0;
        if (!empty($this->customer->getCustomAttribute('approval_status'))) {
            $approvalStatus = $this->customer->getCustomAttribute('approval_status')->getValue();
        }
        $customerType = $this->getCustomerAttributeValue($this->customer, 'customer_type');
        if (!empty($customerCustomType)) {
            $customerType = $customerCustomType;
        }
        $approvalOptions = $this->metadataService->getAttributeMetadata('approval_status')->getOptions();
        //get approved status id
        foreach ($approvalOptions as $optionData) {
            if ($approvalStatus == $optionData->getValue()) {
                $oldApprovalStatus = $optionData->getLabel();
            }
        }
        if (!empty($newApprovalStatus) && !empty($oldApprovalStatus) && ($oldApprovalStatus != $newApprovalStatus) && $newApprovalStatus == 'approved') {
            $isSendSms = true;
        }
      //  $this->postCode->isValidCustomerInsurance($customerType, $args['input']);
       // $this->postCode->isValidExpertInsurance($customerType, $args['input']);
        $taxVat = $this->customer->getTaxvat();
        $shippingAddressId = $this->customer->getDefaultShipping();
        $billingAddressId = $this->customer->getDefaultBilling();
        if (!empty($groupId)) {
            $this->customer->setGroupId($groupId);
        }
        $this->updateCustomerAccount->execute($this->customer, $args['input'], $context->getExtensionAttributes()->getStore());

        if ($isSendSms && !empty($customerType)) {
            $this->sendSms->sendApprovedSms($customerType, $mobileNumber);
        }

        //$this->oodoPush->create(['customer_id' => $customerId]);
        //get region info from Pincode and validating the address before customer creation
        if (!empty($postalInfo['billing'])) {
            $args['input']['billing_address']['region']['region_id'] = $postalInfo['billing']['region_id'];
            $args['input']['billing_address']['region']['region_code'] = $postalInfo['billing']['region_code'];
            $args['input']['billing_address']['region']['region'] = $postalInfo['billing']['state'];
            $args['input']['billing_address']['city'] = $postalInfo['billing']['city'];
            $args['input']['billing_address']['country_id'] = $args['input']['billing_address']['country_code'];
        }
        if (!empty($postalInfo['shipping'])) {
            $args['input']['shipping_address']['region']['region_id'] = $postalInfo['shipping']['region_id'];
            $args['input']['shipping_address']['region']['region_code'] = $postalInfo['shipping']['region_code'];
            $args['input']['shipping_address']['region']['region'] = $postalInfo['shipping']['state'];
            $args['input']['shipping_address']['city'] = $postalInfo['shipping']['city'];
            $args['input']['shipping_address']['country_id'] = $args['input']['shipping_address']['country_code'];
        }

        if (!empty($billingAddressId) && !empty($args['input']['billing_address'])) {
            $address = $this->getCustomerAddress->execute((int)$billingAddressId, (int)$customerId);
            if($shippingAddressId == $billingAddressId){
                $this->createCustomerAddress->execute((int)$customerId, $args['input']['billing_address']);
            } else {
                $this->updateCustomerAddress->execute($address, (array)$args['input']['billing_address']);
            }
            $this->addLog('Customer Billing Address Updated ');
        } elseif (!empty($args['input']['billing_address'])) {
            $this->createCustomerAddress->execute((int)$customerId, $args['input']['billing_address']);
            $this->addLog('Customer Billing Address Created ');
        }

        if (!empty($shippingAddressId) && !empty($args['input']['shipping_address'])) {
            $address = $this->getCustomerAddress->execute((int)$shippingAddressId, (int)$customerId);
            $this->addLog('Customer Shipping Address Updated ');
            $this->updateCustomerAddress->execute($address, (array)$args['input']['shipping_address']);
        } elseif (!empty($args['input']['shipping_address'])) {
            $this->addLog('Customer Shipping Address Created ');
            $this->createCustomerAddress->execute((int)$customerId, $args['input']['shipping_address']);
        }

        /* calling Ibo wallet in customer update */
        $walletData = [
            'customer_id' => $this->customer->getId(),
            'customer_group_id' => $this->customer->getGroupId(),
            'mobilenumber' => $this->customer->getCustomAttribute("mobilenumber")->getValue()
                ?? '',
            'approval_status' => $this->customer->getCustomAttribute('approval_status')->getValue()[0] ?? ''
        ];
        $this->wallet->createWallet($walletData);

        $data = $this->extractCustomerData->execute($this->customer);
        if (!empty($customerId)) {
            $data['customer_increment_id'] = $customerId;
        }
        return ['customer' => $data];
    }

    private function getCustomerAttributeValue($customer, $attributeCode)
    {
        $customerStatus = $customer->getCustomAttribute($attributeCode);
        $optionValues = [];
        if (!empty($customerStatus)) {
            $this->attributeCollection->setIdFilter(explode(',', $customer->getCustomAttribute($attributeCode)->getValue()))->setStoreFilter();
            $options = $this->attributeCollection->toOptionArray();
            if (!empty($options)) {
                array_walk($options, function ($value, $key) use (&$optionValues) {
                    $optionValues[] = $value['label'];
                });
            }
        }
        return implode(',', $optionValues);
    }

    /**
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    private function createCustomer($customerId, $mobileNumber, $context, $args): array
    {
        if (empty($args['input']['approval_status'])) {
            $args['input']['approval_status'] = 'pending';
        }
        if (empty($args['input']['firstname'])) {
            $args['input']['firstname'] = '-';
        }
        if (empty($args['input']['lastname'])) {
            $args['input']['lastname'] = '-';
        }
        if (empty($args['input']['customer_type'])) {
            $customerCustomType = $args['input']['customer_type'] = 'B2C';
        }

        if ($mobileNumber) {
            $number = str_replace("+91", "", $mobileNumber);
            $args['input']['email'] = $number . '@' . $number . '.com';
        }
        if (isset($args['input']['customer_type'])) {
            // if ($args['input']['customer_type'] == 'B2C') {
            //     $args['input']['customer_type'] = "Individual";
            // }
            $customerTypeLabel = $args['input']['customer_type'];
           // $this->postCode->isValidCustomerInsurance($args['input']['customer_type'], $args['input']);
          //  $this->postCode->isValidExpertInsurance($args['input']['customer_type'], $args['input']);

            $attribute = ['label' => $args['input']['customer_type'], 'key' => 'customer_type'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Customer Type is not valid'));
            }
            $customerTypeArray = explode(',', $args['input']['customer_type']);
            $args['input']['customer_type'] = $customerOptionValue;
            $customerGroup = $this->customerGroupCollection->toOptionArray();

            $customerGroupName = trim($args['input']['customer_group']);

            /* changed from B2C to B2P based on BAP-725 */
            $customerGroupKey = array_search($customerGroupName, array_column($customerGroup, 'label'));

            if ($customerGroupKey !== false) {
                $groupId = $customerGroup[$customerGroupKey]['value'];
            } else {
                throw new GraphQlInputException(__('Customer Group is not valid'));
            } //echo $groupId; exit;
        }


        $isSendSms = false;
        if (isset($args['input']['approval_status'])) {
            $isSendSms = $args['input']['approval_status'] === 'approved';
            $attribute = ['label' => $args['input']['approval_status'], 'key' => 'approval_status'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Approval status is not valid'));
            }
            $args['input']['approval_status'] = $customerOptionValue;
        }

        if (!empty($args['input']['relationship_with_nominee'])) {
            $attribute = ['label' => $args['input']['relationship_with_nominee'], 'key' => 'relationship_with_nominee'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Relationship With Nominee is not valid'));
            }
            $args['input']['relationship_with_nominee'] = $customerOptionValue;
        }

        if(isset($args['input']['entity_type']) && !empty($args['input']['entity_type'])) {
            $attribute = ['label' => $args['input']['entity_type'], 'key' => 'entity_type'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Customer Entity Type is not valid'));
            }
            $args['input']['entity_type'] = !empty($customerOptionValue) ? current($customerOptionValue) : 0;
        }

        if (!empty($args['input']['gender'])) {
            $attribute = ['label' => $args['input']['gender'], 'key' => 'gender'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if (empty($customerOptionValue)) {
                throw new GraphQlInputException(__('Gender is not valid'));
            }
            $args['input']['gender'] = !empty($customerOptionValue) ? current($customerOptionValue) : 0;
        }

        if (!$this->newsLetterConfig->isActive(ScopeInterface::SCOPE_STORE)) {
            $args['input']['is_subscribed'] = false;
        }
        if (isset($args['input']['date_of_birth'])) {
            $args['input']['dob'] = $args['input']['date_of_birth'];
        }
        $args['input']['first_time_promo_applied'] = false;
        $args['input']['entity_id'] = $customerId;
        $customer = $this->customerFactory->create();
        $customer->setData($args['input']);
        $customer->setWebsiteId($context->getExtensionAttributes()->getStore()->getWebsiteId());
        $customer->save();
        $customer = $this->customerRepository->getById($customer->getId());

        $customerId = $customer->getId();

        //set customer Group
        if (!empty($groupId)) {
            $customer->setGroupId($groupId);
            $this->customerRepository->save($customer);
        }

        /* calling Ibo wallet in customer creation */
        $walletData = [
            'customer_id' => $customer->getId(),
            'customer_group_id' => $customer->getGroupId(),
            'mobilenumber' => $customer->getCustomAttribute("mobilenumber")->getValue()
                ?? '',
            'approval_status' => $customer->getCustomAttribute('approval_status')->getValue() ?? ''
        ];
        $this->wallet->createWallet($walletData);

        $this->addLog('Customer Created for ' . $customerId);
        if ($isSendSms && !empty($customerTypeLabel)) {
            $this->sendSms->sendApprovedSms($customerTypeLabel, $mobileNumber);
        }

        $data = $this->extractCustomerData->execute($customer);
        if (!empty($customerId)) {
            $data['customer_increment_id'] = $customerId;
        }
        return ['customer' => $data];
    }
}
