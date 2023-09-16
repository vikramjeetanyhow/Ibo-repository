<?php
namespace Ibo\RegionalPricing\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\HTTP\Client\Curl;

class PriceZoneByPostalcode
{
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
    * @var Curl
    */
    protected $curl;    

    /**
     * @var Construct
     * @param string $logger
     * @param string $action
     * @param string $storeManager
     * @param string $scopeConfigInterface
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfigInterface,
        Curl $curl
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->curl = $curl;
    }
    /**
     * @inheritdoc
     */    
    public function makePostCodeApiCall($postalCode) {

        $postcodeAPIUrl = $this->scopeConfigInterface->getValue("regional_pricing/postcode_setting/postcode_api_url");
        $URL = $postcodeAPIUrl.$postalCode;
     
        //set curl options
        $this->curl->setOption(CURLOPT_HEADER, 0);
        $this->curl->setOption(CURLOPT_TIMEOUT, 60);
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'GET');
        //set curl header
        $this->curl->addHeader("Content-Type", "application/json");
        $this->curl->addHeader("trace_id", "123");
        $this->curl->addHeader("client_id", "123");
        //get request with url
        $this->curl->get($URL);
        //read response
        $response = (array)json_decode($this->curl->getBody());
        if(isset($response['price_zone']) && !empty($response['price_zone'])) {
            $priceZone = $response['price_zone'];
        }
        $priceZone = strtolower($priceZone);

        return $priceZone;

    }

}
