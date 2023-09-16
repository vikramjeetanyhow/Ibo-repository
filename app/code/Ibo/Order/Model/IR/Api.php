<?php
namespace Ibo\Order\Model\IR;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\CustomerGraphQl\Model\Customer\Address\GetCustomerAddress;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Ibo\Order\Helper\Logger;

class Api
{
    /**
     * Logger
     */
    protected $logger;
    
    protected $_httpClient;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AttributeRepositoryInterface
     */
    private $eavAttributeRepository;

    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var GetCustomerAddress
     */
    private $getCustomerAddress;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig; 

    /**
     *
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    private $getAttributeOptions = [];

    public function __construct(
        ZendClientFactory $httpClient,
        GetCustomerAddress $getCustomerAddress,
        CustomerRepositoryInterface $customerRepository,
        AttributeRepositoryInterface $eavAttributeRepository,
        CountryFactory $countryFactory,
        ScopeConfigInterface $scopeConfig,
        GroupRepositoryInterface $groupRepository,
        Logger $logger
    ) {
        $this->_httpClient = $httpClient;
        $this->getCustomerAddress = $getCustomerAddress;
        $this->customerRepository = $customerRepository;
        $this->eavAttributeRepository = $eavAttributeRepository;
        $this->countryFactory = $countryFactory;
        $this->scopeConfig = $scopeConfig;
        $this->groupRepository = $groupRepository;  
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
        return $result;
        
    }

    public function pushOrderToIR($orderData)
    {
        $authorization = $this->scopeConfig->getValue("sales_order_partner/ir_conversion/authorization");
        $url = $this->scopeConfig->getValue("sales_order_partner/ir_conversion/url");
        $brandId = $this->scopeConfig->getValue("sales_order_partner/ir_conversion/brand_id");
        if(!empty($authorization) && !empty($url) && !empty($brandId)){
            $params = $this->getOrderPushParams($orderData);
            $encodedParams = json_encode($params, JSON_UNESCAPED_SLASHES);
            $header = array(
                "accept: application/json",
                "content-type: application/json", 
                "x-api-key: " . $authorization,
                "x-brand-id: " . $brandId
            );
            $orderPush = $this->apiCaller($url, \Zend_Http_Client::POST, $encodedParams, $header);
            if(!empty($orderPush)){
                return json_decode($orderPush,true);
            }
            return [];
        }else{
            return ['api_error' => 'Authorization/Url Configuration not configured'];
        }
    }

    private function getOrderPushParams($orderData = NULL){
        $params = [];
        if($orderData !== NULL){
            $campaignId = $this->getCampaignId();
            $params['order_id'] = (int)$orderData['increment_id'];
            $params['campaign_id'] = (int)$campaignId;
            $params['event'] = 'sale';
            $params['referrer_unique_code'] = $orderData['coupon_code'];
            $params['referee_name'] = $orderData['customer_firstname'];
            $params['referee_mobile'] = $orderData['mobilenumber'];
            $params['purchase_value'] = $orderData['grand_total'];
        }
        return $params;
    }

    private function getCampaignId(){
        $campaignId = $this->scopeConfig->getValue("sales_order_partner/ir_conversion/campaign_id");
        $campId = !empty($campaignId) ? $campaignId : 0;
        return $campId;
    }
}