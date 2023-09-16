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

class Logout implements \Anyhow\SupermaxPos\Api\Supermax\LogoutInterface
{
    protected $model;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->resource = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function logout()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
               $this->updateUserLoginHistory($params['user_id'] , $params['terminal_id']);
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    private function updateUserLoginHistory($user_id, $terminal_id) {
        $connection = $this->resource->getConnection();
        $userHistoryTable = $this->resource->getTableName("ah_supermax_pos_user_login_history");
        $assignedTerminalData = $connection->query("SELECT * FROM $userHistoryTable WHERE pos_user_id = $user_id AND pos_terminal_id = $terminal_id And status = 1")->fetch();
        if(!empty($assignedTerminalData)) {
            $connection->query("UPDATE $userHistoryTable SET status = 0, logout_time = NOW(), is_forced=0 WHERE pos_user_id = $user_id AND pos_terminal_id = $terminal_id And status = 1");
        }
    }
}



