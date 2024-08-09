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

class ProductConnectionUpdate implements \Anyhow\SupermaxPos\Api\Supermax\ProductConnectionUpdateInterface
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
    public function productConnectionUpdate()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) { 
                $result['product_ids'] = array();
                $connectionId = $this->supermaxSession->getPosConnectionId();
                $code = 'product';
                $updates = $this->helper->checkConnectionUpdate($connectionId, $code);

                foreach ($updates as $key => $update) {
                    if ($update && !is_null($update['update'])) {
                        if (!in_array($update['update'], $result['product_ids'])) {
                            array_push($result['product_ids'], (int)$update['update']);
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