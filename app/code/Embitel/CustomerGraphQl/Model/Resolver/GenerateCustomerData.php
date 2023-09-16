<?php

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Embitel\Sms\Model\Customer\MobileCustomer;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Integration\Model\Oauth\Token;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;

class GenerateCustomerData implements ResolverInterface
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
        groupRepository $groupRepository,
        Token $token,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        AttributeCollection $attributeCollection,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->validateMobile = $validateMobile;  
        $this->mobileCustomer = $mobileCustomer;
        $this->groupRepository = $groupRepository;  
        $this->token = $token;    
        $this->addressRepository = $addressRepository;
        $this->attributeCollection = $attributeCollection;
        $this->customerRepository = $customerRepository;
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

        if (isset($args['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }

        if (!isset($args['m2m_token'])) {
            throw new GraphQlInputException(__('M2M token value should be specified'));
        }

        $bearerToken = $args['m2m_token'];
        $accessData = $this->token->loadByToken($bearerToken);
        $customerData = [];

    if($accessData->getId() && ($accessData->getUserType() == 1)) { 
        if(isset($args['mobilenumber'])) {
            $customer = $this->validateMobile->isMobileAssociatedToCustomer($args['mobilenumber']);
        } else {
            if(isset($args['customerId'])) {
                $customer = $this->customerRepository->getById($args['customerId']);
            }
        }
        
        if(($customer != '') && ($customer->getId())) {

            $isB2BCustomer = false;
            
            $groupId = $customer->getGroupId();
            $customerGroup = $this->getGroupName($groupId);
            //$isB2BCustomer = ($customerGroup == 'B2B') ? true : false;
            
            $customerDetail = $this->customerRepository->getById($customer->getId());
            $customerType = $this->getCustomerAttributeValue($customerDetail, 'customer_type');
            $customer_type =  !empty($customerType ) ? $customerType  : '';
           
            $customerData['status'] = true;
            $customerData['message'] = 'The mobile number is registered with customer';
            $customerData['customer']['customer_id'] = $customer->getId();
            $customerData['customer']['customer_group'] = $customerGroup;
            $customerData['customer']['customer_type'] = $customer_type;
            $customerData['customer']['email_id'] = $customer->getEmail();

            if(($customer->getTaxvat() != '') && ($customer->getTaxvat() != 'UNREGISTERED')) {
                $taxvat = $customer->getTaxvat();
            } else {
                $taxvat = '';
            }

            if(isset($args['mobilenumber'])) {
                $mobilenumber = !empty($customer->getMobilenumber()) ? $customer->getMobilenumber() : '';
            } else {
                if(isset($args['customerId'])) {
                    $mobilenumber = $customer->getCustomAttribute('mobilenumber')->getValue();
                }
            }

            $customerData['customer']['gstin'] = $taxvat;

            $customerData['customer']['customer_name']['salutation'] = '';
            $customerData['customer']['customer_name']['first_name'] =!empty($customer->getFirstname()) ? $customer->getFirstname() : '';
            $customerData['customer']['customer_name']['middle_name'] = '';
            $customerData['customer']['customer_name']['last_name'] =!empty($customer->getLastname()) ?  $customer->getLastname() : '';
            $customerData['customer']['customer_name']['suffix'] = !empty($customer->getSuffix()) ?  $customer->getSuffix() : '';

            $customerData['customer']['phone_number']['country_code'] = '+91';
            $customerData['customer']['phone_number']['number'] = $mobilenumber;

            $customerData['customer']['is_b2b_customer'] = $isB2BCustomer;
            $customerData['customer']['entity_name'] = '';

            $billingAddressId = $customer->getDefaultBilling();
            $billingAddress = '';
            if($billingAddressId){
                $billingAddress = $this->addressRepository->getById($billingAddressId);
            }
            
            if(!empty($billingAddress)){
                $billingLandmark = $billingAddress->getCustomAttribute('landmark');
                $customerData['customer']['billing_address']['firstname'] = $billingAddress->getFirstName();
                $customerData['customer']['billing_address']['lastname'] = $billingAddress->getLastName();
                $customerData['customer']['billing_address']['street'] = $billingAddress->getStreet();
                $customerData['customer']['billing_address']['city'] = $billingAddress->getCity();
                $customerData['customer']['billing_address']['region']['region_id'] = $billingAddress->getRegion()->getRegionId();
                $customerData['customer']['billing_address']['region']['region_code'] = $billingAddress->getRegion()->getRegionCode();
                $customerData['customer']['billing_address']['region']['region'] = $billingAddress->getRegion()->getRegion();
                $customerData['customer']['billing_address']['landmark'] =!empty($billingLandmark) ? $billingLandmark->getValue() : '';
                $customerData['customer']['billing_address']['country_code'] = $billingAddress->getCountryId();
                $customerData['customer']['billing_address']['postcode'] = $billingAddress->getPostcode();
                $customerData['customer']['billing_address']['telephone'] = $billingAddress->getTelephone();  
            }

            $shippingAddressId = $customer->getDefaultShipping();
            $shippingAddress = '';
            if($shippingAddressId){
                $shippingAddress = $this->addressRepository->getById($shippingAddressId);
            }
            
            if(!empty($shippingAddress)){
                $shippingLandmark = $shippingAddress->getCustomAttribute('landmark');
                $customerData['customer']['shipping_address']['firstname'] = $shippingAddress->getFirstName();
                $customerData['customer']['shipping_address']['lastname'] = $shippingAddress->getLastName();
                $customerData['customer']['shipping_address']['street'] = $shippingAddress->getStreet();
                $customerData['customer']['shipping_address']['city'] = $shippingAddress->getCity();
                $customerData['customer']['shipping_address']['region']['region_id'] = $shippingAddress->getRegion()->getRegionId();
                $customerData['customer']['shipping_address']['region']['region_code'] = $shippingAddress->getRegion()->getRegionCode();
                $customerData['customer']['shipping_address']['region']['region'] = $shippingAddress->getRegion()->getRegion();
                $customerData['customer']['shipping_address']['landmark'] =!empty($shippingLandmark) ? $shippingLandmark->getValue() : '';
                $customerData['customer']['shipping_address']['country_code'] = $shippingAddress->getCountryId();
                $customerData['customer']['shipping_address']['postcode'] = $shippingAddress->getPostcode();
                $customerData['customer']['shipping_address']['telephone'] = $shippingAddress->getTelephone();  
            }
         

        } else {
            $customerData['status'] = false;
            $customerData['message'] = 'The mobile number is not registered with customer';
            $customerData['customer'] = [];
        }
    } else {
        $customerData['status'] = false;
        $customerData['message'] = 'The m2m token is not valid';
        $customerData['customer'] = [];
    }
        
        return $customerData;
    }

    private function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
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

}
