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

class OverridePermission implements \Anyhow\SupermaxPos\Api\Supermax\OverridePermissionInterface
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
    public function overridePermission()
    {
        $result = array();
        $error = false;
        try 
        {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $username = trim($params['username'] ?? null);
                $password = trim($params['password'] ?? null);

                if (!empty($username) && !empty($password)) {
                    $hashedPassword = $this->encryptor->getHash($password, false);
                    $user = $this->getUserData($username, $hashedPassword);
                    if(!empty($user)){
                        if($user['status'] && $user['role_status']) {
                            $result = array(
                                'user_id' => (int)$user['pos_user_id'], 
                                'user_name' => html_entity_decode($user['userfirstname'].' '.$user['userlastname']),
                                'access_permission' => (!empty($user['access_permission']) && $user['role_status']) ? explode(',' , $user['access_permission']) : array()
                            );
                        } else {
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
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function getUserData($username, $password)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'username', 'pos_outlet_id', 'store_view_id', 'userfirstname'=>'firstname', 'userlastname'=>'lastname', 'status']
        )->joinLeft(
            ['ur' => $this->resource->getTableName('ah_supermax_pos_user_role')],
            "ur.pos_user_role_id = spu.pos_user_role_id",
            ['access_permission', 'role_status'=>'status']
        );
        $select->where("spu.username = '$username'")->where("spu.password = '$password'");
        $userData = $connection->query($select)->fetch();

        return $userData;
    }
}



