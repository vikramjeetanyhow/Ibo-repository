<?php
namespace Embitel\Oodo\Model;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\CustomerGraphQl\Model\Customer\Address\GetCustomerAddress;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Embitel\Oodo\Helper\Logger;

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($httpcode == 200){
            return $result;
        }else{
            return json_encode(['api_error' => 'HTTP Code - ' . $httpcode . ' with response - ' . $result]);
        }

    }

    public function pushCustomerToOodo($customer)
    {
        $authorization = $this->scopeConfig->getValue("oodo/customer_push/authorization");
        $url = $this->scopeConfig->getValue("oodo/customer_push/url");
        if(!empty($authorization) && !empty($url)){
            $params = $this->getCustomerPushParams($customer);
            $encodedParams = json_encode($params, JSON_UNESCAPED_SLASHES);
            $header = array(
                "Content-Type: application/json",
                "Content-Lenght: " . strlen($encodedParams),
                "Authorization: " . $authorization
            );
            $oodoPush = $this->apiCaller($url, \Zend_Http_Client::POST, $encodedParams, $header);
            if(!empty($oodoPush)){
                return json_decode($oodoPush,true);
            }
            return [];
        }else{
            return ['api_error' => 'Authorization/Url Configuration not configured'];
        }
    }

    private function getGroupName($groupId){
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCountryname($countryCode){
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }


    private function getCustomerAttributeValue($customer, $attributeCode){
        if(empty($this->getAttributeOptions[$attributeCode])){
            $attributes = $this->eavAttributeRepository->get(\Magento\Customer\Model\Customer::ENTITY, $attributeCode);
            $this->getAttributeOptions[$attributeCode] = $attributes->getSource()->getAllOptions(false);
        }
        $optionValues = [];
        if(!empty($this->getAttributeOptions[$attributeCode])){
            $customOptionsIds = $customer->getCustomerType();
            if(!empty($customOptionsIds)){
                $customOptionsIds = explode(',', $customOptionsIds);
                array_walk($this->getAttributeOptions[$attributeCode], function($value, $key) use(&$optionValues, $customOptionsIds){
                    if(isset($value['value']) && in_array($value['value'], $customOptionsIds)){
                        $optionValues[] = isset($value['label']) ? $value['label'] : '';
                    }
                });
            }
        }
        return implode(',', $optionValues);
    }

    private function getCustomerPushParams($customer = NULL){
        $params = [];
        if($customer !== NULL){
            $customerId = $customer->getId();

            $mobileNumber = $customer->getMobilenumber();
            $params['customer_id'] = $customerId;
            $params['customer_type'] = $this->getCustomerAttributeValue($customer, 'customer_type');
            $params['customer_segament'] = $this->getGroupName($customer->getGroupId());
            $params['first_name'] = $customer->getFirstname();
            $params['middle_name'] = !empty($customer->getMiddlename()) ? $customer->getMiddlename() : '';
            $params['last_name'] = !empty($customer->getLastname()) ? $customer->getLastname() : '';
            $params['number'] = !empty($mobileNumber) ? $mobileNumber : '';
            $params['email_id'] = $customer->getEmail();
            $params['customer_zone'] = 'default';
            $params['gstn_number'] = !empty($customer->getTaxvat()) ? $customer->getTaxvat() : '';
            $customer->getPrimaryAddresses();
            $addresses = $customer->getAddresses();
            $tradeName = '';
            if(!empty($addresses)){
                foreach($addresses as $address){
                    $landMark   = $address->getLandmark();
                    $street     = $address->getStreet();
                    $region     = $address->getRegion();
                    $city       = $address->getCity();
                    $postCode   = $address->getPostcode();
                    $countryId  = $address->getCountryId();
                    $name       = trim($address->getFirstname() . ' ' . $address->getLastname());
                    $type       = ($address->getIsPrimaryBilling()) ? "invoice" : "contact";
                    $street1    = !empty($street[0]) ? $street[0] : '';
                    $street2    = !empty($street[1]) ? $street[1] : '';
                    $street3    = !empty($street[2]) ? $street[2] : '';
                    $streets    = [$street2, $street3];
                    if($address->getIsPrimaryBilling()) {
                        $params['address_line1'] = $street1;
                        $params['address_line2'] = $street2;
                        $params['address_line3'] = $street3;
                        $params['city'] = !empty($city) ? $city : '';
                        $params['state'] = !empty($region) ? $region : '';
                        $params['zip'] = !empty($postCode) ? $postCode : '';
                        $params['country'] = !empty($countryId) ? $this->getCountryname($countryId) : '';
                        $params['post_code'] = !empty($postCode) ? $postCode : '';
                        $params['landmark'] = !empty($landMark) ? $landMark : '';
                        $tradeName = trim($address->getFirstname() . ' ' . $address->getLastname());
                    }
                    if($address->getIsPrimaryShipping()){
                        $params['outlet_street']  = $street1;
                        $params['outlet_street2'] = $street2;
                        $params['outlet_street3'] = $street3;
                        $params['outlet_city'] = !empty($city) ? $city : '';
                        $params['outlet_state'] = !empty($region) ? $region : '';
                        $params['outlet_zip'] = !empty($postCode) ? $postCode : '';
                        $params['outlet_country'] = !empty($countryId) ? $this->getCountryname($countryId) : '';
                        $params['outlet_landmark'] = !empty($landMark) ? $landMark : '';
                    }
                    $params['address_line'][] = [
                        'name' => $name,
                        'phone' => $address->getTelephone(),
                        "type" => $type,
                        'customer_id' => $address->getId(),
                        'street' => $street1,
                        'street2' => implode(',',$streets),
                        'city' => !empty($city) ? $city : '',
                        'country_id' => $this->getCountryname($countryId),
                        'state_id' => !empty($region) ? $region : '',
                        'zip' => !empty($postCode) ? $postCode : '',
                        'email' => $customer->getEmail()
                    ];
                }
            }
            $params['trade_name'] = !empty($tradeName) ? $tradeName : '';
        }
        return $params;
    }
}
