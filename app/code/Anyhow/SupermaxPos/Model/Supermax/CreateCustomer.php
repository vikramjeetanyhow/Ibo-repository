<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class CreateCustomer implements \Anyhow\SupermaxPos\Api\Supermax\CreateCustomerInterface
{
    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenFactory
    ){
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxUser = $supermaxUser;
        $this->groupRepository = $groupRepository;
        $this->supermaxSession = $supermaxSession;
        $this->timezone = $timezone;
        $this->tokenFactory = $tokenFactory;
    }

     /**
     * GET API
     * @api
     * 
     * @return string
     */
 
    public function createCustomer()
    {
        $result = array();
        $error = false;
        $errorCode = '';
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();

                if(!empty($params)){
                    $firstname = trim($params['firstname'] ?? null);
                    $lastname = trim($params['lastname'] ?? null);
                    $email = trim($params['email'] ?? null);
                    $customer_group = trim($params['group_id'] ?? null);
                    $phone = trim($params['phone'] ?? null);
                    if(!empty($firstname) && !empty($phone)){
                        $connection = $this->resource->getConnection();   
                        $customerTable = $this->resource->getTableName('customer_entity');
                        $supermaxCustomerTable = $this->resource->getTableName('ah_supermax_pos_customer');
                        $customerData  = $connection->query("SELECT * FROM $customerTable WHERE mobilenumber = $phone")->fetch();

                        if(empty($customerData)){
                            $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                            $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                            $storeViewData = $storeView->getData();
                            $storeViewId = 1;
                            $outletId = 0;
                            if(!empty($storeViewData)) {
                                $storeViewId = $storeViewData[0]['store_view_id'];
                                $outletId = $storeViewData[0]['pos_outlet_id'];
                            }

                            $customer = $this->customerFactory->create();
                            $customer->setStoreId($storeViewId);
                            $customer->setFirstname($firstname);
                            $customer->setLastname($lastname);
                            
                            $customer->setEmail($email);

                            $customerTypeOptionValue = $this->helper->getCustomerAttributeValue(['label' => 'Individual', 'key' => 'customer_type']);
                            $customer->setCustomAttribute('customer_type', $customerTypeOptionValue);

                            $customerApprovalStatus = $this->helper->getCustomerAttributeValue(['label' => 'pending', 'key' => 'approval_status']);
                            $customer->setCustomAttribute('approval_status',$customerApprovalStatus);

                            $customer->setCustomAttribute('mobilenumber', $phone);

                            if(!empty($customer_group)){
                                $customer->setGroupId($customer_group);
                            } else {
                                $customer->setGroupId((int)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_default_customer_group', $storeViewId));
                            }

                            $customer = $this->customerRepository->save($customer);

                            $customerId = $customer->getId();
                            $connection->insert($supermaxCustomerTable,
                            ['customer_id' => (int)$customerId, 'pos_user_id' => (int)$supermaxPosUserId, 'pos_outlet_id' => (int)$outletId]); 

                            if(!empty($customer)) {
                                $customer_token = $this->tokenFactory->create()->createCustomerToken($customerId)->getToken();
                                $result = array(
                                    'customer_id' => (int)$customer->getId(),
                                    'firstname' => html_entity_decode($customer->getFirstname()),
                                    'lastname' => html_entity_decode($customer->getLastname()),
                                    'email' => html_entity_decode($customer->getEmail()),
                                    'phone' => html_entity_decode($customer->getCustomAttribute('mobilenumber')->getValue()),
                                    'taxvat' => html_entity_decode($customer->getTaxvat()),
                                    'account_created_in' => html_entity_decode($customer->getCreatedIn()),
                                    'customer_since' => html_entity_decode( $this->timezone->date(new \DateTime(
                                        $customer->getCreatedAt()))->format('Y-m-d h:i:s A')),
                                    'customer_type' => html_entity_decode($this->helper->getCustomerAttribute($connection, $customer, 'customer_type')),
                                    'group_id' => (int)$customer->getGroupId(),
                                    'tax_class_id' => (int)$this->customerTaxId((int)$customer->getGroupId()),
                                    'default_billing' => (int)$customer->getDefaultBilling(),
                                    'default_shipping' => (int)$customer->getDefaultShipping(),
                                    'customer_token' => $customer_token,
                                    'business_activities' => ""
                                    // html_entity_decode($this->helper->getCustomerAttribute($connection, $customer, 'business_activities'))
                                );
                            }
                        } else {
                            $errorCode = "AlreadyRegistered";
                            $error = true;
                        }
                    } else {
                        $error = true;
                    }
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result, 'errorCode' => $errorCode);
        return json_encode($data);
    }

    public function customerTaxId($groupId){
        $taxClassId = '';
        $group = $this->groupRepository->getById($groupId);
        if(!empty($group)){
            $taxClassId = $group->getTaxClassId();
        }
        return $taxClassId;
    }
}