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
class SendSmsUnapprovedCustomer implements ObserverInterface
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

    /**
     * Handler for 'customer_login' event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    { 
        /** @var \Magento\Customer\Model\Data\Customer $customerOrig */
        $customerOrig = $observer->getEvent()->getOrigCustomerDataObject();

        /** @var \Magento\Customer\Model\Data\Customer $customer */
        $customer = $observer->getEvent()->getCustomerDataObject();

        if($this->embitelSmsHelper->getApiEnable()
            && $this->embitelSmsHelper->isBizomMagentoSmsEnabled()){
                $approvalOptions = $this->metadataService->getAttributeMetadata('approval_status')->getOptions();
                $approvalId = 0;
                if(empty($customerData)){
                    $customerData = $this->customer->create()->load($customer->getId());
                }
                //get approved status id 
                foreach ($approvalOptions as $optionData) {
                    if ($optionData->getLabel() && $optionData->getLabel() == 'approved') {
                        $approvalId = $optionData->getValue();
                    }
                }

                if($customerOrig){
                    $customerOrigData = $this->customer->create()->load($customerOrig->getId());
                }

                //check whethere customer is approved by bizom
                if((!$customerOrig && $customerData->getApprovalStatus() == $approvalId)
                    || (!empty($customerOrig) && !empty($customerOrigData)
                        && $customerOrigData->getApprovalStatus() != $customerData->getApprovalStatus()
                        && $customerData->getApprovalStatus() == $approvalId)){
                            $acustomerTypeOptions = $this->metadataService->getAttributeMetadata('customer_type')->getOptions();
                            $customerTypeLabel = '';
                            foreach ($acustomerTypeOptions as $optionData) {
                                if ($optionData->getValue() == $customerData->getCustomerType()) {
                                    $customerTypeLabel = $optionData->getLabel();
                                }
                            }
                            $appUrl = $this->embitelSmsHelper->getIboUrl();
                            $eventId = $this->embitelSmsHelper->getBizomMagentoEventId();
                            $mobileNumber = $customerData->getMobilenumber();
                            $body = "{\"customer_type\":\"".$customerTypeLabel."\",\"ibo_url\":\"".$appUrl."\"}";
                            $this->sendSms($eventId, $body, $mobileNumber);
                }
        }

        if($this->embitelSmsHelper->getApiEnable()
            && $this->embitelSmsHelper->isUnapprovedCustomerSmsEnabled()){
                $customerTypeOptions = $this->metadataService->getAttributeMetadata('customer_type')->getOptions();
                $customerTypeIds = [];
                if(empty($customerData)){
                    $customerData = $this->customer->create()->load($customer->getId());
                }
                $customerTypes = [];
                if(!empty($this->embitelSmsHelper->getCustomerTypeIds())){
                    $customerTypes = explode(',', $this->embitelSmsHelper->getCustomerTypeIds());
                }
                //get customerTypeIds
                foreach ($customerTypeOptions as $optionData) {
                    if ($optionData->getLabel() && in_array($optionData->getLabel(),$customerTypes)) {
                        $customerTypeIds[] = $optionData->getValue();
                    }
                }

                //check whethere customer belongs to unapproved customer types
                if(!$customerOrig && in_array($customerData->getCustomerType(), $customerTypeIds)){
                    $eventId = $this->embitelSmsHelper->getUnapprovedCustomerEventId();
                    $mobileNumber = $customerData->getMobilenumber();
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
