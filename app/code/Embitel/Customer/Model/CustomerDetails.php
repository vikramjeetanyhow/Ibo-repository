<?php

/**
 * @package Embitel_Customer
 *
 */

namespace Embitel\Customer\Model;

use Embitel\Customer\Api\CustomerDetailsInterface;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Customer\Model\CustomerFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;

class CustomerDetails implements CustomerDetailsInterface{

    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     * @var groupRepository
     */
    private $groupRepository;
    /**
     * @var AttributeCollection
     */
    private $attributeCollection;

    public function __construct(
        CustomerFactory $customerFactory,
        groupRepository $groupRepository,
        AttributeCollection $attributeCollection,
        \Magento\Eav\Model\Config $eavConfig
    ){
        $this->customerFactory = $customerFactory;
        $this->groupRepository = $groupRepository;
        $this->attributeCollection = $attributeCollection;
        $this->eavConfig = $eavConfig;
    }

    public function getCustomerById(int $customerId)
    {
        $response = [];

        $customer = $this->customerFactory->create()->load($customerId);
        if (!$customer->getId()) {
            $response ['data'] = [
                "satatus" => "failed",
                "message" => "Customer Id not found"
            ];
        } else {
            if($customer->getDataModel()->getGender() == 1) {
                $gender = 'MALE';
            } elseif ($customer->getDataModel()->getGender() == 2) {
                $gender = 'FEMALE';
            } else {
                $gender = 'NOT SPECIFIED';
            }


            if(!empty($customer->getDataModel()->getCustomAttribute('approval_status'))) {
                $approval_status_code = $customer->getDataModel()->getCustomAttribute('approval_status')->getValue();
                $attribute = $this->eavConfig->getAttribute('customer', 'approval_status');
                $status = $attribute->getSource()->getOptionText($approval_status_code);
            } else {
                $status = '';
            }

            if(!empty($customer->getDataModel()->getCustomAttribute('customer_secondary_email'))){
                $email = $customer->getDataModel()->getCustomAttribute('customer_secondary_email')->getValue();
            } else {
                $email = '';
            }

            $response['data']['customer'] = Array(
                    "customer_id" => $customer->getId(),
		            "customer_group" => $this->getGroupName($customer->getGroupId()),
		            "customer_type" => $this->getCustomerAttributeValue($customer->getDataModel(), 'customer_type'),
		            "gender" => $gender,
		            "date_of_birth" => $customer->getDataModel()->getDob() ?? '',
                    "email_id" => $email,
                    "is_b2b_customer" => ($this->getGroupName($customer->getGroupId()) == 'B2B') ? true : false,
		            "status" => $status,
		            "status_reason" => ""
            );

            $response['data']['customer']['customer_name']['salutation'] = $customer->getDataModel()->getPrefix();
            $response['data']['customer']['customer_name']['first_name'] = ($customer->getDataModel()->getFirstname() == '-') ? 'customer' : $customer->getDataModel()->getFirstname();
            $response['data']['customer']['customer_name']['middle_name'] = ($customer->getDataModel()->getMiddlename() == '-') ? 'customer' : $customer->getDataModel()->getMiddlename();
            $response['data']['customer']['customer_name']['last_name'] = ($customer->getDataModel()->getLastname() == '-') ? 'customer' : $customer->getDataModel()->getLastname();
            $response['data']['customer']['customer_name']['suffix'] = $customer->getDataModel()->getSuffix();

            $response['data']['customer']['phone_number']['type'] = "MOBILE";
            $response['data']['customer']['phone_number']['country_code'] = "+91";
            $response['data']['customer']['phone_number']['number'] = !empty($customer->getDataModel()->getCustomAttribute('mobilenumber')->getValue()) ? $customer->getDataModel()->getCustomAttribute('mobilenumber')->getValue() : '';;
            $response['data']['customer']['phone_number']['availability'] = "";
        }

        return $response;
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
