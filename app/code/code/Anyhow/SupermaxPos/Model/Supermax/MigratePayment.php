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

class MigratePayment implements \Anyhow\SupermaxPos\Api\Supermax\MigratePaymentInterface
{
    protected $model;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function migratePayment()
    {
        $result = array();
        $error = false;
        // try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(isset($params['start']) && isset($params['limit'])) {
                    $start = $params['start'];
                    $limit = $params['limit'];
                    $connection= $this->resource->getConnection();
                    $orderTable = $this->resource->getTableName('ah_supermax_pos_orders');
                    $paymentTable = $this->resource->getTableName('ah_supermax_pos_payment_detail');
                    $posOrderData = $connection->query("SELECT * FROM $orderTable WHERE pos_order_id > $start LIMIT $limit")->fetchAll(); 
                    if(!empty($posOrderData)) {
                        foreach ($posOrderData as $orderData) {
                            $orderId = $orderData['order_id'];
                            $posOrderId = $orderData['pos_order_id'];
                            $orderPaymentData = $connection->query("SELECT * FROM $paymentTable WHERE pos_order_id = $posOrderId")->fetch(); 
                            if(empty($orderPaymentData)) {
                                $paymentData = $orderData['payment_data'];
                                if(!empty($paymentData)) {
                                    $this->migrateData($paymentData, $posOrderId);
                                    $result['success'][] = $orderId . " | " . $posOrderId;
                                } else {
                                    $result['failed'][] = $orderId . " | " . $posOrderId;
                                }
                            } else {
                                $result['already_migrated'][] = $orderId . " | " . $posOrderId;
                            }
                        }
                    }
                }
            }
        // } catch (\Exception $e) {
        //     $error = true;
        // }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function emptyPayment() {
        $result = array();
        $error = false;
        $tokenFlag = $this->helper->userAutherization();
        $this->helper->setHeaders(); 

        if($tokenFlag) {
            $params = $this->helper->getParams();
            if(isset($params['empty']) && $params['empty']) {
                $connection = $this->resource->getConnection();
                $paymentTable = $this->resource->getTableName('ah_supermax_pos_payment_detail');
                $orderPaymentData = $connection->query("TRUNCATE TABLE $paymentTable"); 
                $result[] = 'Success'; 
            }
        }

        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function migrateSinglePaymentData() {
        $result = array();
        $error = false;
        $tokenFlag = $this->helper->userAutherization();
        $this->helper->setHeaders(); 

        if($tokenFlag) {
            $params = $this->helper->getParams();
            if(isset($params['orderId']) && $params['orderId']) {
                $orderId = $params['orderId'];
                $connection= $this->resource->getConnection();
                $orderTable = $this->resource->getTableName('ah_supermax_pos_orders');
                $paymentTable = $this->resource->getTableName('ah_supermax_pos_payment_detail');
                $orderData = $connection->query("SELECT * FROM $orderTable WHERE order_id = $orderId")->fetch(); 
                if(!empty($orderData)) {
                    $orderId = $orderData['order_id'];
                    $posOrderId = $orderData['pos_order_id'];
                    $orderPaymentData = $connection->query("SELECT * FROM $paymentTable WHERE pos_order_id = $posOrderId")->fetch(); 
                    if(empty($orderPaymentData)) {
                        $paymentData = $orderData['payment_data'];
                        if(!empty($paymentData)) {
                            $this->migrateData($paymentData, $posOrderId);
                            $result['success'][] = $orderId . " | " . $posOrderId;
                        } else {
                            $result['failed'][] = $orderId . " | " . $posOrderId;
                        }
                    } else {
                        $result['already_migrated'][] = $orderId . " | " . $posOrderId;
                    }
                } else {
                    $result['message'] = "POS order does not exist";
                }
            }
        }

        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    private function migrateData($paymentData, $posOrderId) {
        $connection = $this->resource->getConnection();
        $paymentTable = $this->resource->getTableName('ah_supermax_pos_payment_detail');
        $paymentData = (array)json_decode($paymentData);
        foreach ($paymentData as $payment) {
            $payment = (array)$payment;
            $payment_method = isset($payment['payment_method']) ? $payment['payment_method'] : "";
            $payment_code = isset($payment['payment_code']) ? $payment['payment_code'] : "";
            $amount = isset($payment['amount']) ? $payment['amount'] : 0;
            $payment_intent_id = isset($payment['payment_intent_id']) ? $payment['payment_intent_id'] : "";
            $amount_formatted = isset($payment['amount_formatted']) ? $payment['amount_formatted'] : $amount;
            $cash_paid = isset($payment['cash_paid']) ? $payment['cash_paid'] : 0;
            $cash_change = isset($payment['cash_change']) ? $payment['cash_change'] : 0;
            $orderPaymentData = $connection->query("INSERT INTO $paymentTable SET pos_order_id = $posOrderId, payment_method = '$payment_method', payment_code = '$payment_code', amount = $amount, payment_intent_id = '$payment_intent_id', amount_formatted = '$amount_formatted', cash_paid = $cash_paid, cash_change = $cash_change");
        }
    }

    public function getTotal() {
        $result = array();
        $error = false;
        $tokenFlag = $this->helper->userAutherization();
        $this->helper->setHeaders(); 

        if($tokenFlag) {
            $params = $this->helper->getParams();
            $connection= $this->resource->getConnection();
            $orderTable = $this->resource->getTableName('ah_supermax_pos_orders');
            if(isset($params['total']) && $params['total']) {
                $orderPaymentData = $connection->query("SELECT * FROM $orderTable")->fetchAll(); 
                $result['total'] = count($orderPaymentData);
                $lastRecord = $connection->query("SELECT * FROM $orderTable ORDER BY pos_order_id DESC limit 1")->fetch(); 
                if(!empty($lastRecord)) {
                    $result['last_record'] = $lastRecord;
                }
            }
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }
}



