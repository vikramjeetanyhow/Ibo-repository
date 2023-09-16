<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Embitel\CatalogGraphQl\Model\Resolver\Customer\GetCustomerGroup;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;

/**
 * Customer Group Code field resolver
 */
class BillingAddress implements ResolverInterface
{
    private $attributeCollection;

    public function __construct(
        GroupRepositoryInterface $groupRepository,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        AttributeCollection $attributeCollection,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        GetCustomerGroup $getCustomerGroup
    ) {
        $this->groupRepository = $groupRepository;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->getCustomerGroup = $getCustomerGroup;
        $this->_regionFactory = $regionFactory;
        $this->attributeCollection = $attributeCollection;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var CustomerInterface $customer */
        $customer = $value['model'];
        $customer_increment_id = (int)$customer->getId();
        $customerId = (int)$customer->getId();
        $customer = $this->customerRepository->getById($customerId);
        $billingAddressId = $customer->getDefaultBilling();
        $billingAddress = '';
        if($billingAddressId){
            $billingAddress = $this->addressRepository->getById($billingAddressId);
        }
        $customer_default_billing = [];
        if(!empty($billingAddress)){
            $billingLandmark = $billingAddress->getCustomAttribute('landmark');
            $customer_default_billing['customer_default_billing']['firstname'] = $billingAddress->getFirstName();
            $customer_default_billing['customer_default_billing']['lastname'] = $billingAddress->getLastName();
            $customer_default_billing['customer_default_billing']['street'] = $billingAddress->getStreet();
            $customer_default_billing['customer_default_billing']['city'] = $billingAddress->getCity();
            $customer_default_billing['customer_default_billing']['region']['region_id'] = $billingAddress->getRegion()->getRegionId();
            $customer_default_billing['customer_default_billing']['region']['region_code'] = $billingAddress->getRegion()->getRegionCode();
            $customer_default_billing['customer_default_billing']['region']['region'] = $billingAddress->getRegion()->getRegion();
            $customer_default_billing['customer_default_billing']['landmark'] =!empty($billingLandmark) ? $billingLandmark->getValue() : '';
            $customer_default_billing['customer_default_billing']['country_code'] = $billingAddress->getCountryId();
            $customer_default_billing['customer_default_billing']['postcode'] = $billingAddress->getPostcode();
            $customer_default_billing['customer_default_billing']['telephone'] = $billingAddress->getTelephone();  
         }
         
        return $customer_default_billing;
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
