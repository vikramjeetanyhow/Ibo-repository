<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Newsletter\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Magento\CustomerGraphQl\Model\Customer\UpdateCustomerAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\CustomerGraphQl\Model\Customer\Address\CreateCustomerAddress as CreateCustomerAddressModel;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Embitel\CustomerGraphQl\Model\Customer\Address\PostCode;
use Embitel\Notification\Model\SendSms;
use Embitel\Customer\Model\Wallet;

/**
 * Create customer account resolver
 */
class CreateEboCustomer implements ResolverInterface
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

    private $date;

    private $file;

    private $dir;

    /**
     * @var SendSms
     */
    private $sendSms;

    /**
     * @var PostCode
     */
    private $postCode;

    /**
     * @var ExtractCustomerAttribute
     */
    private $extractCustomerAttribute;

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
     * @param ExtractCustomerAttribute $extractCustomerAttribute
     * @param CustomerGroupCollection $customerGroupCollection
     * @param PostCode $postCode
     * @param SendSms $sendSms
     */
    public function __construct(
        ExtractCustomerData $extractCustomerData,
        CreateCustomerAccount $createCustomerAccount,
        Config $newsLetterConfig,
        ValidateMobile $validateMobile,
        CreateCustomerAddressModel $createCustomerAddress,
        UpdateCustomerAccount $updateCustomerAccount,
        CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $date,
        ExtractCustomerAttribute $extractCustomerAttribute,
        CustomerGroupCollection $customerGroupCollection,
        PostCode $postCode,
        SendSms $sendSms,
        Wallet $wallet
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
        $this->date = $date;
        $this->file = $file;
        $this->postCode = $postCode;
        $this->sendSms = $sendSms;
        $this->walletModel = $wallet;
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
        $this->addLog('<---------->');
        $this->addLog('Payload - ' . json_encode($args['input']));
        if(isset($args['input']['business_activities'])){
            $baValue = [];
            foreach($args['input']['business_activities'] as $businessValue){
                $baAttribute = ['label' => $businessValue, 'key' => 'business_activities'];
                $baValue[] = !empty($this->extractCustomerAttribute->get($baAttribute)) ? current($this->extractCustomerAttribute->get($baAttribute)) : '';
            }
            $args['input']['business_activities'] = implode(',',$baValue);
        }

        $errorFields = [];
        if(empty($args['input']['customer_type'])){
            $errorFields[] = 'customer_type';
        }
        if(empty($args['input']['approval_status'])){
            $errorFields[] = 'approval_status';
        }
        if(!isset($args['input']['mobilenumber'])){
            $errorFields[] = 'mobilenumber';
        }

        //Get Zip code information from csv sheet
        $postalInfo = [];
        if(!empty($args['input']['billing_address']['postcode'])
            && !empty($args['input']['shipping_address']['postcode'])
            && $args['input']['billing_address']['postcode'] == $args['input']['shipping_address']['postcode']){
                $postalInfo['billing'] = $postalInfo['shipping'] = $this->postCode->getCsvData($args['input']['billing_address']);
        }

        if(empty($postalInfo)
            && (!empty($args['input']['billing_address']['postcode']) || !empty($args['input']['shipping_address']['postcode']))){
            $postalInfo['billing'] = !empty($args['input']['billing_address']) ? $this->postCode->getCsvData($args['input']['billing_address']) : [];
            $postalInfo['shipping'] = !empty($args['input']['shipping_address']) ? $this->postCode->getCsvData($args['input']['shipping_address']) : [];
        }

        if(!empty($errorFields)){
            throw new GraphQlInputException(__(implode('/', $errorFields) . ' is mandatory'));
        }

        if (isset($args['input']['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['input']['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }

        if (isset($args['input']['mobilenumber'])
            && !$this->validateMobile->isMobileExist($args['input']['mobilenumber'])) {
                throw new GraphQlInputException(__('Mobile number already exists'));
        }
        $mobileNumber = $args['input']['mobilenumber'];

        if (isset($args['input']['mobilenumber']) && empty($args['input']['email'])) {
            $mobilenumber = str_replace("+91", "", $args['input']['mobilenumber']);
            $args['input']['email'] = $mobilenumber.'@'.$mobilenumber.'.com';
        }

        if (isset($args['input']['customer_type'])){
            if($args['input']['customer_type'] == 'B2C'){
                $args['input']['customer_type'] = "Individual";
            }
            $customerTypeLabel = $args['input']['customer_type'];
            $this->postCode->isValidCustomerInsurance($args['input']['customer_type'], $args['input']);
            $this->postCode->isValidExpertInsurance($args['input']['customer_type'], $args['input']);

            $attribute = ['label' => $args['input']['customer_type'], 'key' => 'customer_type'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if(empty($customerOptionValue)){
                throw new GraphQlInputException(__('Customer Type is not valid'));
            }
            $customerTypeArray = explode(',',$args['input']['customer_type']);
            $args['input']['customer_type'] = $customerOptionValue;
            $customerGroup = $this->customerGroupCollection->toOptionArray();
            /* changed from B2C to B2P based on BAP-725 */
            $customerGroupKey = array_search('B2P',array_column($customerGroup, 'label'));

            if(in_array('Dealer',$customerTypeArray)){
                $customerGroupKey = array_search('B2B',array_column($customerGroup, 'label'));
            }
            if(in_array('Retailer',$customerTypeArray)){
                $customerGroupKey = array_search('B2B',array_column($customerGroup, 'label'));
            }
            if(in_array('Individual',$customerTypeArray)){
                $customerGroupKey = array_search('B2C',array_column($customerGroup, 'label'));
            }
            if($customerGroupKey !== false){
                $groupId = $customerGroup[$customerGroupKey]['value'];
            }
        }

        $isSendSms = false;
        if (isset($args['input']['approval_status'])){
            $isSendSms = ($args['input']['approval_status'] == 'approved') ? true : false;
            $attribute = ['label' => $args['input']['approval_status'], 'key' => 'approval_status'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if(empty($customerOptionValue)){
                throw new GraphQlInputException(__('Approval status is not valid'));
            }
            $args['input']['approval_status'] = $customerOptionValue;
        }

        if (!empty($args['input']['relationship_with_nominee'])){
            $attribute = ['label' => $args['input']['relationship_with_nominee'], 'key' => 'relationship_with_nominee'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if(empty($customerOptionValue)){
                throw new GraphQlInputException(__('Relationship With Nominee is not valid'));
            }
            $args['input']['relationship_with_nominee'] = $customerOptionValue;
        }

        if (!empty($args['input']['gender'])){
            $attribute = ['label' => $args['input']['gender'], 'key' => 'gender'];
            $customerOptionValue = $this->extractCustomerAttribute->get($attribute);
            if(empty($customerOptionValue)){
                throw new GraphQlInputException(__('Gender is not valid'));
            }
            $args['input']['gender'] = !empty($customerOptionValue) ? current($customerOptionValue) : 0;
        }

        $customer = null;

        if (!$this->newsLetterConfig->isActive(ScopeInterface::SCOPE_STORE)) {
            $args['input']['is_subscribed'] = false;
        }
        if (isset($args['input']['date_of_birth'])) {
            $args['input']['dob'] = $args['input']['date_of_birth'];
        }

        //get region info from Pincode and validating the address before customer creation
        if(isset($args['input']['billing_address']['region']['region'])
            && !empty($postalInfo['billing'])){
                $args['input']['billing_address']['region']['region_id'] = $postalInfo['billing']['region_id'];
                $args['input']['billing_address']['region']['region_code'] = $postalInfo['billing']['region_code'];
                $args['input']['billing_address']['region']['region'] = $postalInfo['billing']['state'];
                $args['input']['billing_address']['city'] = $postalInfo['billing']['city'];
                $args['input']['billing_address']['country_id'] = $args['input']['billing_address']['country_code'];
                $this->createCustomerAddress->validateData($args['input']['billing_address']);
        }
        if(isset($args['input']['shipping_address']['region']['region']) && !empty($postalInfo['shipping'])){
            $args['input']['shipping_address']['region']['region_id'] = $postalInfo['shipping']['region_id'];
            $args['input']['shipping_address']['region']['region_code'] = $postalInfo['shipping']['region_code'];
            $args['input']['shipping_address']['region']['region'] = $postalInfo['shipping']['state'];
            $args['input']['shipping_address']['city'] = $postalInfo['shipping']['city'];
            $args['input']['shipping_address']['country_id'] = $args['input']['shipping_address']['country_code'];
            $this->createCustomerAddress->validateData($args['input']['shipping_address']);
        }

        //creation of customer
        $customer = $this->createCustomerAccount->execute(
            $args['input'],
            $context->getExtensionAttributes()->getStore()
        );
        $customerId = $customer->getId();

        //set customer Group
        if(!empty($groupId)){
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
        $this->walletModel->createWallet($walletData);

        $this->addLog('Customer Created for ' . $customerId);
        if(isset($args['input']['billing_address'])){
            $this->createCustomerAddress->execute((int)$customerId, $args['input']['billing_address']);
        }
        if(isset($args['input']['shipping_address'])){
            $this->createCustomerAddress->execute((int)$customerId, $args['input']['shipping_address']);
        }

        if($isSendSms && !empty($customerTypeLabel)){
            $this->sendSms->sendApprovedSms($customerTypeLabel, $mobileNumber);
        }

        $data = $this->extractCustomerData->execute($customer);
        if(!empty($customerId)){
            $data['customer_increment_id'] = $customerId;
        }
        return ['customer' => $data];
    }

    public function addLog($logData){
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        $logEnable = 1;
        if ($logEnable) {
            $filename = BP . '/var/log/ebo-customer-creation.log';
            $writer = new \Zend\Log\Writer\Stream($filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }
}
