<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Notification\Observer;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Embitel\Notification\Helper\Data as EmbitelSmsHelper;

/**
 * Customer log observer.
 */
class SendSms implements ObserverInterface
{
    /**
     * Logger of customer's log data.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Embitel\Sms\Helper\Data
     */
    protected $embitelSmsHelper;

    /**
     * @param Logger $logger
     * @param EmbitelSmsHelper $embitelSmsHelper
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Curl $curl,
        EmbitelSmsHelper $embitelSmsHelper
    )
    {
        $this->curl = $curl;
        $this->logger = $logger; 
        $this->customer = $customer; 
        $this->_scopeConfig = $scopeConfig;
        $this->embitelSmsHelper = $embitelSmsHelper;
    } 

    public function getApiKey()
    {
        $apiKey = $this->_scopeConfig->getValue("sms_notification/settings/api_key");
        return $apiKey;
    }    
    public function getApiUrl()
    {
        $apiKey = $this->_scopeConfig->getValue("sms_notification/settings/url");
        return $apiKey;
    }
    public function getApiEnable()
    {
        $apiKey = $this->_scopeConfig->getValue("sms_notification/settings/enable");
        return $apiKey;
    }    
    public function getOrderTemplate()
    {
        $apiKey = $this->_scopeConfig->getValue("sms_notification/settings/order_template");
        return $apiKey;
    }
    /**
     * Handler for 'customer_login' event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    { 
        $order = $observer->getOrder();
        if($this->getApiEnable() && $this->embitelSmsHelper->isSalesOrderSmsEnabled()) { 
            $url = $this->getApiUrl().'v1/trigger';
            $apiKey = $this->getApiKey(); 
            $template = array();
            if($url && $apiKey && $order->getIncrementId()) { 
                $customerId = $order->getCustomerId();
                $customer = $this->customer->create()->load($customerId);
                $deliveryDate = '';
                if(!empty($order->getPromiseOptions())){
                    $promiseOptions = json_decode($order->getPromiseOptions(), true);
                    foreach($promiseOptions as $newkey=>$promiseOption){
                        $shippingDetails['delivery_method'] = isset($promiseOptions[$newkey]['delivery_method']) ? $promiseOptions[$newkey]['delivery_method'] : '';
                        if(isset($promiseOptions[$newkey]["promise_delivery_info"])){
                            foreach($promiseOptions[$newkey]["promise_delivery_info"] as $newKey1 => $promiseDeliveryInfo){
                                if(isset($promiseDeliveryInfo['delivery_slot']['to_date_time'])){
                                    $deliveryDate = date("Y-m-d",strtotime($promiseDeliveryInfo['delivery_slot']['to_date_time']));
                                }
                            }
                        }
                    }
                    $this->logger->info("promise_delivery_info - ".$deliveryDate);
                }
                
                $template['message']['body'] = "{\"order_number\":\"".$order->getIncrementId()."\",\"delivery_date\":\"".$deliveryDate."\"}";//'{\"order_number\":\"'.$order->getIncrementId().'\"}';
                $template['message']['event_id'] = $this->embitelSmsHelper->getSalesOrderEventId();  
                $template['message']['header']['override_config']['phone_number'] = [$customer->getMobilenumber()];  

                $template = json_encode($template);
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->setOption(CURLOPT_POST, true);
                $headers = ["Content-Type" => "application/json", "x-api-key" => $apiKey];
                $this->curl->setHeaders($headers);
                try {
                    // $this->addLog('Curl Initiated');
                    $this->curl->post($url, $template);
                    $result = $this->curl->getBody();
                    $resultData = json_decode($result, true);
                    $this->logger->info("SMS Send to ".$customer->getMobilenumber());
                } catch (\Exception $e) {
                    // $this->addLog($e->getMessage());
                    $this->logger->info("SMS Send Error ".$e->getMessage());
                }

                $this->logger->info("SMS Sent for ".$order->getIncrementId());
            }
        }
    }
}
