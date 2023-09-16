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

class PaymentIntentBk implements \Anyhow\SupermaxPos\Api\Supermax\PaymentIntentBkInterface
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
    public function paymentIntent() {
        $result = array();
        $error = false;
        $tokenFlag = $this->helper->userAutherization();
        $accessToken = $this->helper->getCashierToken();
        $this->helper->setHeaders(); 
        if($tokenFlag) {
            $params = $this->helper->getParams();
            if(!empty($params)) {
                $orderId = $params['orderId'];
                if(isset($params["api_type"]) && ($params["api_type"] == 'old')) {
                    $oldData = $this->paymentIntentOld($orderId, $error, $result);
                    return json_encode($oldData);
                } else {
                    $resultData = $this->paymentIntentNew($orderId, $accessToken);
                    $result = (array)json_decode($resultData);
                    if(isset($result->payment_intent_id) && !empty($result->payment_intent_id)) {
                        $this->updatePaymentIntent($orderId, $result->payment_intent_id);
                    }
                }
            }
        } else {
            $error = true;  
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function paymentIntentNew($orderId, $accessToken) {
        $orderData = $this->getOrderData($orderId);
        $orderNumber = $orderData['increment_id'];
        $quoteId = $orderData['quote_id'];
        $customerId = $orderData['customer_id'];
        $grandTotals = $orderData['grand_total'];
        $currency = $orderData['order_currency_code'];
        $paymentData = $this->getPaymentInfo($orderId);
        $resultData = $this->createPaymentIntent($orderNumber, $quoteId, $customerId, $grandTotals, $currency, $paymentData, $accessToken);
        return $resultData;
    }

    public function createPaymentIntent($orderId, $quoteId, $customerId, $grandTotals, $currency, $paymentData, $accessToken) {
        $paymentOptions = $this->helper->getIboPaymentOptions();
        $paymentMethods = array();
        $orderPaymentCode = "CASH";
        $paymentDeviceMode = $paymentData['payment_device_mode'];
        if(!empty($paymentData['payment_data'])) {
            $splitPaymentData = (array)json_decode($paymentData['payment_data']);
            foreach ($splitPaymentData as $payment) {
                $payment = (array)$payment;
                $orderPaymentCode = $payment['payment_code'];
                $paymentMethods[] = $this->getPaymentMethodsData($paymentOptions, $payment, $orderId, $currency, $quoteId, $paymentDeviceMode);
            }
        }
        $data = array( 
            // "order_id" => $orderId, 
            "order_number" => $orderId, 
            "customer_id" => $customerId, 
            "amount" => array(
                "cent_amount" => $grandTotals * 10000,
                "currency" => $currency,
                "fraction" => 10000
            ), 
            "status" => ($orderPaymentCode == 'BANK-DEPOSIT' || $orderPaymentCode == 'SARALOAN' || $orderPaymentCode == 'BHARATPE') ? "PAYMENT_INTENT_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_CHARGED",
            // "statuses" => [
            //     array(
            //         "status" => ($orderPaymentCode == 'BANK-DEPOSIT' || $orderPaymentCode == 'SARALOAN' || $orderPaymentCode == 'BHARATPE') ? "PAYMENT_INTENT_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_CHARGED",
            //         "event_time" => date('Y-m-d H:i:s')
            //     ) 
            // ], 
            "payment_intent_methods" => $paymentMethods, 
        ); 

        $header = array(
            "Content-Type: application/json",
            "Authorization: Bearer " . "$accessToken",
            "x-channel-id: STORE"
        );

        $paymentIntentUrl = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_create_payment_intent_api', null);
        $apiResponse = $this->helper->curlPostRequest($paymentIntentUrl, $data, $header, true, 'Payment Intent');
        return $apiResponse;
    }

    private function getPaymentMethodsData($paymentOptions, $method, $orderId, $currency, $quoteId, $paymentDeviceMode) {
        $items = $this->getQuoteItems($quoteId, $currency);
        $customInfo = (($method['payment_code'] == 'OFFLINE') && (isset($method['custom_info']))) ? (array)$method['custom_info'] : array();
        if((($method['payment_code'] == 'EZETAP-EMI') || ($method['payment_code'] == 'UPI') || ($method['payment_code'] == 'CREDIT-CARD') || ($method['payment_code'] == 'DEBIT-CARD') || ($method['payment_code'] == 'NET-BANKING')) && (array_key_exists($method['payment_code'], $paymentOptions))) {
            $paymentOptions[$method['payment_code']]['psp_id'] = ($paymentDeviceMode == 'ezetap-hdfc') ? $paymentOptions[$method['payment_code']]['hdfc_psp_id'] : $paymentOptions[$method['payment_code']]['axis_psp_id'];
        } elseif ((($method['payment_code'] == 'CARD') || ($method['payment_code'] == 'PINELABS-UPI') || ($method['payment_code'] == 'EMI')) && (array_key_exists($method['payment_code'], $paymentOptions))) {
            $paymentOptions[$method['payment_code']]['psp_id'] = ($paymentDeviceMode == 'pinelabs-icici') ? $paymentOptions[$method['payment_code']]['pinelabs_icici_psp_id'] : $paymentOptions[$method['payment_code']]['pinelabs_axis_psp_id'];
        }

        $data = array(
            "payment_option_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['payment_option_id'] : $paymentOptions['CREDIT-CARD']['payment_option_id'], 
            "psp_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['psp_id'] : $paymentOptions['CREDIT-CARD']['psp_id'], 
            "amount" => array(
                "cent_amount" => $method['amount'] * 10000,
                "currency" => $currency,
                "fraction" => 10000 
            ), 
            "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_INTENT_METHOD_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_METHOD_CHARGED", 
            // "statuses" => [
            //     array(
            //         "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_INTENT_METHOD_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_METHOD_CHARGED", 
            //         "event_time" => date('Y-m-d H:i:s') 
            //     ) 
            // ], 
            "method_detail" => [
                array(
                    "key" => "type",
                    "value" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['code'] : $method['payment_code']
                )
            ], 
            "transactions" => [
                array(
                    "payment_option_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['payment_option_id'] : $paymentOptions['CREDIT-CARD']['payment_option_id'], 
                    "psp_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['psp_id'] : $paymentOptions['CREDIT-CARD']['psp_id'], 
                    "amount" => array(
                        "cent_amount" => $method['amount'] * 10000,
                        "currency" => $currency,
                        "fraction" => 10000 
                    ), 
                    "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "CHARGE_PENDING" : "CHARGED", 
                    // "statuses" => [
                    //     array(
                    //         "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "CHARGE_PENDING" : "CHARGED",
                    //         "event_time" => date('Y-m-d H:i:s')  
                    //     ) 
                    // ], 
                    "type" => "CHARGE", 
                    "request_id" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId, 
                    "transaction_reference_id" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
                    'custom_info'=> $customInfo
                )
            ],  
            "payment_lines" => $items
        );

        return $data;
    }

    private function getQuoteItems($quoteId, $currency) {
        $items = array();
        $connection = $this->resource->getConnection();
        $quoteItemsTable = $this->resource->getTableName('quote_item');
        $quoteData = $connection->query("SELECT * FROM $quoteItemsTable WHERE quote_id='" . $quoteId . "'")->fetchAll();
        if(!empty($quoteData)) {
            foreach($quoteData as $item) {
                $product = $this->productRepository->getById($item['product_id'], false, null, true);
                $orderLineNumber = $item['item_id'];
                $items[] = array(
                    // "order_line_id" => $item['item_id'],
                    "order_line_number" => "$orderLineNumber", 
                    "offering_id" => $item['sku'],
                    "quantity" => array(
                        "quantity_uom" => $product->getAttributeText('sale_uom'),
                        "quantity_number" => (int)$item['qty']
                    ),
                    "unit_amount" => array(
                        "cent_amount" => $item['price_incl_tax'] * 10000,
                        "currency" => $currency,
                        "fraction" => 10000
                    ),
                    "type" => "CREATED" 
                );
            }
        }
        return $items;
    }

    private function getOrderData($orderId) {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderData = $connection->query("SELECT * FROM $orderTable WHERE entity_id='" . (int)$orderId . "'")->fetch();
        return $orderData;
    }

    private function getPaymentInfo($orderId) {
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $ezetapTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $paymentData = $connection->query("SELECT * FROM $posOrderTable WHERE order_id='" . (int)$orderId . "'")->fetch();
        return $paymentData;
    }
    
    private function updatePaymentIntent($orderId, $paymentIntentId) {
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $connection->query("UPDATE $posOrderTable set payment_intent_id='$paymentIntentId' WHERE order_id=$orderId");
    }

    private function paymentIntentOld($orderId, $error, $result) {
        $orderData = $this->getOrderData($orderId);
        $paymentOptions = $this->helper->getIboPaymentOptions();
        $items = array();
        if(!empty($orderData)) {
            $orderItmsData = $this->getOrderItemsDataOld($orderId);
            if(!empty($orderItmsData)) {
                foreach($orderItmsData as $item) {
                    $product = $this->productRepository->getById($item['product_id'], false, null, true);
                    $items[] = array(
                        "offering_id" => $item['sku'],
                        "order_line_id" => $item['item_id'],
                        "quantity" => array(
                            "quantity_uom" => $product->getAttributeText('sale_uom'),
                            "quantity_number" => $item['qty_ordered']
                        ),
                        "unit_amount" => array(
                            "cent_amount" => $item['price_incl_tax'] * 10000,
                            "currency" => $orderData['order_currency_code'],
                            "fraction" => 10000
                        ),
                    );
                }
            }
            $paymentData = $this->getPaymentInfo($orderId);
            $paymentMethods = array();
            $orderPaymentCode = "CASH";
            if(!empty($paymentData['payment_data'])) {
                $splitPaymentData = json_decode($paymentData['payment_data']);
                foreach ($splitPaymentData as $key => $value) {
                    $method = (array)$value;
                    $orderPaymentCode = $method['payment_code'];
                    $paymentMethods[$key] = $this->getPaymentMethodsDataOld($paymentOptions, $method, $orderData, true);
                }
            } else {
                $paymentMethods[0] = $this->getPaymentMethodsDataOld($paymentOptions, $paymentData, $orderData);
            }

            $data = array(
                "cart_id" => $orderData['quote_id'],
                "order_id" => $orderData['entity_id'],
                "order_number" => $orderData['increment_id'],
                "customer_id" => $orderData['customer_id'],
                "channel" => "STORE",
                "status" => ($orderPaymentCode == 'BANK-DEPOSIT') || ($orderPaymentCode == 'SARALOAN') || ($orderPaymentCode == 'BHARATPE') ? "PAYMENT_INTENT_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_CHARGED",
                'order_lines' => $items,
                "amount" => array(
                    "cent_amount" => $orderData['grand_total'] * 10000,
                    "currency" => $orderData['order_currency_code'],
                    "fraction" => 10000
                ),
                "payment_methods" => $paymentMethods
            );
            $header = array(
                "Content-Type: application/json"
            );

            $baseUrl = $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_payment_api_url", $storeId = null);
            $paymentIntentUrl = rtrim($baseUrl, "/");
            $apiResponse = $this->helper->curlPostRequest($paymentIntentUrl, $data, $header, true, 'Payment Intent');
            
            $result = json_decode($apiResponse);

            if(isset($result->payment_intent_id) && !empty($result->payment_intent_id)) {
                $this->updatePaymentIntent($orderId, $result->payment_intent_id);
            }
        }
        return array('error' => (bool)$error, 'result' => $result);
    }

    private function getOrderItemsDataOld($orderId) {
        $connection = $this->resource->getConnection();
        $orderItemsTable = $this->resource->getTableName('sales_order_item');
        $orderItmsData = $connection->query("SELECT * FROM $orderItemsTable WHERE order_id='" . (int)$orderId . "'")->fetchAll();
        return $orderItmsData;
    }

    private function getEzetapDataOld($orderId, $requestId) {
        $connection = $this->resource->getConnection();
        $ezetapTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $pinelabsTable = $this->resource->getTableName('ah_supermax_pos_payment_pinelabs');
        $data = $connection->query("SELECT * FROM $ezetapTable WHERE order_id='" . (int)$orderId . "' AND request_id = '" . $requestId . "'")->fetch();
        if(empty($data)) {
            $data = $connection->query("SELECT * FROM $pinelabsTable WHERE order_id='" . (int)$orderId . "' AND request_id = '" . $requestId . "'")->fetch();
        }
        return $data;
    }

    private function getPaymentMethodsDataOld($paymentOptions, $method, $orderData, $split = false) {
        $orderId = $orderData['entity_id'];
        $ezetapData = array();
        if($method['payment_code'] == 'DEBIT-CARD' || $method['payment_code'] == 'CREDIT-CARD' || $method['payment_code'] == 'UPI' || $method['payment_code'] == 'NET-BANKING' || $method['payment_code'] == 'EMI' || $method['payment_code'] == 'CARD') {
            $ezetapData = $this->getEzetapData($orderId, $method['payment_intent_id']);
        }
        $data =  array(
            "payment_option_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['payment_option_id'] : $paymentOptions['CREDIT-CARD']['payment_option_id'],
            "psp_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['psp_id'] : $paymentOptions['CREDIT-CARD']['psp_id'], 
            "payment_tracking_number" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
            "request_id" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
            "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_INTENT_METHOD_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_METHOD_CHARGED",
            "amount" => array(
                "cent_amount" => $split ? ($method['amount'] * 10000) : ($orderData['grand_total'] * 10000),
                "currency" => $orderData['order_currency_code'],
                "fraction" => 10000
            ),
            "transaction_amount" => [
                array(
                    "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_CHARGE_PENDING" : "PAYMENT_CHARGED",
                    "type" => "CHARGE",
                    "amount" => array(
                        "cent_amount" => $split ? ($method['amount'] * 10000) : ($orderData['grand_total'] * 10000),
                        "currency" => $orderData['order_currency_code'],
                        "fraction" => 10000
                    ),
                    "transaction_reference_number" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
                    "transaction_datetime" => isset($ezetapData['date_added']) ? $ezetapData['date_added'] : $orderData['created_at'],
                    "transaction_request_data" => "",
                    "transaction_response_data" => isset($ezetapData['status_check_info']) ? $ezetapData['status_check_info'] : ""
                )
            ],
            "method_detail" => [
                array(
                    "key" => "type",
                    "value" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['code'] : $method['payment_code']
                )
            ],
        );

        return $data;
    }
}



