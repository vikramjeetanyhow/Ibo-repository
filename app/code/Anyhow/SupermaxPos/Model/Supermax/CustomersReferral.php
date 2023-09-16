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

class CustomersReferral 
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper
        
    ){
        $this->helper = $helper;
        
    }

    /**
     * GET API
     * @api
     * @param int $page
     * @return string
     */
 
    public function getCustomersReferral()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();
            if($tokenFlag) {
                $params = $this->helper->getParams();
                $this->helper->setHeaders();
                $connection= $this->resource->getConnection();
                $referralTable = $this->resource->getTableName("ah_supermax_pos_customer_referral");
                $result = $connection->query("SELECT pos_referral_id, referral_title, referral_code FROM $referralTable WHERE status=1")->fetchAll();
                
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }
}