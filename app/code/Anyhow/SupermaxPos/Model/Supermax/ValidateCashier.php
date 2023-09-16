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

class ValidateCashier implements \Anyhow\SupermaxPos\Api\Supermax\ValidateCashierInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->helper = $helper;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function validateCashier() {
        $result = array(
            'ResponseCode' => 0,
            'ResponseMessage' => 'Cashier Token is Invalid',
        );
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 
            if($tokenFlag) {
                $result = array(
                    'ResponseCode' => 1,
                    'ResponseMessage' => 'Cashier Token is Valid'
                );
            }
        } catch (\Exception $e) {
            $result = array(
                'ResponseCode' => $e->getCode(),
                'ResponseMessage' => $e->getMessage()
            );
            $this->helper->addDebuggingLogData("---- Validate Cashier Catch Error : " . $e->getCode() . " : " . $e->getMessage());
        }
    	return json_encode($result);
    }
}



