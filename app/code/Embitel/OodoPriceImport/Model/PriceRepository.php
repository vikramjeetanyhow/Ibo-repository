<?php

namespace Embitel\OodoPriceImport\Model;

use Embitel\OodoPriceImport\Api\PriceRepositoryInterface;
use Embitel\OodoPriceImport\Api\Data\OodoPriceInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Ibo\RegionalPricing\Model\Config\Source\Region as PriceZones;

class PriceRepository implements PriceRepositoryInterface
{
    protected $oodoPrice;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param OodoPriceInterfaceFactory $oodoPrice
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer
     * @param File $fileDriver
     * @param DirectoryList $directoryList
     */
    public function __construct(
        OodoPriceInterfaceFactory $oodoPrice,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serializer,
        File $fileDriver,
        DirectoryList $directoryList,
        GroupManagementInterface $groupManagement,
        StoreManagerInterface $storeManager,
        PriceZones $priceZones
    ) {
        $this->oodoPrice = $oodoPrice;
        $this->_scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->fileDriver = $fileDriver;
        $this->directoryList = $directoryList;
        $this->groupManagement = $groupManagement;
        $this->storeManager = $storeManager;
        $this->priceZones = $priceZones;
    }
    
    /**
     * Save Oodo Price data
     *
     * @param mixed $price
     * @return bool
     * @throws CouldNotSaveException
     */
    public function save($price)
    {
        try { 
            //Commenting $basePriceGroup as such the base price will now be decided upon highest price for an offer id
            /*$basePriceGroup = $this->groupManagement
                                   ->getDefaultGroup()
                                   ->getCode();*/

            /*$offerIds = array_column($price, 'offer_id');
            array_multisort($offerIds, SORT_REGULAR, $price);*/

            /** commenting lock to support uninterrupted push from oodo **/
            /*$lockExists = $this->checkLockExists();
            if ($lockExists) {
                throw new \Magento\Framework\Webapi\Exception(
                 __(
                   'Import In Progress please try after Sometime'
                 ), 0, \Magento\Framework\Webapi\Exception::HTTP_FORBIDDEN);
            }*/
            $this->addLog("IN SAVE FUNCTION");
            $data = [];
            $priceData = [];
            $tierPrices = [];
            $zoneList =  $this->priceZones->toOptionArray();
            if (empty($zoneList)) {
                throw new LocalizedException(__('Zones are not defined in Magento, hence cannot import prices'));
            }
            $defaultZone = $this->_scopeConfig
                            ->getValue("regional_pricing/setting/default_zone",
                                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                                    $this->storeManager->getStore()->getStoreId()
                                    );
            $zoneData = array_column($zoneList, 'value');
            $definedPrice = [];
            $definedPrice = ['DEFINED','DERIVED'];
            
            foreach ($price as $priceData) {
                $customerZone = strtolower($priceData['customer_zone']);
                if (!in_array($customerZone, $zoneData)) {
                    throw new LocalizedException(__('Price Data was not saved for '.$priceData['offer_id'].' because the customer_zone '.$customerZone.' is not existing in magento'));
                }
                $definedpriceData = $priceData['is_defined_price'];
                if (!in_array($definedpriceData, $definedPrice)) {
                    throw new LocalizedException(__('Defined Price Data was not saved for '.$priceData['offer_id'].' because the defined price data '.$definedpriceData.' value is not correct'));
                }
                //Commenting $basePriceGroup as such the base price will now be decided upon highest price for an offer id
                /*if ($priceData['price_group'] == $basePriceGroup
                 && $priceData['min_quantity'] == 1 && $customerZone == $defaultZone){
                    $basePrices = [
                      'price' => $priceData['price_without_gst'],
                      'store_id' => 0,
                      'sku' => $priceData['offer_id']
                    ];
                    $oodoData[$priceData['offer_id']]['base_price'] = $basePrices;             
                }   */       
                $tierPrices = [
                    'price' => $priceData['price_without_gst'],
                    'price_type' => 'fixed',
                    'website_id' => 0,
                    'sku' => $priceData['offer_id'],
                    'customer_group' => $priceData['price_group'],
                    'quantity' => $priceData['min_quantity'],
                    'extension_attributes' => ['customer_zone' => $customerZone, 'is_defined_price' => $definedpriceData]
                ];
                $oodoData[$priceData['offer_id']][] = $tierPrices;
            }   
            $basePriceData = [];        
            foreach ($oodoData as $key => $prices) {
                $oodoPriceData = [];
                $oodoPriceData['offer_id'] = $key;
                $searchBasePrice = array_column($prices, 'price');
                $basePricekey = array_search(max($searchBasePrice), $searchBasePrice);
                if (!isset($oodoPriceData['base_price_data'])) {
                    $basePriceData = [
                      'price' => $prices[$basePricekey]['price'],
                      'store_id' => 0,
                      'sku' => $key
                    ];
                    $oodoPriceData['base_price_data'] = 
                    $this->serializer->serialize($basePriceData);
                    $this->addLog('base Price of offer Id:'. $key);
                    $this->addLog($basePriceData );
                    $this->addLog('-------------------------');
                    /*unset($prices['base_price']);*/
                } /*else {
                    throw new LocalizedException(__('Price record for '.$key.' cannot be imported as Default price not present for '.$basePriceGroup));
                }*/
                $oodoPriceData['tier_prices_data']= $this->serializer->serialize(array_values($prices));
                $this->addLog('Slab base of offer Id:'. $key);
                $this->addLog($prices);
                $this->addLog('-------------------------');
                $oodoPrice = $this->oodoPrice->create();
                /** load offer id and check if it exists 
                if exists then replace the record - mohit pandit **/
                $oodoPrice->load($key, 'offer_id');
                if ($oodoPrice->getPriceId())
                {
                    $oodoPriceData['price_id'] = $oodoPrice->getPriceId();
                }
                $oodoPrice->setData($oodoPriceData);
                $oodoPrice->save();                          
            }

        } catch (LocalizedException $exception) {
            throw new CouldNotSaveException(
                __('Could not save the Price Data: %1', $exception->getMessage()),
                $exception
            );
        } 
        return true;
    }

    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            if (is_array($logdata)) {
                $this->logger->info(
                    print_r((new \Magento\Framework\DataObject(['slab_prices' => $logdata]))->debug(),true));
            } else {
                $this->logger->info($logdata);
            }
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->_scopeConfig->getValue(
                "tierprice_cron/cron_settings/statusupdate_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/tier-price-db.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }

    /** commenting lock to support uninterrupted push from oodo **/
    /*public function checkLockExists()
    {
        $varDir = $this->directoryList->getPath('var');
        $fileName = $varDir.'/lock.txt';
        if ($this->fileDriver->isExists($fileName)) {
            return true;
        } else {
            return false;
        }
    }*/
}
