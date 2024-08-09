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

class StartEdcPayment implements \Anyhow\SupermaxPos\Api\Supermax\StartEdcPaymentInterface
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
    public function startEdcPayment() {
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
                if(isset($params['is_emi']) && $params['is_emi']) {
                    $params['paymentMode'] = 'CARD';
                    $params['emiType'] = 'EMI';
                }
                $url = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_start_ezetap_api_url", null);
                $header = array("Content-Type: application/json");
                $apiResponse = $this->helper->curlPostRequest($url, $params, $header, true, 'Start EDC(Ezetap) Payment');
                $result = json_decode($apiResponse);
    
                if(isset($result->p2pRequestId) && !empty($result->p2pRequestId)) {
                    $result->additionalParamJson = ""; 
                   $this->helper->updateEdcInfo($result->p2pRequestId, json_encode($result), "ezetap");
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



