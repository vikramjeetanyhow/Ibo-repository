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
use Magento\CustomerGraphQl\Model\Customer\Address\CreateCustomerAddress as CreateCustomerAddressModel;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Embitel\CustomerGraphQl\Model\Customer\Address\PostCode;
use Embitel\Notification\Model\SendSms;


/**
 * Create customer account resolver
 */
class UpdateReferrerCustomer implements ResolverInterface
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
     * @var CustomerHelper
     */
    protected $customerHelper;

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
     * @param string $customerHelper
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
        SendSms $sendSms
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

        if(empty($args['input']['customer_id']) || empty($args['input']['referrer_customer_id']) || empty($args['input']['referrer_date'])){
            throw new GraphQlInputException(__('Input should have some value'));
        }
        $isReffererExists = $this->customerRepository->get($args['input']['referrer_customer_id']);

        if(empty($isReffererExists)){
            throw new GraphQlInputException(__('Referrer Id does not exists.'));
        }
        $customer = $this->customerRepository->get($args['input']['customer_id']);
        if($customer){
            $this->updateCustomerAccount->execute(
                $customer,
                $args['input'],
                $context->getExtensionAttributes()->getStore()
            );
            $data = $this->extractCustomerData->execute($customer);
        }else{
            throw new GraphQlInputException(__('Customer Not found'));
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
            $filename = BP . '/var/log/ebo-referrer-update.log';
            $writer = new \Zend\Log\Writer\Stream($filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    } 
}
