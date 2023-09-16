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

class PincodeAddress implements \Anyhow\SupermaxPos\Api\Supermax\PincodeAddressInterface
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
    public function pincodeAddress() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                
                if(isset($params['postcode']) && !empty($params['postcode'])) {
                    $postcode = $params['postcode'];
                    $header = array(
                        "Content-Type: application/json",
                        "x-channel-id: STORE"
                    );
                    $invoiceApiUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_pincode_address_api_url", $storeId = null);
                    $apiResponse = $this->helper->curlGetRequest($invoiceApiUrl . $postcode, $header, true, 'Get Address By Pincode');
    
                    $result = json_decode($apiResponse);
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

    private function getOrderData($orderId) {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderData = $connection->query("SELECT * FROM $orderTable WHERE entity_id='" . (int)$orderId . "'")->fetch();
        return $orderData;
    }

    private function getOrderItemsData($orderId) {
        $connection = $this->resource->getConnection();
        $orderItemsTable = $this->resource->getTableName('sales_order_item');
        $orderItmsData = $connection->query("SELECT * FROM $orderItemsTable WHERE order_id='" . (int)$orderId . "'")->fetchAll();
        return $orderItmsData;
    }

    private function getPaymentInfo($orderId) {
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $ezetapTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $paymentData = $connection->query("SELECT o.*, ez.request_id, ez.status_check_info, ez.date_added as ez_date_added FROM $posOrderTable as o LEFT JOIN $ezetapTable as ez on(o.order_id = ez.order_id) WHERE o.order_id='" . (int)$orderId . "'")->fetch();
        return $paymentData;
    }

    private function updatePincodeAddress($orderId, $paymentIntentId) {
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $connection->query("UPDATE $posOrderTable set payment_intent_id='$paymentIntentId' WHERE order_id=$orderId");
    }
}



