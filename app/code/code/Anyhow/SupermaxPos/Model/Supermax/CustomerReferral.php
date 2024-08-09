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

class CustomerReferral 
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
        $this->supermaxUser = $supermaxUser;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET API
     * @api
     * @param int $page
     * @return string
     */
 
    public function getcustomerreferral()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();
            if($tokenFlag) {
                $connection= $this->resource->getConnection();
                $referralTable = $this->resource->getTableName("ah_supermax_pos_customer_referral");
                $result = $connection->query("SELECT pos_referral_id, referral_title FROM $referralTable WHERE status=1")->fetchAll();
                
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    public function addcustomerreferral()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();
            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)) {
                    $posUserId = $this->supermaxSession->getPosUserId();
                    $title = isset($params['title']) ? $params['title'] : '';
                    $phone = isset($params['phone']) ? $params['phone'] : '';
                    $customer_id = $params['customer_id'];
                    $posUser = $this->supermaxUser->addFieldToFilter('pos_user_id', $posUserId);
                    $posUserData = $posUser->getData();
                    $posOutletId = !empty($posUserData) ? $posUserData[0]['pos_outlet_id'] : 0;
                    $connection= $this->resource->getConnection();
                    $referralTable = $this->resource->getTableName("ah_supermax_pos_customer");
                    $posCustomerData = $connection->query("SELECT * FROM $referralTable WHERE `customer_id` = $customer_id")->fetch();
                    if(!empty($posCustomerData)) {
                        $connection->query("UPDATE $referralTable SET `customer_referral_title` = '".$title ."', `customer_referral_Phone` = '". $phone ."' WHERE `customer_id` = $customer_id");
                    } else {
                        $connection->query("INSERT INTO $referralTable SET `pos_user_id`= $posUserId, `pos_outlet_id`= $posOutletId, `customer_id` = $customer_id, `customer_referral_title` = '".$title ."', `customer_referral_Phone` = '". $phone ."'");
                    }
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error);
        return json_encode($data);
    }
}