<?php

namespace Embitel\Sms\Model\Customer;

/**
 * Create customer account resolver
 */
class ValidateMobile
{

    /**
     * @var \Embitel\Sms\Helper\Data
     */
    protected $helper;

    /**
     * @var \Embitel\Sms\Model\OtpFactory
     */
    protected $mobileFactory;

    /**
     *
     * @param \Embitel\Sms\Helper\Data $helper
     * @param \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory
     */
    public function __construct(
        \Embitel\Sms\Helper\Data $helper,
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory
    ) {
        $this->helper = $helper;
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
    
    public function getCustomer($field, $value)
    {
        $collection = $this->customerCollectionFactory->create()
                   ->addAttributeToFilter($field, $value);
        return $collection;
    }
}
