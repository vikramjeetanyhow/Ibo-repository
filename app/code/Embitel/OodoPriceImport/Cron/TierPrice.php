<?php
/**
 * Copyright © Embitel All rights reserved.
 * See COPYING.txt for license details.
 * Oodo Price import Cron - Mohit Pandit
 */
declare(strict_types=1);

namespace Embitel\OodoPriceImport\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\OodoPriceImport\Model\ResourceModel\OodoPrice\CollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Catalog\Api\Data\TierPriceInterfaceFactory as TierPriceData;
use Magento\Catalog\Api\TierPriceStorageInterface as TierPriceStorage;
use Magento\Catalog\Api\BasePriceStorageInterface as BasePriceStorage;
use Magento\Catalog\Api\Data\BasePriceInterfaceFactory as BasePriceData;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Filesystem;
use Magento\Framework\Exception\LocalizedException;
use Embitel\Catalog\Helper\Data as CatalogHelper;

class TierPrice
{

    protected $logger; 
    /**
     *
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $_jsonSerializer;   

    /**
     * @var EventManager
     */
    private $_eventManager;

    /**
    * @param CollectionFactory $collectionFactory    
    * @param ScopeConfigInterface $scopeConfig
    * @param DataObjectHelper $dataObjectHelper
    * @param TierPriceData $tierPriceData
    * @param TierPriceStorage $tierPriceStorage
    * @param BasePriceData $basePriceData
    * @param BasePriceStorage $basePriceStorage
    * @param JsonSerializer $jsonSerializer
    * @param DateTime $dateTime
    * @param File $fileDriver
    * @param DirectoryList $directoryList
    * @param EventManager $eventManager
    * @param Filesystem $fileSystem
    */
    public function __construct(
        CollectionFactory $collectionFactory,    
        ScopeConfigInterface $scopeConfig,
        DataObjectHelper $dataObjectHelper,
        TierPriceData $tierPriceData,
        TierPriceStorage $tierPriceStorage,
        BasePriceData $basePriceData,
        BasePriceStorage $basePriceStorage,
        JsonSerializer $jsonSerializer,
        DateTime $dateTime,
        File $fileDriver,
        DirectoryList $directoryList,
        EventManager $eventManager,
        Filesystem $fileSystem,
        CatalogHelper $catalogHelper
    ) {
        $this->collectionFactory =  $collectionFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->tierPriceData = $tierPriceData;
        $this->tierPriceStorage = $tierPriceStorage;
        $this->basePriceData = $basePriceData;
        $this->basePriceStorage = $basePriceStorage;
        $this->_jsonSerializer = $jsonSerializer;
        $this->dateTime = $dateTime;
        $this->fileDriver = $fileDriver;
        $this->directoryList = $directoryList;
        $this->_eventManager = $eventManager;
        $this->fileSystem = $fileSystem;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->addLog("Cronjob Tier Price executed");
        $isCronEnable = $this->_scopeConfig->getValue("tierprice_cron/cron_settings/cron_status_update");
        if (!$isCronEnable) {
            $this->addLog("Cronjob Tier Price update is disabled.");
            return;
        }
        $fiveMinutes = $this->getDateAndTime();
        
        $tierCollection = $this->collectionFactory->create();
        $tierCollection->addFieldToFilter('created_at',['lteq'=>$fiveMinutes]);
        $tierCollection->getSelect()
                       ->order('offer_id','ASC')
                       ->group('offer_id')
                       ->limit(500);
        try { 
            $prices = [];
            $basePrices = [];
            $basePriceRequests = []; 
            $requests = [];           
            if($tierCollection->getSize() >= 1) {
                /** commenting lock to support uninterrupted push from oodo **/
                /*$this->acquireLock();*/
                $basePriceCollection = clone $tierCollection;
                foreach ($tierCollection as $tierPrice) {          
                    $prices = $this->_jsonSerializer
                       ->unserialize($tierPrice->getTierPricesData());
                    $this->addLog('Tier/Slab Price of '.$tierPrice->getOfferId().':');
                    $this->addLog($prices);
                    $this->addLog('-----------------');
                    foreach ($prices as $price) {
                        $requestDataObject = $this->tierPriceData->create();
                        $this->dataObjectHelper->populateWithArray(
                            $requestDataObject,
                            $price,
                            \Magento\Catalog\Api\Data\TierPriceInterface::class
                        );
                        $requests[] = $requestDataObject; 
                    } 
                    $productAttribute['price'] = 'price';
                    $this->catalogHelper->updateSeldate($tierPrice->getOfferId(),$productAttribute);          
                }                
                $updatedTiers =  $this->tierPriceStorage->replace($requests);
                $this->addLog('---Tier Price Failure Data---');
                $this->addLog($updatedTiers);                
                $this->addLog('-----------------');
                
                $basePriceColl = $basePriceCollection
                                ->addFieldToSelect('base_price_data')
                                ->addFieldToSelect('offer_id');
                foreach ($basePriceColl as $basePriceCol) {
                    if ($basePriceCol->getBasePriceData() != null) {
                        $basePriceRequests[] = $this->prepareBasePriceDataObject($basePrices,$basePriceCol);
                    } else {
                        $this->addLog('BASE PRICE RECORD NOT PASSED FROM OODO FOR: '.$basePriceCol->getOfferId());
                    }        
                }
                if(!empty($basePriceRequests)) {
                        $updatedBasePrices =  $this->basePriceStorage->update($basePriceRequests);
                        $this->addLog('---Base Price Failure Data---');
                        $this->addLog($updatedBasePrices);
                }

                //Below event is used to add products to enable & publish cron queue.
                //Event observer is added in the Embitel_ProductImport module.
                $offerIds = array_column($tierCollection->getData(), 'offer_id');
                $this->_eventManager->dispatch('odoo_price_update_after',
                    [
                        'offer_ids' => $offerIds
                    ]
                );
                $tierCollection->walk('delete');

                /** commenting lock to support uninterrupted push from oodo **/
                /*$this->releaseLock();*/
            }    
        } catch (LocalizedException $exception) {
            /*$this->releaseLock();*/
            $this->addLog($exception->getMessage());
        }              
        $this->addLog("Cronjob Tier Price update is executed.");
    }

