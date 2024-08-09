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

class PaymentIntent implements \Anyhow\SupermaxPos\Api\Supermax\PaymentIntentInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\ResourceConnection $resource,
        \Anyhow\SupermaxPos\Model\Supermax\PaymentIntentBk $paymentIntentBkFile
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->resource = $resource;
        $this->paymentIntentBkFile = $paymentIntentBkFile;
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
        // try {
            $tokenFlag = $this->helper->userAutherization();
            $accessToken = $this->helper->getCashierToken();
            $this->helper->setHeaders(); 
            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)) {
                    if(isset($params["api_type"]) && ($params["api_type"] == 'old')) {
                        $oldData = $this->paymentIntentOld($error, $result, $params);
                        return json_encode($oldData);
                    } else {
                        $orderId = $params['reserved_order_id'];
                        $quoteId = $params['quote_id'];
                        $customerId = $params['customer_id'];
                        $grandTotals = $params['grand_totals'];
                        $currency = $params['currency_code'];
                        $paymentData = $params['payment_data'];
                        $resultData = $this->paymentIntentBkFile->createPaymentIntent($orderId, $quoteId, $customerId, $grandTotals, $currency, $paymentData, $accessToken);
                        $result = (array)json_decode($resultData);
                    }
                }
            } else {
                $error = true;  
            }
        // } catch (\Exception $e) {
        //     $error = true;
        // }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    private function paymentIntentOld($error, $result, $params) {
        $items = array();
        $quoteId = $params['quote_id'];
        $orderId = $params['reserved_order_id'];
        $currency = $params['currency_code'];
        $grandTotals = $params['grand_totals'];
        $customerId = $params['customer_id'];
        $quoteItmsData = $this->getQuoteItemsOld($quoteId);
        if(!empty($quoteItmsData)) {
            foreach($quoteItmsData as $item) {
                $product = $this->productRepository->getById($item['product_id'], false, null, true);
                $items[] = array(
                    "offering_id" => $item['sku'],
                    "order_line_id" => $item['item_id'],
                    "quantity" => array(
                        "quantity_uom" => $product->getAttributeText('sale_uom'),
                        "quantity_number" => $item['qty']
                    ),
                    "unit_amount" => array(
                        "cent_amount" => $item['price_incl_tax'] * 10000,
                        "currency" => $currency,
                        "fraction" => 10000
                    ),
                );
            }
        }
        $paymentMethods = array();
        $paymentOptions = $this->helper->getIboPaymentOptions();
        $paymentData = $params['payment_data'];
        $orderPaymentCode = "CASH";
        if(!empty($paymentData)) {
            foreach ($paymentData as $payment) {
                $orderPaymentCode = $payment['payment_code'];
                $paymentMethods[] = $this->getPaymentMethodsDataOld($paymentOptions, $payment, $orderId, $currency);
            }
        }

        $data = array(
            "cart_id" => $quoteId,
            "order_number" => $orderId,
            "customer_id" => $customerId,
            "channel" => "STORE",
            "status" => ($orderPaymentCode == 'BANK-DEPOSIT' || $orderPaymentCode == 'SARALOAN' || $orderPaymentCode == 'BHARATPE') ? "PAYMENT_INTENT_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_CHARGED",
            'order_lines' => $items,
            "amount" => array(
                "cent_amount" => $grandTotals * 10000,
                "currency" => $currency,
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
        $result = (array)json_decode($apiResponse);
        return array('error' => (bool)$error, 'result' => $result);
    }

    private function getPaymentMethodsDataOld($paymentOptions, $method, $orderId, $currency) {
        $data =  array(
            "payment_option_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['payment_option_id'] : $paymentOptions['CREDIT-CARD']['payment_option_id'],
            "psp_id" => !empty($method) && array_key_exists($method['payment_code'], $paymentOptions) ? $paymentOptions[$method['payment_code']]['psp_id'] : $paymentOptions['CREDIT-CARD']['psp_id'], 
            "payment_tracking_number" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
            "request_id" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
            "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_INTENT_METHOD_CHARGE_IN_PROGRESS" : "PAYMENT_INTENT_METHOD_CHARGED",
            "amount" => array(
                "cent_amount" => $method['amount'] * 10000,
                "currency" => $currency,
                "fraction" => 10000
            ),
            "transaction_amount" => [
                array(
                    "status" => ($method['payment_code'] == 'BANK-DEPOSIT') || ($method['payment_code'] == 'SARALOAN') || ($method['payment_code'] == 'BHARATPE') ? "PAYMENT_CHARGE_PENDING" : "PAYMENT_CHARGED",
                    "type" => "CHARGE",
                    "amount" => array(
                        "cent_amount" => $method['amount'] * 10000,
                        "currency" => $currency,
                        "fraction" => 10000
                    ),
                    "transaction_reference_number" => !empty($method['payment_intent_id']) ? $method['payment_intent_id'] : $orderId,
                    "transaction_datetime" => date('Y-m-d H:i:s'),
                    "transaction_request_data" => "",
                    "transaction_response_data" => ""
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

    private function getQuoteItemsOld($quoteId) {
        $connection = $this->resource->getConnection();
        $quoteItemsTable = $this->resource->getTableName('quote_item');
        $quoteData = $connection->query("SELECT * FROM $quoteItemsTable WHERE quote_id='" . $quoteId . "'")->fetchAll();
        return $quoteData;
    }
}



