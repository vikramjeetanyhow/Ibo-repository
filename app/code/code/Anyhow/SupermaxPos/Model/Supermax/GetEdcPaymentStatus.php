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

class GetEdcPaymentStatus implements \Anyhow\SupermaxPos\Api\Supermax\GetEdcPaymentStatusInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\App\ResourceConnection $resource
    ){
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
        $this->resource = $resource;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function getEdcPaymentStatus() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $userId = $this->supermaxSession->getPosUserId();
                $ezetapData = $this->helper->getEdcData($userId, $params['terminal_id'], "ezetap");
                $params['username'] =  isset($ezetapData['username']) ? $ezetapData['username'] : "";
                $params['appKey'] = isset($ezetapData['appKey']) ? $ezetapData['appKey'] : "";
                $params['pushTo'] = isset($ezetapData['pushTo']) ? $ezetapData['pushTo'] : "";
                
                $header = array("Content-Type: application/json");
                $url = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_get_ezetap_status_api_url", null);
                $apiResponse = $this->helper->curlPostRequest($url, $params, $header, true, 'Get EDC(Ezetap) Payment Status');
                
                $apiData = json_decode($apiResponse);
                $result = (array)$apiData;
                if(isset($params['origP2pRequestId']) && !empty($params['origP2pRequestId'])) {
                    $apiData->additionalParamJson = ""; 
                    $this->helper->updateEdcInfo($params['origP2pRequestId'], json_encode($apiData), "ezetap");
                    $responseData = (array)$apiData;
                    if(isset($responseData['messageCode']) && ($responseData['messageCode'] == "P2P_DEVICE_TXN_DONE")) {
                       $paymentData = $this->getEzetapPaymentData($responseData);
                       $result['payment_method'] = $paymentData['payment_method'];
                       $result['payment_code'] = $paymentData['payment_code'];
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

    private function getEzetapPaymentData($paymentStausData) {
        $ezetapPaymentData = array(
            "payment_code" => "",
            "payment_method" => ""
        );
        if(isset($paymentStausData['paymentMode']) && !empty($paymentStausData['paymentMode'])) {
            switch($paymentStausData['paymentMode']) {
                case 'CARD':
                    if(isset($paymentStausData['externalRefNumber7']) && ($paymentStausData['externalRefNumber7'] == "NORMAL_EMI")) {
                        $ezetapPaymentData['payment_code'] = "EZETAP-EMI";
                        $ezetapPaymentData['payment_method'] = "EZETAP-EMI";
                    } else {
                        $ezetapPaymentData['payment_code'] = ($paymentStausData['paymentCardType'] == "DEBIT") ? "DEBIT-CARD" : "CREDIT-CARD";
                        $ezetapPaymentData['payment_method'] = ($paymentStausData['paymentCardType'] == "DEBIT") ? "DEBIT-CARD" : "CREDIT-CARD";
                    }
                    break;
                case 'UPI':
                    $ezetapPaymentData['payment_code'] = "UPI"; 
                    $ezetapPaymentData['payment_method'] = "UPI"; 
                    break;
                default:
                    $ezetapPaymentData['payment_code'] = "NET-BANKING"; 
                    $ezetapPaymentData['payment_method'] = "NET-BANKING"; 
            }
        }
        return $ezetapPaymentData;
    }

    private function getEdcData($requestId) {
        $connection =  $this->resource->getConnection();
        $table = $this->resource->getTableName("ah_supermax_pos_payment_ezetap");
        $data = $connection->query("SELECT * FROM $table WHERE request_id = '$requestId'")->fetch();
        if(!empty($data)) {
            return $data['status_check_info'];
        }

        return array();
    }
}



