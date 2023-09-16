<?php
namespace Embitel\Customer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\Oodo\Helper\Logger;

class Api
{
    /**
     * Logger
     */
    protected $logger;
    
    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function apiCaller($url, $method, $params, $header = null)
    {
        $this->logger->addLog('payload - ' . $params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode == 200) {
            return $result;
        } else {
            return json_encode(['api_error' => 'HTTP Code - ' . $httpcode . ' with response - ' . $result]);
        }
    }

    public function pushCustomerToMoengage($customer)
    {
        $url = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/url");
        $apiKey = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/api_key");
        $authorization = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/api_auth");
        if (!empty($authorization) && !empty($url)) {
            $url = $url . '/' . $apiKey;
            $header = [
                "Content-Type: application/json",
                "Authorization: Basic " . base64_encode($apiKey . ":" . $authorization)
            ];
            $customerPushtoMoengage = $this->apiCaller($url, \Zend_Http_Client::POST, $customer, $header);
            if (!empty($customerPushtoMoengage)) {
                return json_decode($customerPushtoMoengage, true);
            }
            return [];
        } else {
            return ['api_error' => 'Authorization/Url Configuration not configured'];
        }
    }

    public function pushCustomerToOfflineMoengage($customer)
    {
        $offlineUrl = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/offline_api_url");
        $offlineApiKey = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/offline_api_key");
        $offlineAuthorization = $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/offline_api_auth");
        if (!empty($offlineAuthorization) && !empty($offlineUrl)) {
            $offlineUrl = $offlineUrl . '/' . $offlineApiKey;
            $header = [
                "Content-Type: application/json",
                "Authorization: Basic " . base64_encode($offlineApiKey . ":" . $offlineAuthorization)
            ];
            $customerPushtoMoengage = $this->apiCaller($offlineUrl, \Zend_Http_Client::POST, $customer, $header);
            if (!empty($customerPushtoMoengage)) {
                return json_decode($customerPushtoMoengage, true);
            }
            return [];
        } else {
            return ['api_error' => 'IBO Offline Authorization/Url Configuration not configured'];
        }
    }
}
