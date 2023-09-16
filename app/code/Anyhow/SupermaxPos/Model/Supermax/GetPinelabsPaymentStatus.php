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

class GetPinelabsPaymentStatus implements \Anyhow\SupermaxPos\Api\Supermax\GetPinelabsPaymentStatusInterface
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
    public function getPinelabsPaymentStatus() {
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
                $url = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_get_pinelabs_status_api_url", null);
                $apiResponse = $this->helper->curlPostRequest($url, $params, $header, true, 'Get EDC(Pinelabs) Payment Status');
                $result = (array)json_decode($apiResponse);
                // $result = $this->getData();
                if(isset($params['PlutusTransactionReferenceID']) && !empty($params['PlutusTransactionReferenceID'])) {
                    $this->helper->updateEdcInfo($params['PlutusTransactionReferenceID'], json_encode($result), "pinelabs");
                    if(isset($result['ResponseMessage']) && ($result['ResponseMessage'] == "TXN APPROVED")) {
                       $paymentData = $this->getPinelabsPaymentData($result);
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

    private function getPinelabsPaymentData($paymentStausData) {
        $ezetapPaymentData = array(
            "payment_code" => "",
            "payment_method" => ""
        );
        if(isset($paymentStausData['TransactionData']) && !empty($paymentStausData['TransactionData'])) {
            foreach ($paymentStausData['TransactionData'] as $transactionData) {
                $transactionData = (array)$transactionData;
                if(isset($transactionData['Tag']) && ($transactionData['Tag'] == "PaymentMode")) {
                    $paymentMode = $transactionData['Value'];
                    switch($paymentMode) {
                        case 'CARD':
                            $ezetapPaymentData['payment_code'] = "CARD"; 
                            $ezetapPaymentData['payment_method'] = "CARD"; 
                            break;
                        case 'PHONEPE':
                            $ezetapPaymentData['payment_code'] = "PINELABS-UPI"; 
                            $ezetapPaymentData['payment_method'] = "PINELABS-UPI"; 
                            break;
                        case 'PayTm':
                            $ezetapPaymentData['payment_code'] = "PINELABS-UPI"; 
                            $ezetapPaymentData['payment_method'] = "PINELABS-UPI"; 
                            break;
                        case 'UPI SALE':
                            $ezetapPaymentData['payment_code'] = "PINELABS-UPI"; 
                            $ezetapPaymentData['payment_method'] = "PINELABS-UPI"; 
                            break;
                        case 'UPI Bharat QR':
                            $ezetapPaymentData['payment_code'] = "PINELABS-UPI"; 
                            $ezetapPaymentData['payment_method'] = "PINELABS-UPI"; 
                            break;
                        case 'UPI AIRTEL BANK':
                            $ezetapPaymentData['payment_code'] = "PINELABS-UPI"; 
                            $ezetapPaymentData['payment_method'] = "PINELABS-UPI"; 
                            break;
                        case 'BANK EMI':
                            $ezetapPaymentData['payment_code'] = "EMI"; 
                            $ezetapPaymentData['payment_method'] = "EMI"; 
                            break;
                        default:
                            $ezetapPaymentData['payment_code'] = "NET-BANKING"; 
                            $ezetapPaymentData['payment_method'] = "NET-BANKING"; 
                    }
                    return $ezetapPaymentData;
                }
            }
        }
        return $ezetapPaymentData;
    }

    // private function getData() {
    //     $data = array (
    //         'ResponseCode' => 0,
    //         'ResponseMessage' => 'TXN APPROVED',
    //         'PlutusTransactionReferenceID' => 652557,
    //         'TransactionData' => 
    //         array (
    //           0 => 
    //           array (
    //             'Tag' => 'TID',
    //             'Value' => '0',
    //           ),
    //           1 => 
    //           array (
    //             'Tag' => 'MID',
    //             'Value' => '0',
    //           ),
    //           2 => 
    //           array (
    //             'Tag' => 'PaymentMode',
    //             'Value' => 'CARD',
    //           ),
    //           3 => 
    //           array (
    //             'Tag' => 'Amount',
    //             'Value' => '4200',
    //           ),
    //           4 => 
    //           array (
    //             'Tag' => 'BatchNumber',
    //             'Value' => '9162',
    //           ),
    //           5 => 
    //           array (
    //             'Tag' => 'RRN',
    //             'Value' => '',
    //           ),
    //           6 => 
    //           array (
    //             'Tag' => 'ApprovalCode',
    //             'Value' => '',
    //           ),
    //           7 => 
    //           array (
    //             'Tag' => 'Invoice Number',
    //             'Value' => '105',
    //           ),
    //           8 => 
    //           array (
    //             'Tag' => 'Card Number',
    //             'Value' => '',
    //           ),
    //           9 => 
    //           array (
    //             'Tag' => 'Expiry Date',
    //             'Value' => '',
    //           ),
    //           10 => 
    //           array (
    //             'Tag' => 'Card Type',
    //             'Value' => '',
    //           ),
    //           11 => 
    //           array (
    //             'Tag' => 'Acquirer Id',
    //             'Value' => '00',
    //           ),
    //           12 => 
    //           array (
    //             'Tag' => 'Acquirer Name',
    //             'Value' => 'WALLET',
    //           ),
    //           13 => 
    //           array (
    //             'Tag' => 'Transaction Date',
    //             'Value' => '10092018',
    //           ),
    //           14 => 
    //           array (
    //             'Tag' => 'Transaction Time',
    //             'Value' => '172407',
    //           ),
    //           15 => 
    //           array (
    //             'Tag' => 'AmountInPaisa',
    //             'Value' => '00',
    //           ),
    //           16 => 
    //           array (
    //             'Tag' => 'OriginalAmount',
    //             'Value' => '42',
    //           ),
    //           17 => 
    //           array (
    //             'Tag' => 'FinalAmount',
    //             'Value' => '0',
    //           ),
    //         ),
    //     );

    //     return $data;

    // }
}



