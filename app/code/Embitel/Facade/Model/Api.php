<?php

namespace Embitel\Facade\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\HTTP\ClientFactory;

class Api extends AbstractModel
{
    const XML_PATH_CRON_STATUS = 'facade/general/cron_status';

    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var ClientFactory
     */
    protected $clientFactory;

    /**
     * @var DataObject
     */
    protected $config;

    protected $products;

    /**
     * @param EncryptorInterface $encryptor
     * @param ClientFactory $clientFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $productCollection
     */
    public function __construct(
        EncryptorInterface $encryptor,
        ClientFactory $clientFactory,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $productCollection
    ) {
        $this->encryptor = $encryptor;
        $this->clientFactory = $clientFactory;
        $this->scopeConfig = $scopeConfig;
        $this->productCollection = $productCollection;
        $this->config = new \Magento\Framework\DataObject();
    }

    /**
     * Prepare facade configurations
     *
     * @return DataObject
     */
    public function getConfig()
    {
        if (!$this->config->getCronStatus()) {
            $this->config->addData(
                $this->scopeConfig->getValue('facade/general', ScopeInterface::SCOPE_STORE)
            );
        }
        return $this->config;
    }

    /**
     * Check is cron active or not.
     *
     * @return boolean
     */
    public function isActive()
    {
        $isCronEnable = $this->getConfig()->getCronStatus();
        if (!$isCronEnable || $isCronEnable == 0) {
            $this->addLog("Cron to sync product to facade is disabled.");
            return false;
        }
        return true;
    }

    /**
     * Get password from salesforce configuration.
     *
     * @return type
     */
    private function getPassword()
    {
        //return $this->encryptor->decrypt($this->getConfig()->getXApiKey());
        return $this->getConfig()->getXApiKey();
    }

    /**
     * Check  configuration.
     *
     * @return type
     */
    public function isCredentialsAvailable()
    {
        if (!$this->getConfig()->getApiUrl() || !$this->getPassword()) {
            $this->addLog("API URL or X-API-KEY is not added.");
            return false;
        }
        return true;
    }

    /**
     * Check if cron is enabled and credentials are added
     *
     * @return type
     */
    public function isEnabled()
    {
        return ($this->isActive() && $this->isCredentialsAvailable()) ? true : false;
    }

    /**
     * Send request to facade
     *
     * @param type $endPoint
     * @param type $method
     * @param type $requestBody
     * @param type $header
     */
    public function send($endPoint, $method = \Zend_Http_Client::POST, $requestBody = '', $header = [])
    {
        $apiUrl = trim($this->getConfig()->getApiUrl()) . $endPoint;

        /** @var \Magento\Framework\HTTP\ClientFactory $client */
        $client = $this->clientFactory->create();
        $headers = [
            "Content-Type" => "application/json",
            "x-api-key" => $this->getPassword(),
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
        } elseif ($method == \Zend_Http_Client::DELETE) {
            $client->setOption(CURLOPT_CUSTOMREQUEST, $method);
            $client->setOption(CURLOPT_RETURNTRANSFER, true);
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
     * Add data to log file.
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            if (is_array($logdata)) {
                $this->logger->info(print_r($logdata, true));
            } else {
                $this->logger->info($logdata);
            }
        }
    }

    /**
     * Check log configuration is enable or not.
     */
    public function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->getConfig()->getLogStatus();
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/facade_sync.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
