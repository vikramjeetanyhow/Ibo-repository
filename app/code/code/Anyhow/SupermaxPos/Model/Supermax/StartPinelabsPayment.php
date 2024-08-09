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

class StartPinelabsPayment implements \Anyhow\SupermaxPos\Api\Supermax\StartPinelabsPaymentInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function startPinelabsPayment() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $userId = $this->supermaxSession->getPosUserId();
                $ezetapData = $this->helper->getEdcData($userId, $params['terminal_id'], "pinelabs");
                $params['UserID'] = $userId;
                $params['AllowedPaymentMode'] =  isset($ezetapData['AllowedPaymentMode']) ? $ezetapData['AllowedPaymentMode'] : "";
                if(isset($params['is_emi']) && $params['is_emi']) {
                    $params['AllowedPaymentMode'] = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_pinelabs_emi_code", null);
                }
                $params['MerchantStorePosCode'] = isset($ezetapData['MerchantStorePosCode']) ? $ezetapData['MerchantStorePosCode'] : "";
                $params['MerchantID'] = isset($ezetapData['MerchantID']) ? $ezetapData['MerchantID'] : 0;
                $params['SecurityToken'] = isset($ezetapData['SecurityToken']) ? $ezetapData['SecurityToken'] : "";
                $params['IMEI'] = isset($ezetapData['IMEI']) ? $ezetapData['IMEI'] : "";
                $params['AutoCancelDurationInMinutes'] = isset($ezetapData['AutoCancelDurationInMinutes']) ? $ezetapData['AutoCancelDurationInMinutes'] : "";

                $header = array("Content-Type: application/json");
                $url = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_start_pinelabs_api_url", null);
                $apiResponse = $this->helper->curlPostRequest($url, $params, $header, true, 'Start EDC(Pinelabs) Payment');
                $result = (array)json_decode($apiResponse);
                // $result = $this->getData();
                if(isset($result['PlutusTransactionReferenceID']) && ($result['PlutusTransactionReferenceID'] > 0)) {
                   $this->helper->updateEdcInfo($result['PlutusTransactionReferenceID'], json_encode($result), "pinelabs");
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

    // private function getData() {
    //     return array (
    //         'ResponseCode' => 0,
    //         'ResponseMessage' => 'APPROVED',
    //         'PlutusTransactionReferenceID' => 652557,
    //     );
    // }
}



