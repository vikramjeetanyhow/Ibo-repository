<?php
namespace Ibo\MrpUpdate\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Exception\LocalizedException;
use \Ibo\MrpUpdate\Helper\Logger as MRPLogger;

class MrpUpdateInOodo
{

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var MRPLogger
     */
    private $_mrpLogger;

    /**
     * @var Construct
     * @param string $scopeConfigInterface
     * @param string $_curl
     * @param string $_mrpLogger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        Curl $_curl,
        MRPLogger $_mrpLogger
    ) {
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->_curl = $_curl;
        $this->_mrpLogger = $_mrpLogger;
    }
    /**
     * @inheritdoc
     */
    public function update($params)
    {
        $sku = $params['sku'];
        $mrp = $params['mrp'];

        try {
            $authorization = $this->scopeConfigInterface->getValue("mrpprice_update/settings/authorization");
            $url = $this->scopeConfigInterface->getValue("mrpprice_update/settings/url");
            if (!empty($authorization) && !empty($url)) {
                $params['product_sku'] = $sku;
                $params['mrp'] = $mrp;
                if (!is_array($params)) {
                    $paramData = $params->getData();
                } else {
                     $paramData = $params;
                }
                $encodedParams = json_encode($paramData, JSON_UNESCAPED_SLASHES);
                $header = [
                    "Content-Type" => "application/json",
                    "Content-Length" =>  strlen($encodedParams),
                    "Authorization" =>  $authorization
                ];

                $oodoPush = $this->apiCaller($url, \Zend_Http_Client::POST, $encodedParams, $header);
                if (!empty($oodoPush)) {
                    $resp =  json_decode($oodoPush, true);
                    if (isset($resp['result'])) {
                        $this->_mrpLogger->addLog('MRP value updated successfully in OODO for SKU: '.$sku);
                    } elseif (isset($resp['error'])) {
                        $this->_mrpLogger->addLog('MRP value not updated in OODO for SKU '.$sku);
                        $this->_mrpLogger->addLog('OODO error: '.$sku.' due to : '.$resp['error']['data']['message']);
                    }
                }
            } else {
                $this->_mrpLogger->addLog('Authorization/Url Configuration not configured');
            }

        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->_mrpLogger->addLog('Error: '.$sku.': '.$e->getMessage());
        }
    }

    /**
     * ApiCaller to send value from magneto to Oodo
     *
     * @param String $url
     * @param Mixed $method
     * @param Mixed $params
     * @param Mixed $header
     * @return Array
     */
    public function apiCaller($url, $method, $params, $header = null)
    {
        $this->_mrpLogger->addLog('payload - ' . $params);

        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_POST, true);
        $this->_curl->setHeaders($header);
        $this->_curl->post($url, $params);
        $result = $this->_curl->getBody();

        return $result;
    }
}
