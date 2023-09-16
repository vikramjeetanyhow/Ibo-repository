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

class GenerateOtp implements \Anyhow\SupermaxPos\Api\Supermax\GenerateOtpInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resource
    ){
        $this->helper = $helper;
        $this->resource = $resource;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function generateOtp() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $header = array(
                    "Content-Type: application/json",
                    "x-channel-id: STORE"
                );
                $generateOtpUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_generate_otp_api_url", $storeId = null);
              
                $apiResponse = $this->helper->curlPostRequest($generateOtpUrl, $params, $header, true, 'Generate OTP');

                $result = json_decode($apiResponse);
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



