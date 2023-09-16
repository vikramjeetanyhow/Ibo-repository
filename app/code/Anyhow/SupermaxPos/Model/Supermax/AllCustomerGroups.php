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

class AllCustomerGroups implements \Anyhow\SupermaxPos\Api\Supermax\AllCustomerGroupsInterface {
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroup,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
        $this->supermaxUser = $supermaxUser;
        $this->customerGroup = $customerGroup;
    }

    /**
     * GET API
     * @api
     * @return string
     */
 
    public function getAllCustomerGroups() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $storeId = 0;
                $customerGroupsResult = array();
                $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                $storeViewData = $storeView->getData();

                if(!empty($storeViewData)) {
                    $storeId = $storeViewData[0]['store_view_id'];
                }

                $posDefaultCustomerGroup = (int)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_default_customer_group', $storeId);               
                $customerGroups = $this->customerGroup->toOptionArray();

                if(!empty($customerGroups)){
                    foreach($customerGroups as $group){
					    $customerGroupsResult[] = array(
                            'customer_group_id' => $group['value'],
                            'name' => $group['label'],
                            'default' => $group['value'] == $posDefaultCustomerGroup ? true : false
                        ); 
                    }
                }
            
                $result = array(
                    'customer_groups'=> $customerGroupsResult
                );
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}