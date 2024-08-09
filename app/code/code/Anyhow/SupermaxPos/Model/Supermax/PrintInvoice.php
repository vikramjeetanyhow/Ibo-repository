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

class PrintInvoice implements \Anyhow\SupermaxPos\Api\Supermax\PrintInvoiceInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\ResourceConnection $resource
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function printInvoice() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                
                if(isset($params['orderId']) && !empty($params['orderId'])) {
                    $orderId = $params['orderId'];
                    $clientId = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_client_id", $storeId = null);
                    $header = array(
                        "Content-Type: application/json",
                        "client_id:" . $clientId,
                        "trace_id:" . $clientId
                    );
                    $invoiceApiUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_invoice_api_url", $storeId = null);
                    $apiResponse = $this->helper->curlGetRequest($invoiceApiUrl . "?order=" . $orderId, $header, true, 'Print Invoice');
    
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

    private function updatePrintInvoice($orderId, $paymentIntentId) {
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $connection->query("UPDATE $posOrderTable set payment_intent_id='$paymentIntentId' WHERE order_id=$orderId");
    }
}



