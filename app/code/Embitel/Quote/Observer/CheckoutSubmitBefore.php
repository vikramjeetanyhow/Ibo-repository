<?php

namespace Embitel\Quote\Observer;
use Magento\Framework\Exception\InputException;
use Magento\Framework\HTTP\Client\Curl;

class CheckoutSubmitBefore implements \Magento\Framework\Event\ObserverInterface
{ 
	protected $helper;
    public function __construct( 
    	Curl $curl, 
    	\Embitel\Quote\Helper\Data $helper

    ){
        $this->curl = $curl;
        $this->helper = $helper;

    }

	public function execute(\Magento\Framework\Event\Observer $observer)
	{ 
        // if($this->helper->getPromiseStatus()) { //If promise call enabled in backend 
        //     $this->addLog('Promise is enabled');
        //     $payload_arr = array();
        //     $quote = $observer->getQuote(); 
        //     $postcode = $quote->getShippingAddress()->getPostcode(); 
        //     $traceId = $this->helper->getTraceId(); 
        //     $client_id = $this->helper->getClientId();
        //     $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
        //     $is_error= 0;
        //     $resultData = array();

        //     if($postcode){ //Trigger API call to check inventory before convert quote to order
        //         $this->addLog('Got postcode:'.$postcode);
        //         $promiseUrl = $this->helper->getPromiseApi(); 
        //         $payload = $this->helper->CreateJsonRequestData($quote,$postcode); 
        //         $this->curl->setOption(CURLOPT_RETURNTRANSFER, true); 
        //         $this->curl->setOption(CURLOPT_POST, true); 
        //         $this->curl->setHeaders($headers);
        //         $this->curl->post($promiseUrl, $payload);
        //         $result = $this->curl->getBody();
        //         $resultData = json_decode($result,true);
        //         $this->addLog('Promise result for postcode:'.$postcode);
        //         $this->addLog(json_encode($resultData));
        //     } 
        //     if(isset($resultData) && isset($resultData['promise_id'])){ 
        //         $data = $resultData['delivery_groups']; 
        //         foreach($data as $deliveryData){ 
        //             $delOptionData = $deliveryData['delivery_group_lines']; 
        //             foreach($delOptionData as $delData) { 
        //                 if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {  
        //                     $this->addLog('Quantity mismatch for postcode:'.$postcode);
        //                     $is_error = 1;
        //                 } 
        //             } 
        //         }
        //     }
        //     if(!$is_error){ 
                
        //         $shippingRecievedAt = $quote->getShippingReceivedAt(); 
        //         $shippingUpdatedAt = $quote->getShippingUpdateAt();

        //         if($shippingUpdatedAt > $shippingRecievedAt) {
        //             throw new InputException(
        //                         __('Quote is changed Please update the cart.')
        //                     ); 
        //                     return; 
        //         } 
                
        //         //Make promise reservation call
        //         // $this->addLog('No quantity error for postcode:'.$postcode);
        //         // $url = $this->helper->getPromiseHostURL().'reservation/'; 
        //         // $quoteId = $quote->getId();
        //         // $orderId = ($quote->getReservedOrderId())?$quote->getReservedOrderId():$quote->getId();
        //         // $payload_arr["promise_id"] = $quote->getPromiseId(); 
        //         // $payload_arr["cart_id"] = $quoteId;
        //         // $payload_arr["order_number"] = $orderId;
        //         // $payload = json_encode($payload_arr);
        //         // $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        //         // $this->curl->setOption(CURLOPT_POST, true);
        //         // $this->curl->setHeaders($headers);
        //         // $this->addLog('Promise reservation curl Initiated '.$url);
        //         // $this->curl->post($url, $payload); 
        //         // $this->addLog(json_encode($payload));
        //         // $result = $this->curl->getBody(); 
        //         // $resultData = json_decode($result,true);
        //         // $status = $this->curl->getStatus();
        //         // $this->addLog('Curl Body'.json_encode($resultData));
        //         // $this->addLog('Curl Status'.$status);
        //         // if(!isset($resultData) || (isset($resultData) && !isset($resultData['success']))){ 
        //         //     throw new InputException(
        //         //         __('Promise reservation error while placing the order.')
        //         //     ); 
        //         //     return; 
        //         // }
        //     }else{
        //         throw new InputException(
        //                 __('Requested quantity is not available.')
        //             ); 
        //             return; 
        //     }
        //    return $this;
        // }
        $quote = $observer->getQuote();
        $shippingRecievedAt = $quote->getShippingReceivedAt(); 
        $shippingUpdatedAt = $quote->getShippingUpdateAt();

        if($shippingUpdatedAt > $shippingRecievedAt) {
            $this->addLog('Quote is changed Please update the cart. - CheckoutSubmitBefore');
            throw new InputException(
                        __('Quote is changed Please update the cart.')
                    ); 
                    return; 
        } 
        return $this;
	} 
    public function addLog($logData){
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/place-order.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    }    

}