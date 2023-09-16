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
class CustomerType implements ResolverInterface
{
    private $attributeCollection;

    public function __construct(
        GroupRepositoryInterface $groupRepository,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        AttributeCollection $attributeCollection,
        GetCustomerGroup $getCustomerGroup
    ) {
        $this->groupRepository = $groupRepository;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->getCustomerGroup = $getCustomerGroup;
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
        //$customer_increment_id = (int)$customer->getId();
        $customerId = (int)$customer->getId();
        $customer_type = '';
        if($customerId){
            //$customerType['customer_increment_id'] = (int)$customer->getId();
            $customerType = $this->getCustomerAttributeValue($customer, 'customer_type');
            $customer_type =  !empty($customerType ) ? $customerType  : '';
            
        }
        
     return $customer_type;
    }

     
    private function getCustomerAttributeValue($customer, $attributeCode)
    {
        $customerStatus =$customer->getCustomAttribute($attributeCode);
        $optionValues = [];
        if (!empty($customerStatus)) {
            $value = explode(',', $customer->getCustomAttribute($attributeCode)->getValue());
            if(is_numeric($value[0])) {
                $this->attributeCollection->setIdFilter(explode(',', $customer->getCustomAttribute($attributeCode)->getValue()))
                ->setStoreFilter();
                $options = $this->attributeCollection->toOptionArray();
                if (!empty($options)) {
                    array_walk($options, function ($value, $key) use (&$optionValues) {
                        $optionValues[] = $value['label'];
                    });
                }
                return implode(',', $optionValues);
            } else {
               return $customer->getCustomAttribute($attributeCode)->getValue();
            }
        }
        
    }
}