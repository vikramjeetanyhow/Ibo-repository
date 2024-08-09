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

class ResetUserPassword implements \Anyhow\SupermaxPos\Api\Supermax\ResetUserPasswordInterface
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
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ){
        $this->resource = $resourceConnection;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->supermaxApi = $supermaxApi;
        $this->supermaxOutlet = $supermaxOutlet;
        $this->supermaxSession = $supermaxSession;
        $this->encryptor = $encryptor;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function resetUserPassword()
    {
        $result = array();
        $error = false;
        try 
        {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $userId = $this->supermaxSession->getPosUserId();
                $params = $this->helper->getParams();
                $old_password = trim($params['old_password'] ?? null);
                $new_password = trim($params['new_password'] ?? null);

                if (!empty($userId) && !empty($old_password) && !empty($new_password)) {
                    $user = $this->getUserData($userId, $this->encryptor->getHash($old_password, false));

                    if(isset($user['status']) &&  $user['status']){
                        $this->resetPassword($userId, $this->encryptor->getHash($new_password, false));
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
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function getUserData($userId, $password) {
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')]
        );
        $select->where("spu.pos_user_id = $userId")->where("spu.password = '$password'");
        $userData = $connection->query($select)->fetch();

        return $userData;
    }

    public function resetPassword($userId, $password){
        $connection = $this->resource->getConnection();
        $userTable = $this->resource->getTableName('ah_supermax_pos_user');
        $date = date("Y-m-d H:i:s");
        $connection->query("UPDATE $userTable SET password = '" . $password . "', password_reset_date = '" . $date . "' WHERE pos_user_id = '" . (int)$userId. "'");
    }
}



