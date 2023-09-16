<?php
namespace Ibo\RegionalPricing\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
//use Magento\Catalog\Api\TierPriceStorageInterface;
//use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Customer\Model\Session as CustomerSession;
use \Ibo\RegionalPricing\Model\PriceZoneByPostalcode;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ProductRepository;

class PriceByPostcode
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
    * @var Curl
    */
    protected $curl;

    /**
     * @var TierPriceStorageInterface
     */
    //private $tierPrice;

    /**
     * @var GroupRepositoryInterface
     */
   // private $groupRepository;
    
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Product
     */

    protected $_product;

    /**
     * @var PriceZoneByPostalcode
     */
    private $priceZoneByPostalcode;

    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var ProductRepository
     */
    protected $_productRepository;

    /**
     * @var Construct
     * @param string $logger
     * @param string $action
     * @param string $storeManager
     * @param string $scopeConfigInterface
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        Curl $curl,
        //TierPriceStorageInterface $tierPrice,
        //GroupRepository $groupRepository,
        CustomerSession $customerSession,
        PriceZoneByPostalcode $priceZoneByPostalcode,
        ResourceConnection $resources,
        ProductRepository $productRepository
    ) {
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->curl = $curl;
        //$this->tierPrice = $tierPrice;
        //$this->groupRepository = $groupRepository;
        $this->customerSession = $customerSession;
        $this->priceZoneByPostalcode = $priceZoneByPostalcode;
        $this->resources = $resources;
        $this->connection = $this->resources->getConnection();
        $this->_productRepository = $productRepository;
    }
    /**
     * @inheritdoc
     */
    public function getItemPrice($quote)
    { 
        $this->addLog('Start Item Price');
        try {
            if(!empty($quote->getPostalCode()))
            {
                $postalCode = $quote->getPostalCode();
            }

            $priceZone = $this->getPriceZoneByPostcode($postalCode);

            $quoteData = $quote->getItemsCollection();
            foreach($quoteData as $item) { 
                $sku = $item['sku'];
                $qty = $item['qty'];
                $quoteId = $item['quote_id'];
                $tierPrice = $this->getTierPrice($sku, $priceZone, $qty);

                $item->setCustomPrice($tierPrice);
                $item->setOriginalCustomPrice($tierPrice);
                $item->setBasePriceInclTax($tierPrice);
                $item->getProduct()->setIsSuperMode(true);
                $item->save(); 
     
            }
            

        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
        }
        $this->addLog('End Item Price');
    }

    /**
     * tier price result
     *
     * @param array $sku
     * @return Price
     */
    public function getTierPrice($sku, $priceZone, $qty)
    {  
        $result = [];
        $defaultCustomerGroup = $this->getDefaultGroupName();
        $customerGroup = $this->getCurrentCustomerGroup();
        $defaultPriceZone = $this->getDefaultPriceZone();
        $zoneWithGroupPriceVal = '';
        $defaultZoneWithGroupPriceVal = '';
        $basePriceVal = '';
        $tierPriceFinalVal = [];
        $this->addLog('Current Customer Group:'.$customerGroup. '  Price Zone:'.$priceZone.' Qty:'.$qty );
        try {
            $sql = "SELECT `value` FROM `catalog_product_entity_tier_price` WHERE `entity_id` = (SELECT `entity_id` FROM `catalog_product_entity` WHERE `sku` = $sku) AND `customer_zone` = '$priceZone' AND `customer_group_id` = $customerGroup AND `qty` <= $qty  ORDER BY `qty` DESC" ;
            $resultVal = $this->connection->fetchAll($sql);

            if(!empty($resultVal)) {
                $tierPriceFinalVal = $resultVal[0]['value'];
            }
            else{
                $defaultZoneSql = "SELECT `value` FROM `catalog_product_entity_tier_price` WHERE `entity_id` = (SELECT `entity_id` FROM `catalog_product_entity` WHERE `sku` = $sku) AND `customer_zone` = '$defaultPriceZone' AND `customer_group_id` = $customerGroup AND `qty` <= $qty ORDER BY `qty` DESC" ;
                $defaultZoneResultVal = $this->connection->fetchAll($defaultZoneSql);
                if(!empty($defaultZoneResultVal)) {
                   $tierPriceFinalVal = $defaultZoneResultVal[0]['value'];
                }else{
                   $tierPriceFinalVal = $this->getPriceBySku($sku);
                }
            }
            $this->addLog('Tier Final Price: '.$tierPriceFinalVal);

            return $tierPriceFinalVal;

            // Commented tier price data by tier price factory
            /*
            $result = $this->tierPrice->get($sku);
            if (count($result)) {
                foreach ($result as $item) { 

                    $priceData = $item->getData();   
                    
                    if(($priceData['customer_zone'] == $priceZone) && ($priceData['customer_group'] == $customerGroup) && ($qty >= $priceData['quantity'])) 
                    { 
                       $zoneWithGroupPriceVal = $priceData['price'];
                    }
                    else if(($priceData['customer_zone'] == $defaultPriceZone) && ($priceData['customer_group'] == $customerGroup) && ($qty >= $priceData['quantity'])) {
                         $defaultZoneWithGroupPriceVal = $priceData['price'];
                    }
                    else{
                        $basePriceVal = $this->getPriceBySku($sku);
                    }
                } 

                if(!empty($zoneWithGroupPriceVal)) { 
                    $tierPriceFinalVal = $zoneWithGroupPriceVal;
                }
                else if(!empty($defaultZoneWithGroupPriceVal)) {
                    $tierPriceFinalVal = $defaultZoneWithGroupPriceVal;
                }
                else {
                    $tierPriceFinalVal = $basePriceVal;
                }
                $this->addLog('Tier Final Price: '.$tierPriceFinalVal);
                return $tierPriceFinalVal;                  
            */

        } catch (\Exception $exception) {
            $this->addLog($exception->getMessage());
        }
    }

    public function getDefaultGroupName()
    {
        $defaultGroupId = $this->scopeConfigInterface->getValue("customer/create_account/default_group");
        //$group = $this->groupRepository->getById($defaultGroupId);
        return $defaultGroupId; //$group->getCode();
    }

    public function getDefaultPriceZone()
    {
        $defaultPriceZone = $this->scopeConfigInterface->getValue("regional_pricing/setting/default_zone");
        return strtolower($defaultPriceZone);
    }

    public function getCurrentCustomerGroup() {
       if($this->customerSession->isLoggedIn()){
            $customerGroupId = $this->customerSession->getCustomer()->getGroupId();
           // $group = $this->groupRepository->getById($customerGroupId);
            return $customerGroupId;//$group->getCode();
        }
        else{
            return $this->getDefaultGroupName();
        }
    }

    public function getPriceBySku($sku)
    {
        $product = $this->_productRepository->get($sku);
        $productPriceBySku = $product->getPrice();
        return $productPriceBySku;
    }

    public function getPriceZoneByPostcode($postalCode) {
        $regionalPricingStatus = $this->scopeConfigInterface->getValue("regional_pricing/setting/active");
        if($regionalPricingStatus == 1) {
            $priceZone = $this->priceZoneByPostalcode->makePostCodeApiCall($postalCode);
        }else {
            $priceZone = strtolower($this->getDefaultPriceZone());
        }
        return $priceZone;
    }

    public function addLog($logData, $filename = "regionalPricing.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {

        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }

}
