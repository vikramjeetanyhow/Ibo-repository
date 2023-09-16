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

class CancelPinelabsPayment implements \Anyhow\SupermaxPos\Api\Supermax\CancelPinelabsPaymentInterface
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
    public function cancelPinelabsPayment() {
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
                $params['MerchantStorePosCode'] = isset($ezetapData['MerchantStorePosCode']) ? $ezetapData['MerchantStorePosCode'] : "";
                $params['MerchantID'] = isset($ezetapData['MerchantID']) ? $ezetapData['MerchantID'] : 0;
                $params['SecurityToken'] = isset($ezetapData['SecurityToken']) ? $ezetapData['SecurityToken'] : "";
                $params['IMEI'] = isset($ezetapData['IMEI']) ? $ezetapData['IMEI'] : "";
                
                $header = array("Content-Type: application/json");
                $url = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_cancel_pinelabs_api_url", null);
                $apiResponse = $this->helper->curlPostRequest($url, $params, $header, true, 'Cancel EDC(Pinelabs) Payment');
                $result = (array)json_decode($apiResponse);

                if(isset($params['PlutusTransactionReferenceID']) && !empty($params['PlutusTransactionReferenceID'])) {
                    $this->helper->updateEdcInfo($params['PlutusTransactionReferenceID'], json_encode($result), "pinelabs", true);
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



