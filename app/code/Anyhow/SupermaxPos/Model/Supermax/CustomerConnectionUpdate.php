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

class CustomerConnectionUpdate implements \Anyhow\SupermaxPos\Api\Supermax\CustomerConnectionUpdateInterface
{
    protected $helper;
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper, 
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper; 
        $this->supermaxSession = $supermaxSession;
    }
    
    /**
     * GET API
     * @api
     * @return string
     */
    public function customerConnectionUpdate()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $result['customer_ids'] = array();
                $connectionId = $this->supermaxSession->getPosConnectionId();
                $code = 'customer';
                $updates = $this->helper->checkConnectionUpdate($connectionId, $code);

                foreach ($updates as $key => $update) {
                    if ($update && !is_null($update['update'])) {
                        if (!in_array($update['update'], $result['customer_ids'])) {
                            array_push($result['customer_ids'], (int)$update['update']);
                        }
                        $this->helper->deleteConnectionUpdate($connectionId, $code, $update['pos_connection_update_id']);
                    }
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
}