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

class AllCustomers implements \Anyhow\SupermaxPos\Api\Supermax\AllCustomersInterface
{
    public function __construct(
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenFactory
    ){
        $this->customerFactory = $customerFactory;
        $this->helper = $helper;
        $this->supermaxUser = $supermaxUser;
        $this->groupRepository = $groupRepository;
        $this->supermaxSession = $supermaxSession;
        $this->customerRepository = $customerRepository;
        $this->resourceConnection = $resourceConnection;
        $this->timezone = $timezone;
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * GET API
     * @api
     * @param int $page
     * @return string
     */
 
    public function getAllCustomers($page)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $customerDatas = array();
                $totalCustomers = 0;
                if(isset($params['phone']) && !empty($params['phone'])) {
                    $connection = $this->resourceConnection->getConnection();
                    $customerPhone = $params['phone'];
                    $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                    $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                    $storeViewData = $storeView->getData();

                    if(!empty($storeViewData)) {
                        $storeViewId = $storeViewData[0]['store_view_id'];
                    
                        $customerCollection = $this->customerFactory->create()
                            ->getCollection()
                            ->addAttributeToSelect('*')
                            ->addAttributeToFilter("mobilenumber", array("eq" => $customerPhone));
                        $customers = $customerCollection->load();
                        
                        foreach ($customers as $customer) {
                            $customerId = $customer->getId();
                            $customerDetail = $this->customerRepository->getById($customerId);
                            $enablePayLater = $customer->getEnablePaylaterPayment(); 
                            $payLaterLimit = $customer->getPayLaterLimit();
                            $customer_token = $this->tokenFactory->create()->createCustomerToken($customerId)->getToken();
                            $customerEntityType = $this->helper->getCustomerAttribute($connection, $customerDetail, 'entity_type');
                            $customerDatas[] = array(
                                'customer_id' => (int)$customerId,
                                'firstname' => html_entity_decode($customer->getFirstname()),
                                'lastname' => html_entity_decode($customer->getLastname()),
                                'email' => html_entity_decode($customer->getEmail()),
                                'phone' => html_entity_decode($customerPhone),
                                'taxvat' => html_entity_decode($customer->getTaxvat()),
                                'account_created_in' => html_entity_decode($customer->getCreatedIn()),
                                'customer_since' => html_entity_decode( $this->timezone->date(new \DateTime(
                                    $customer->getCreatedAt()))->format('Y-m-d h:i:s A')),
                                'customer_type' => html_entity_decode($this->helper->getCustomerAttribute($connection, $customerDetail, 'customer_type')),
                                'group_id' => (int)$customer->getGroupId(),
                                'tax_class_id' => (int)$this->customerTaxId((int)$customer->getGroupId()),
                                'default_billing' => (int)$customer->getDefaultBilling(),
                                'default_shipping' => (int)$customer->getDefaultShipping(),
                                'virtual_account_bank' => $customer->getVirtualAccountBank(),
                                'virtual_account_ifsc' => $customer->getVirtualAccountIfsc(),
                                'virtual_account_no' => $customer->getVirtualCustomerAccount(),
                                'customer_token' => $customer_token,
                                'business_activities' => "",
                                // html_entity_decode($this->helper->getCustomerAttribute($connection, $customerDetail, 'business_activities'))
                                'enable_paylater_payment' => $enablePayLater,
                                'loan_partner' => html_entity_decode($this->helper->getCustomerAttribute($connection, $customerDetail, 'loan_partner')),
                                'pay_later_limit' => $payLaterLimit,
                                'entity_type' => !empty($customerEntityType) ? $customerEntityType : "REGULAR"
                            );
                           
                        }

                        $totalCustomers = count($customerDatas);
                    }
                }

                $result = array(
                    'customers'=> $customerDatas,
                    'total_customers' => $totalCustomers
                );
                // check connection update data.
                // $connectionId = $this->supermaxSession->getPosConnectionId();
                // $code = 'customer';
                // $conncetionUpdateData = $this->helper->checkConnectionUpdate($connectionId, $code);
                // $customerDatas = array();
                // $totalCustomers = 0;
                // $params = $this->helper->getParams();
                // $customerIds = array();
                // if(!empty($params)){
                //     $customerIds = $params['customers'];
                // }
                
                // if($customerIds || ($conncetionUpdateData && is_null($conncetionUpdateData[0]['update']))){
                //     $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                //     $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                //     $storeViewData = $storeView->getData();

                //     if(!empty($storeViewData)) {
                //         $storeViewId = $storeViewData[0]['store_view_id'];
                //         if(empty($page) || $page == 1 || $page == 0) {
                //             $page = 1;
                //         }
                        
                //         $customerData = array();
                //         if(!empty($customerIds)){
                //             $totalCustomerCollection = $this->customerFactory->create()
                //                                     ->getCollection()
                //                                     ->addAttributeToSelect('*')
                //                                     ->addFieldToFilter('entity_id', ['in' => $customerIds])
                //                                     ->addAttributeToFilter("store_id", array("eq" => $storeViewId))
                //                                     ->load();
                //             $totalCustomers = count($totalCustomerCollection->getData());
                            
                //             $customerCollection = $this->customerFactory->create()
                //                                 ->getCollection()
                //                                 ->addAttributeToSelect('*')
                //                                 ->addFieldToFilter('entity_id', ['in' => $customerIds])
                //                                 ->addAttributeToFilter("store_id", array("eq" => $storeViewId))
                //                                 ->setPageSize(500)    
                //                                 ->setCurPage($page)
                //                                 ->load();
                //         } else {
                //             $totalCustomerCollection = $this->customerFactory->create()
                //                                     ->getCollection()
                //                                     ->addAttributeToSelect('entity_id')
                //                                     ->addAttributeToFilter("store_id", array("eq" => $storeViewId))
                //                                     ->load();
                //             $totalCustomers = count($totalCustomerCollection->getData());
                            
                //             $customerCollection = $this->customerFactory->create()
                //                                 ->getCollection()
                //                                 ->addAttributeToSelect('entity_id')
                //                                 ->addAttributeToFilter("store_id", array("eq" => $storeViewId))
                //                                 ->setPageSize(500)    
                //                                 ->setCurPage($page)
                //                                 ->load();
                //         }
                //         $connection = $this->resourceConnection->getConnection();

                //         if(!empty($customerCollection)) {
                //             foreach ($customerCollection as $customer) {
                //                 $customerDetail = $this->customerRepository->getById($customer['entity_id']);
                //                 $customerPhone = $customerDetail->getCustomAttribute('mobilenumber')->getValue();
                //                 $customerDatas[] = array(
                //                     'customer_id' => (int)$customer->getId(),
                //                     'firstname' => html_entity_decode($customer->getFirstname()),
                //                     'lastname' => html_entity_decode($customer->getLastname()),
                //                     'email' => html_entity_decode($customer->getEmail()),
                //                     'phone' => html_entity_decode($customerPhone),
                //                     'taxvat' => html_entity_decode($customer->getTaxvat()),
                //                     'account_created_in' => html_entity_decode($customer->getCreatedIn()),
                //                     'customer_since' => html_entity_decode( $this->timezone->date(new \DateTime(
                //                         $customer->getCreatedAt()))->format('Y-m-d h:i:s A')),
                //                     'customer_type' => html_entity_decode($this->helper->getCustomerAttribute($connection, $customerDetail, 'customer_type')),
                //                     'group_id' => (int)$customer->getGroupId(),
                //                     'tax_class_id' => (int)$this->customerTaxId((int)$customer->getGroupId()),
                //                     'default_billing' => (int)$customer->getDefaultBilling(),
                //                     'default_shipping' => (int)$customer->getDefaultShipping()
                //                 );
                //             }
                //         }
                //     }
                // }
                // $result = array(
                //     'customers'=> $customerDatas,
                //     'total_customers' => $totalCustomers
                // );

            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    // to get customer tax class.
    public function customerTaxId($groupId){
        $taxClassId = '';
        $group = $this->groupRepository->getById($groupId);
        if(!empty($group)){
            $taxClassId = $group->getTaxClassId();
        }
        return $taxClassId;
    }
}