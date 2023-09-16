<?php

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Embitel\Sms\Model\Customer\MobileCustomer;
use Magento\Integration\Model\Oauth\Token;
use Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Embitel\Customer\Model\Wallet;

class CreateCustomerToken implements ResolverInterface
{

    /**
     * @var \Embitel\CustomerGraphQl\Model\Customer\ValidateMobile
     */
    protected $validateMobile;

    /**
     * @var MobileCustomer
     */
    protected $mobileCustomer;

    /**
     *
     * @param ValidateMobile $validateMobile
     */
    public function __construct(
        ValidateMobile $validateMobile,
        MobileCustomer $mobileCustomer,
        Token $token,
        CreateCustomerAccount $createCustomerAccount,
        \Magento\Customer\Model\Group $group,
        CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ExtractCustomerAttribute $extractCustomerAttribute,
        AttributeCollection $attributeCollection,
        groupRepository $groupRepository,
        Wallet $wallet
    ) {
        $this->validateMobile = $validateMobile;
        $this->mobileCustomer = $mobileCustomer;
        $this->token = $token;
        $this->createCustomerAccount = $createCustomerAccount;
        $this->group = $group;
        $this->customerRepository = $customerRepository;
        $this->scopeConfig = $scopeConfig;
        $this->extractCustomerAttribute = $extractCustomerAttribute;
        $this->attributeCollection = $attributeCollection;
        $this->groupRepository = $groupRepository;
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
        /* check if mobile number is passed in graphql and validate the same */
        if (!isset($args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value should be specified'));
        }

        if (isset($args['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }

        if (!isset($args['m2m_token'])) {
            throw new GraphQlInputException(__('M2M token value should be specified'));
        }

        $bearerToken = $args['m2m_token'];
        $accessData = $this->token->loadByToken($bearerToken);
        $customerData = [];
        $customerDetail = [];

    if($accessData->getId() && ($accessData->getUserType() == 1)) {

        $customer = $this->validateMobile->isMobileAssociatedToCustomer($args['mobilenumber']);
        if($customer != '') {
            $token = $this->mobileCustomer->createCustomerAccessToken($customer);

            $customerData = $this->customerRepository->getById($customer->getId());
            $customerType = $this->getCustomerAttributeValue($customerData, 'customer_type');
            $customer_type =  !empty($customerType ) ? $customerType  : '';

            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);

            $customerDetail['is_customer'] = true;
            $customerDetail['msg'] = 'The mobile number is registered with customer';
            $customerDetail['token'] = $token;
            $customerDetail['customer_increment_id'] = $customer->getId();
            $customerDetail['mobilenumber'] = $customer->getMobilenumber();
            $customerDetail['firstname'] = $customer->getFirstname();
            $customerDetail['lastname'] = $customer->getLastname();
            $customerDetail['email'] = $customer->getEmail();
            $customerDetail['profile_picture'] = '';
            $customerDetail['is_customer_new'] = false;
            $customerDetail['customer_type'] = $customer_type;
            $customerDetail['customer_group'] = $customerGroup;

        } else {

            $auto_register_flag = $this->scopeConfig->getValue("create_auto_register/auto_register/auto_register_status");

            if($auto_register_flag) {

                $approval_attribute = ['label' => 'pending', 'key' => 'approval_status'];
                $approvalcustomerOptionValue = $this->extractCustomerAttribute->get($approval_attribute);

                $customertype_attribute = ['label' => 'Individual', 'key' => 'customer_type'];
                $customertypeOptionValue = $this->extractCustomerAttribute->get($customertype_attribute);

                $cusGrpCode = 'B2P';
                $existingGroup = $this->group->load($cusGrpCode, 'customer_group_code');
                $groupId = $existingGroup->getData('customer_group_id');
                $mobileNumber = $args['mobilenumber'];
                $customerData1['mobilenumber'] = $args['mobilenumber'];
                $customerData1['email'] = $mobileNumber.'@'.$mobileNumber.'.com';
                $customerData1['firstname'] = '-';
                $customerData1['lastname'] = '-';

                $customer = $this->createCustomerAccount->execute(
                    $customerData1,$context->getExtensionAttributes()->getStore()
                );
                $this->addLog('Customer registered with given data. The given request below');
                $this->addLog(json_encode($customerData1));

                //set customer Group
                // $customer->setGroupId($groupId);
                $customer->setCustomAttribute('customer_type',$customertypeOptionValue);
                $customer->setCustomAttribute('approval_status',$approvalcustomerOptionValue);
                $this->customerRepository->save($customer);

                $customerData = $this->customerRepository->getById($customer->getId());
                $customerType = $this->getCustomerAttributeValue($customerData, 'customer_type');
                $customer_type =  !empty($customerType ) ? $customerType  : '';

                $groupId = $customer->getGroupId();
                $customerGroup = $this->getGroupName($groupId);

                /* calling Ibo wallet in customer creation */
                $walletData = [
                    'customer_id' => $customerData->getId(),
                    'customer_group_id' => $customerData->getGroupId(),
                    'mobilenumber' => $customerData->getCustomAttribute("mobilenumber")->getValue()
                        ?? '',
                    'approval_status' => $customerData->getCustomAttribute('approval_status')->getValue() ?? ''
                ];
                $this->walletModel->createWallet($walletData);

                $token = $this->mobileCustomer->createCustomerAccessToken($customer);
                $customerDetail['is_customer'] = true;
                $customerDetail['msg'] = 'The mobile number is registered with customer';
                $customerDetail['token'] = $token;
                $customerDetail['customer_increment_id'] = $customer->getId();
                $customerDetail['mobilenumber'] = $customer->getCustomAttribute('mobilenumber')->getValue();
                $customerDetail['firstname'] = $customer->getFirstname();
                $customerDetail['lastname'] = $customer->getLastname();
                $customerDetail['email'] = $customer->getEmail();
                $customerDetail['profile_picture'] = '';
                $customerDetail['is_customer_new'] = true;
                $customerDetail['customer_type'] = $customer_type;
                $customerDetail['customer_group'] = $customerGroup;
            } else {
                $customerDetail['is_customer'] = false;
                $customerDetail['msg'] = 'The mobile number is not registered with customer';
                $customerDetail['token'] = '';
                $customerDetail['customer_increment_id'] = '';
                $customerDetail['mobilenumber'] = '';
                $customerDetail['firstname'] = '';
                $customerDetail['lastname'] = '';
                $customerDetail['email'] = '';
                $customerDetail['profile_picture'] = '';
                $customerDetail['is_customer_new'] = false;
                $customerDetail['customer_type'] = '';
                $customerDetail['customer_group'] = '';
            }
        }

    } else {
        $customerDetail['is_customer'] = false;
        $customerDetail['msg'] = 'The m2m token is not valid';
        $customerDetail['token'] = '';
        $customerDetail['customer_increment_id'] = '';
        $customerDetail['firstname'] = '';
        $customerDetail['lastname'] = '';
        $customerDetail['mobilenumber'] = '';
        $customerDetail['email'] = '';
        $customerDetail['profile_picture'] = '';
        $customerDetail['is_customer_new'] = false;
        $customerDetail['customer_type'] = '';
        $customerDetail['customer_group'] = '';
    }

    return $customerDetail;

    }

    public function addLog($logData, $filename = "auto-registration.log")
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

    private function getCustomerAttributeValue($customer, $attributeCode)
    {
        $customerStatus =$customer->getCustomAttribute($attributeCode);
        $optionValues = [];
        if (!empty($customerStatus)) {
            $this->attributeCollection->setIdFilter(explode(',', $customer->getCustomAttribute($attributeCode)->getValue()))
            ->setStoreFilter();
            $options = $this->attributeCollection->toOptionArray();
            if (!empty($options)) {
                array_walk($options, function ($value, $key) use (&$optionValues) {
                    $optionValues[] = $value['label'];
                });
            }
        }
        return implode(',', $optionValues);
    }

    private function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

}
