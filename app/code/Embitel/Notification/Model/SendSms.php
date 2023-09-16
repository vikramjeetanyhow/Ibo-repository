<?php
namespace Embitel\Notification\Model;

use Magento\Framework\HTTP\Client\Curl;
use Embitel\Notification\Helper\Data as EmbitelSmsHelper;

class SendSms
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
        \Magento\Customer\Api\CustomerMetadataInterface $metadataService,
        Curl $curl,
        EmbitelSmsHelper $embitelSmsHelper
    )
    {
        $this->curl = $curl;
        $this->logger = $logger; 
        $this->customer = $customer; 
        $this->embitelSmsHelper = $embitelSmsHelper;
        $this->_scopeConfig = $scopeConfig;
        $this->metadataService = $metadataService;
    }

    public function sendApprovedSms($customerTypeLabel, $mobileNumber){
        if($this->embitelSmsHelper->getApiEnable()
            && $this->embitelSmsHelper->isBizomMagentoSmsEnabled()){
                $appUrl = $this->embitelSmsHelper->getIboUrl();
                $eventId = $this->embitelSmsHelper->getBizomMagentoEventId();
                $body = "{\"customer_type\":\"".$customerTypeLabel."\",\"ibo_url\":\"".$appUrl."\"}";
                $this->sendSms($eventId, $body, $mobileNumber);
        }
    }

    public function sendUnapprovedSms($customerTypeLabel, $mobileNumber){
        if($this->embitelSmsHelper->getApiEnable()
            && $this->embitelSmsHelper->isUnapprovedCustomerSmsEnabled()){
            $customerTypes = [];
            if(!empty($this->embitelSmsHelper->getCustomerTypeIds())){
                $customerTypes = explode(',', $this->embitelSmsHelper->getCustomerTypeIds());
            }
            if(empty($customerTypes) || (!empty($customerTypes) && in_array($customerTypeLabel,$customerTypes))){
                $eventId = $this->embitelSmsHelper->getUnapprovedCustomerEventId();
                $body = "{\"var\":\"var\"}";
                $this->sendSms($eventId, $body, $mobileNumber);
            }
        }
    }

    private function sendSms($eventId, $body, $mobileNumber){
        $url = $this->embitelSmsHelper->getApiUrl().'v1/trigger';
        $apiKey = $this->embitelSmsHelper->getApiKey(); 
        $template = array();
        if($url && $apiKey) { 
            $template['message']['body'] = $body;
            $template['message']['event_id'] = $eventId;  
            $template['message']['header']['override_config']['phone_number'] = [$mobileNumber];  

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
                $this->logger->info($eventId . " - SMS Send to ".$mobileNumber);
            } catch (\Exception $e) {
                // $this->addLog($e->getMessage());
                $this->logger->info($eventId . " - SMS Send Error ".$e->getMessage());
            }
        }
    }

}