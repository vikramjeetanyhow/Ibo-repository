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

class Login implements \Anyhow\SupermaxPos\Api\Supermax\LoginInterface
{
    protected $model;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\Collection $supermaxOutlet,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosApi\Collection $supermaxApi,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ){
        $this->resource = $resourceConnection;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->supermaxApi = $supermaxApi;
        $this->supermaxOutlet = $supermaxOutlet;
        $this->supermaxSession = $supermaxSession;
        $this->encryptor = $encryptor;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function login()
    {
        $result = array();
        $error = false;
        $message = "";
        try {
           // check pos status
           $posStatus = (bool)$this->helper->getPosStatus();
            if($posStatus) {
                $storeName = $storeCurrencyCode = $storeLanguageCode = $outletName = $outletStoreId = '';
                $params = $this->helper->getParams();
                $username = trim($params['username'] ?? null);
                $password = trim($params['password'] ?? null);
                $terminal_id = trim($params['terminal_id'] ?? null);
                $store_id = trim($params['store_id'] ?? null);

                $connection= $this->resource->getConnection();
                $supermaxApiTable = $this->resource->getTableName('ah_supermax_pos_api');

                if (!empty($username) && !empty($password) && !empty($terminal_id) && !empty($store_id)) {
                    $hashedPassword = $this->encryptor->getHash($password, false);
                    $userDataCollection = $this->supermaxUserCollection
                                                ->addFieldToFilter('username', $username)
                                                ->addFieldToFilter('password', $hashedPassword);
                    $userData = $userDataCollection->getData();

                    if(!empty($userData)) {
                        $isAccessGranted = $this->userAccess($userData[0]['pos_user_role_id']);
                        if($isAccessGranted) {
                            if($userData[0]['pos_outlet_id'] == $store_id) {
                                $supermaxUserId = $userData[0]['pos_user_id'];
                                $userOutletId = $userData[0]['pos_outlet_id'];
                                $userAndOutletStatus = $this->helper->joinUserAndOutletData($supermaxUserId);
                                $userStatus = (bool)$userAndOutletStatus['user_status'];
                                $outletStatus = (bool)$userAndOutletStatus['outlet_status'];
                                $currentIp = $this->remoteAddress->getRemoteAddress();
                                $allowedIps = preg_split('(,|;|/)', $userAndOutletStatus['allowed_ips']);
                                // Check User and Outlet status
        
                                // && in_array($currentIp, $allowedIps) commented this code for not to check ip
                                if($userStatus && $outletStatus) {
                                    $checkUserLogin = $this->updateUserLoginHistory($supermaxUserId, $terminal_id);
                                    if(empty($checkUserLogin)) {
                                        $outletCollection = $this->supermaxOutlet->addFieldToFilter('pos_outlet_id', $userOutletId);
                                        $outletData = $outletCollection->getData();
        
                                        if(!empty($outletData)) {
                                            $outletName = html_entity_decode($outletData[0]['outlet_name']);
                                            $outletStoreId  = html_entity_decode($outletData[0]['store_id']);
                                        }
                                        $userStoreViewId = $userData[0]['store_view_id'];
        
                                        if(!empty($userStoreViewId)) {
                                            $storeData = $this->storeManager->getStore($userStoreViewId);
                                            if(!empty($storeData)) {
                                                $storeName = html_entity_decode($storeData->getName());
                                                $storeCurrencyCode = html_entity_decode($storeData->getCurrentCurrencyCode());
                                            }
                                            $storeLanguageCode = html_entity_decode($this->helper->getConfig('general/locale/code', $userStoreViewId));
                                        }
                                
                                        $posApiCollection = $this->supermaxApi->addFieldToFilter('pos_user_id', $supermaxUserId);
                                        $posApiData = $posApiCollection->getData();
                                        $expire = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). ' + 1 days'));
        
                                        if(!empty($posApiData)) {
                                            $token = $posApiData[0]['token'];
                                            $where = $connection->quoteInto('pos_user_id = ?', $supermaxUserId);
                                            $query = $connection->update($supermaxApiTable,
                                                [ 'expire'=> $expire], $where );
        
                                        } else {
                                            $token = base64_encode(round(microtime(true) * 1000) . "token");
                                            $query = $connection->insert($supermaxApiTable,
                                                ['pos_user_id' => $supermaxUserId, 'token'=> $token, 'expire'=> $expire]);
                                        }
                                
                                        $this->supermaxSession->setPosUserId($supermaxUserId);
                                
                                        $result = array(  
                                            'user' => array(
                                                'user_id' => (int)$supermaxUserId, 
                                                'user_name' => html_entity_decode($username),
                                                'employee_id' => $userData[0]['username'] ? $userData[0]['username'] : '',
                                                'password_reset_date' => $userData[0]['password_reset_date'] ? $userData[0]['password_reset_date'] : '' ,
                                            ), 
                                            'outlet' => array(
                                                'outlet_id' => (int)$userOutletId, 
                                                'outlet_store_id' => $outletStoreId,
                                                'outlet_name' => $outletName
                                            ), 
                                            'store_view' => array(
                                                'store_view_id' => (int)$userStoreViewId, 
                                                'language_code' => $storeLanguageCode,
                                                'currency_code' => $storeCurrencyCode,
                                                'store_view_title' => $storeName
                                            ),  
                                            'token'=> $token,
                                            "user_login_time" => date('Y-m-d H:i:s'),
                                            'ip' => html_entity_decode($currentIp),
                                            'terminal_id' => $terminal_id,
                                            'store_id' => $store_id,
                                            "edc_type" => $this->getEdcType($terminal_id),
                                            "receipt_thermal_status" => $this->getThermalStatus($terminal_id)
                                        );
                                    } else {
                                        $error = true;
                                        $message = $checkUserLogin;
                                    }
                                } else {
                                    $error = true;
                                }
                            } else {
                                $error = true;
                                $message = "You have selected wrong store. Please select the correct store.";
                            }  
                        } else {
                            $error = true;
                            $message = "You don't have the access. Please contact to the administaror.";
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
        $data = array('error' => (bool)$error, 'result' => $result, 'message' => $message);
    	return json_encode($data);
    }

    private function updateUserLoginHistory($user_id, $terminal_id) {
        $msg = "";
        $connection = $this->resource->getConnection();
        $userHistoryTable = $this->resource->getTableName("ah_supermax_pos_user_login_history");
        $assignedTerminalData = $connection->query("SELECT * FROM $userHistoryTable WHERE pos_terminal_id = $terminal_id And status = 1 AND pos_user_id != $user_id")->fetchAll();

        if(empty($assignedTerminalData)) {
            $userData = $connection->query("SELECT * FROM $userHistoryTable WHERE pos_user_id = $user_id And status = 1")->fetch();
            if(empty($userData)) {
                $connection->query("INSERT INTO $userHistoryTable SET pos_user_id = $user_id, pos_terminal_id = $terminal_id,  status = 1, login_time = NOW(), logout_time = null, is_forced = null");
            } else {
                $msg = "You are already logged in other terminal. Please logout from there first.";
            }
        } else {
            $msg = "Selected terminal is not available. Please try again with other terminal.";
        }

        return $msg;
    }

    private function getEdcType($terminal_id) {
        $edc_type = "ezetap";
        $connection = $this->resource->getConnection();
        $terminalTable = $this->resource->getTableName("ah_supermax_pos_terminals");
        $userData = $connection->query("SELECT * FROM $terminalTable WHERE pos_terminal_id = $terminal_id")->fetch();
        if(!empty($userData)) {
            $edc_type = $userData['edc_type'];
        }

        return $edc_type;
    }

    private function getThermalStatus($terminal_id) {
        $thermal_status = 0;
        $connection = $this->resource->getConnection();
        $terminalTable = $this->resource->getTableName("ah_supermax_pos_terminals");
        $userData = $connection->query("SELECT * FROM $terminalTable WHERE pos_terminal_id = $terminal_id")->fetch();
        if(!empty($userData)) {
            $thermal_status = $userData['receipt_thermal_status'];
        }
        return $thermal_status;
    }

    private function userAccess($userRoleId) {
        $isAccessGranted = false;
        $connection = $this->resource->getConnection();
        $posUserRoleTable = $this->resource->getTableName("ah_supermax_pos_user_role");
        $userRoleData = $connection->query("SELECT * FROM $posUserRoleTable WHERE pos_user_role_id = $userRoleId")->fetch();
        if(isset($userRoleData['access_permission']) && !empty($userRoleData['access_permission'])) {
            $permissions = explode(",", $userRoleData['access_permission']);
            if(!in_array("no_access", $permissions)){
                $isAccessGranted = true;
            }
        }
        return $isAccessGranted;
    }
}