    /* Prepare magento specific tier price data */
    protected function prepareTierPriceDataObject($tierPrice) {

        $prices = $this->_jsonSerializer
                       ->unserialize($tierPrice->getTierPricesData());

        $this->addLog('Tier/Slab Price of '.$tierPrice->getOfferId().':');
        $this->addLog($prices);
        $this->addLog('-----------------');
        try {
            foreach ($prices as $price) {
                $requestDataObject = $this->tierPriceData->create();
                $this->dataObjectHelper->populateWithArray(
                    $requestDataObject,
                    $price,
                    \Magento\Catalog\Api\Data\TierPriceInterface::class
                );
                $requestData[] = $requestDataObject; 
            }
        } catch (LocalizedException $exception) {
            $this->addLog($exception->getMessage());     
        }
        return $requestData;
    }

    /* Prepare magento specific base price data */
    protected function prepareBasePriceDataObject($basePrices,$basePrice) {

        $basePrices = $this->_jsonSerializer
                           ->unserialize(
                                $basePrice->getBasePriceData()
                            );
        $this->addLog('Base Price of '.$basePrice->getOfferId().':');
        $this->addLog($basePrices);
        $this->addLog('----------------');
        $baseRequestDataObject = $this->basePriceData->create();
        $this->dataObjectHelper->populateWithArray(
            $baseRequestDataObject,
            $basePrices,
            \Magento\Catalog\Api\Data\BasePriceInterface::class
        );
        return $baseRequestDataObject;
    }

    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/tier-price-update.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            if(is_array($logdata)) {
                $logger->info(
                    print_r((new \Magento\Framework\DataObject(['tier_data' => $logdata]))->debug(),true));
            } else {
                $logger->info($logdata);
            }
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->_scopeConfig->getValue(
                "tierprice_cron/cron_settings/statusupdate_log_active"
            );
        }
        return $this->isLogEnable;
    }

    protected function getDateAndTime()
    {
        $gmtDate = $this->dateTime->gmtDate();
        $timeToDeduct = strtotime($gmtDate);
        $timeToDeduct = $timeToDeduct - (5 * 60);
        $finalDateAndTime = date("Y-m-d H:i:s", $timeToDeduct);

        return $finalDateAndTime;
    }

    public function releaseLock() {
        $varDir = $this->directoryList->getPath('var');
        $fileName = $varDir.'/lock.txt';
        if ($this->fileDriver->isExists($fileName))  {
            $this->fileDriver->deleteFile($fileName);
        }
    }

    public function acquireLock() {
        
        $writer = $this->fileSystem->getDirectoryWrite('var');
        $file = $writer->openFile('lock.txt', 'w+');
        $contents = 'Lock Acquired';
        try {
            $file->lock();
            try {
                $file->write($contents);
            }
            finally {
                $file->unlock();
            }
        }
        finally {
            $file->close();
        }
    }
}