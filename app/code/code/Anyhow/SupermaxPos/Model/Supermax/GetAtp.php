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

class GetAtp implements \Anyhow\SupermaxPos\Api\Supermax\GetAtpInterface
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
    public function getAtp() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $data = array();
                $data['aggregate'] = false;
                $data['nodes'] = [$params['node_id']];
                $data['offers'] = [
                    array(
                    "offer_id" => $params['offer_id'],
                    "is_bom" => $params['is_bom']
                    )
                ];
                $clientId = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_client_id", $storeId = null);
                $header = array(
                    "Content-Type: application/json",
                    "client_id:" . $clientId,
                    "trace_id:" . $clientId
                );
                
                $getAtpUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_getatp_api_url", $storeId = null);

                $apiResponse = $this->helper->curlPostRequest($getAtpUrl, $data, $header, true, "Get ATP");
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



