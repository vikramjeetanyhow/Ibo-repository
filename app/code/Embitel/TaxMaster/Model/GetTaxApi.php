<?php
namespace Embitel\TaxMaster\Model;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class GetTaxApi extends AbstractHelper
{
    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Curl
     */
    protected $curl;

    /**
     * Construct method
     *
     * @param string $context
     * @param string $curl
     * @param string $scopeConfig
     */
    public function __construct(
        Context $context,
        Curl $curl,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
    }

    //Get Tax by HSN code using CURL method
    /**
     * Get tax by HSN code
     *
     * @param string $hsnCode
     */
    public function getTax($hsnCode)
    {
        $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/taxrate-update.log');
        $logger = new \Laminas\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('===== START get tax API =====');

        if(!empty($hsnCode) && strpos($hsnCode, "\n") == FALSE) {

            $logger->info('Valid HSN code: '.$hsnCode);

            $URL = $this->scopeConfig->getValue("tax_master/curl_config/curl_api") . $hsnCode;

            //set curl options
            $this->curl->setOption(CURLOPT_HEADER, 0);
            $this->curl->setOption(CURLOPT_TIMEOUT, 600);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'GET');
            //set curl header
            $this->curl->addHeader("x-api-key", $this->scopeConfig->getValue("tax_master/curl_config/api_key"));
            //get request with url
            $this->curl->get($URL);
            //read response
            $response = $this->curl->getBody();
            $response = json_decode($response);
            $logger->info('END get tax API');
            return $response;
        } else {
            $logger->info('Invalid HSN code: '.$hsnCode);
            $logger->info('END get tax API');
        }
    }
}
