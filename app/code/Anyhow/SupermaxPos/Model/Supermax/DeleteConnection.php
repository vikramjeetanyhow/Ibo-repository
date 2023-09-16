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

class DeleteConnection implements \Anyhow\SupermaxPos\Api\Supermax\DeleteConnectionInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET for Post api
     * @api
     * @param string $code
     * @return string
     */
    public function deleteConnection($code)
    {
        $result = array();
        $error = false;
        try {
            // check pos status
            $posStatus = (bool)$this->helper->getPosStatus();
            if($posStatus){
                $tokenFlag = $this->helper->userAutherization();
                $this->helper->setHeaders();

                if($tokenFlag) {
                    if(!empty($code)){
                        $connectionId = $this->supermaxSession->getPosConnectionId();
                        $update = null; 
                        $delete = $this->helper->deleteConnectionUpdate($connectionId, $code, $update);
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
}



