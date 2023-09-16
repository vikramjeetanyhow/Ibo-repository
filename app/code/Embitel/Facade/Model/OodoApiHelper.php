<?php

namespace Embitel\Facade\Model;

use Embitel\Facade\Helper\CurlHelper;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Store\Model\ScopeInterface;

class OodoApiHelper
{
    const LOG_PATH = '/var/log/odoo_product_sync.log';
    const XML_PATH_CRON_STATUS = 'oodo_sync/general/cron_status';
    protected DataObject $config;
    private ClientFactory $clientFactory;
    private ScopeConfigInterface $scopeConfig;
    private Curl $clientCurl;
    private CurlHelper $curlHelper;

    /**
     * @param ClientFactory $clientFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CurlHelper $curlHelper
     */

    public function __construct(
        ClientFactory        $clientFactory,
        ScopeConfigInterface $scopeConfig,
        CurlHelper $curlHelper
    )
    {
        $this->clientFactory = $clientFactory;
        $this->scopeConfig = $scopeConfig;
        $this->config = new DataObject();
        $this->curlHelper = $curlHelper;
    }

    /**
     * Check if cron is enabled and api data is updated
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isCronActive() && $this->isApiDataAvailable();
    }

    /**
     * Check is cron active or not
     *
     * @return bool
     */
    public function isCronActive(): bool
    {
        $isCronEnable = $this->getConfig()->getCronStatus();
        if (!$isCronEnable || $isCronEnable == 0) {
            $this->addLog("Cron to sync product to Oodo is disabled");
        }
        return $isCronEnable;
    }

    /**
     *
     * Prepare Oodo configurations
     *
     * @return DataObject
     */
    public function getConfig(): DataObject
    {
        if (!$this->config->getCronStatus()) {
            $this->config->addData(
                $this->scopeConfig->getValue('oodo_sync/general', ScopeInterface::SCOPE_WEBSITE)
            );
        }
        return $this->config;
    }

    /**
     * Add data to log file
     *
     * @param $logdata
     * @return void
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            if (is_array($logdata)) {
                $this->getOodoSyncLog()->info(print_r($logdata, true));
            } else {
                $this->getOodoSyncLog()->info($logdata);
            }
        }
    }

    public function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->getConfig()->getLogStatus();
        }
        return $this->isLogEnable;
    }

    /**
     * Check is API Url or X-API-KEY available
     *
     * @return bool
     */
    public function isApiDataAvailable(): bool
    {
        if (!$this->config->getApiUrl() || !$this->config->getXApiKey()) {
            $this->addLog('API Url or X-API-KEY is not added');
            return false;
        }
        return true;
    }

    /**
     * @return Logger
     */
    private function getOodoSyncLog(): Logger
    {
        $writer = new Stream(BP . self::LOG_PATH);
        $logger = new Logger();
        $logger->addWriter($writer);
        return $logger;
    }

    /**
     * Send request to oodo
     *
     * @param $endPoint
     * @param string $method
     * @param string $requestBody
     * @param array $header
     * @return int
     */
    public function send($endPoint, string $method = \Zend_Http_Client::POST, string $requestBody = '', array $header = []): int
    {

        $apiUrl = trim($this->getConfig()->getApiUrl()) . $endPoint;

        $client = $this->clientFactory->create();
        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => $this->getConfig()->getXApiKey(),
            "Expect" => ""
        ];
        if (!empty($header)) {
            foreach ($header as $key => $value) {
                $headers[$key] = $value;
            }
        }
        $client->setHeaders($headers);

        if ($method == \Zend_Http_Client::POST) {
            $client->post($apiUrl, $requestBody);
        } else {
            $client->get($apiUrl);
        }

        $response = $client->getBody();
        $this->addLog("Request Body:");
        $this->addLog($requestBody);
        $this->addLog("Headers:");
        $this->addLog($client->getHeaders());
        $this->addLog("Status:");
        $this->addLog($client->getStatus());
        $this->addLog("Body:");
        $this->addLog($response);
        $this->addLog("-------------------------");

        return $client->getStatus();


    }

    /**
     * Send request to oodo
     *
     * @param $endPoint
     * @param string $method
     * @param string $requestBody
     * @param array $header
     * @return int
     */
    public function curlSend($endPoint, string $method = \Zend_Http_Client::POST, string $requestBody = ''): int
    {
        $apiUrl = trim($this->getConfig()->getApiUrl()) . $endPoint;

        $client = $this->curlHelper;
        $headers = [
            "Content-Type: application/json",
            "Authorization:" .$this->getConfig()->getXApiKey()
        ];


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
        ));


        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);

        curl_close($curl);

        $this->addLog("Request Body:");
        $this->addLog($requestBody);
        $this->addLog("Headers:");
        $this->addLog($headers);
        $this->addLog("Status:");
        $this->addLog($statusCode);
        $this->addLog("Body:");
        $this->addLog($response);
        $this->addLog("-------------------------");

        return $statusCode;


    }

}