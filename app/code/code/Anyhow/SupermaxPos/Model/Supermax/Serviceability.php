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

class Serviceability implements \Anyhow\SupermaxPos\Api\Supermax\ServiceabilityInterface
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
    public function serviceability() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $clientId = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_client_id", $storeId = null);
                $header = array(
                    "Content-Type: application/json",
                    "client_id:" . $clientId,
                    "trace_id:" . $clientId
                );
                
                $baseUrl = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_host_url", $storeId = null);
                $checkserviceabilityUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_check_serviceability_api_url", $storeId = null);
                
                $apiResponse = $this->helper->curlGetRequest($checkserviceabilityUrl . '?post-code='. $params['postcode'], $header, true, 'Serviceability');
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



