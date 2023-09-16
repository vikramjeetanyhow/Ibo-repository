<?php

namespace Embitel\CustomerGraphQl\Model\Customer;

/**
 * Create customer account resolver
 */
class ValidateMobile
{
    /**
     *
     * @param \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory
     */
    public function __construct(
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    public function isMobileExist($mobileNumber)
    {
        /* Check if the mobile is used by other */
        $collection = $this->getCustomer('mobilenumber', $mobileNumber);
        if ($collection->getSize() > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function isEboMobileExist($mobileNumber)
    {
        /* Check if the mobile is used by other */
        $collection = $this->getCustomer('mobilenumber', $mobileNumber);
        if ($collection->getSize() > 0) {
            $customer = $collection->getFirstItem();
            return $customer->getId();
        } else {
            return false;
        }
    }

    public function isMobileAssociatedToCustomer($mobileNumber)
    {
        $customer = '';
        $collection = $this->getCustomer('mobilenumber', $mobileNumber);
        if ($collection->getSize() > 0) {
            $customer = $collection->getFirstItem();
            return $customer;
        } else {
            return $customer;
        }
    }

    public function getCustomerByEmail($email)
    {
        $customer = '';
        $collection = $this->customerCollectionFactory->create()
                ->addAttributeToFilter('email', $email);
        if ($collection->getSize() > 0) {
            $customer = $collection->getFirstItem();
            return $customer;
        } else {
            return $customer;
        }
    }

    public function isCustomerExists($id): bool
    {
        $collection = $this->customerCollectionFactory->create()->addAttributeToFilter('entity_id', $id);
        if ($collection->getSize() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getCustomer($field, $value)
    {
        $collection = $this->customerCollectionFactory->create()
                   ->addAttributeToFilter($field, $value);
        return $collection;
    }
}
