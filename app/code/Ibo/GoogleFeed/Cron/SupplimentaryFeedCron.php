<?php

namespace Ibo\GoogleFeed\Cron;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem;

class SupplimentaryFeedCron
{
    /**
     * @var ProductCollection
     */
    protected $productCollection;

    /**
     * @var productRepository
     */
    protected $productRepository;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param ProductSync $productSync
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        \Magento\Framework\Filesystem\DirectoryList $directory,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        Filesystem $filesystem
    ) {
        $this->productCollection = $productCollection;
        $this->productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->groupManagement = $groupManagement;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function isFeedEnabled()
    {
        $status = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/google_feed_status");
        return $status;
    }

    public function isLogEnabled()
    {
        $status = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/google_feed_log_status");
        return $status;
    }

    public function getIboCategoriesId(){
        $boCatIds =  $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/primary_feeder_ibo_category_id");
        
        return explode(",",$boCatIds);
    }

    /**
     * Sync products to Facade.
     */
    public function execute()
    {
        $feedEnable = $this->isFeedEnabled();
        if ($feedEnable) {

            $this->addLog('Entered Supplimentary Feed Cron');

            $fileName = 'supplimentary-google-feed.txt'; //textfile name
            $stream = $this->directory->openFile($fileName, 'w+');
            $stream->lock();

            $header = "id"."\t"."availability"."\t"."price"."\t"."sale price"."\n";
            $stream->write($header);

            $collection = $this->productCollection->addAttributeToSelect(
                ['sku','allowed_channels','price','mrp','status','type_id','is_published','ibo_category_id']
            );

            if(count($this->getIboCategoriesId())>0){
                $collection->addAttributeToFilter('ibo_category_id',array($this->getIboCategoriesId()));
            }

            $collection = $collection->addAttributeToFilter(
                'status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
            );
            $collection = $collection->addAttributeToFilter(
                'type_id',
                \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
            );
            $collection = $collection->setFlag('has_stock_status_filter', false)
                            ->joinField(
                                'stock_items',
                                'cataloginventory_stock_item',
                                'is_in_stock',
                                'product_id=entity_id',
                                'is_in_stock=1'
                            );

            $this->addLog('Collection Count : '.count($collection));
            foreach ($collection as $productData) {
                $offerId = '';
                $availability = 'in stock';
                $mrpprice = '';
                $saleprice = '';

                $product = $this->productRepository->get($productData->getSku(), false, null, true);

                if (($productData->getSku() != '') && (strlen($productData->getSku()) <= 50)) {
                    $offerId = $productData->getSku();
                }

                if ($productData->getPrice() != '') {

                    $customerGroupId = $this->_scopeConfig->getValue("customer/create_account/default_group");
                    $defaulZone = $this->_scopeConfig->getValue("regional_pricing/setting/default_zone");

                    $priceKey = 'website_price';
                    $value = $this->_scopeConfig->getValue(
                        'catalog/price/scope',
                        \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE
                    );
                    if ($value == 0) {
                        $priceKey = 'price';
                    }

                    $cgi = ($customerGroupId === 'all'
                            ? $this->groupManagement->getAllCustomersGroup()->getId()
                            : $customerGroupId);

                    $prices = [];
                    $tierPriceData = $product->getData('tier_price');
                    if (is_countable($tierPriceData) && (count($tierPriceData) > 0)) {
                        $saleprice = $this->getPriceByTierPrice(
                            $tierPriceData,
                            $customerGroupId,
                            $defaulZone
                        );
                    }

                    if ($saleprice == '') {
                        $finalPrice = $product->getFinalPrice();
                        $saleprice = (float)number_format((float)($finalPrice), 2, '.', '');
                    }
                }

                if (($productData->getMrp() != '') && ($productData->getMrp() != 0)) {
                    $mrpprice = (float)number_format((float)($productData->getMrp()), 2, '.', '');
                } else {
                    $mrpprice = $saleprice;
                }

                if (($offerId != '') && ($mrpprice != '') && ($saleprice != '') && ($mrpprice != '0')
                    && ($saleprice != '0') && ($productData->getAllowedChannels() != '')
                    && ($productData->getAllowedChannels() != 'STORE')
                    && ($productData->getIsPublished())) {
                    
                     $txtVal = $offerId."\t".$availability."\t".$mrpprice."\t"
                                .$saleprice."\n";
                                $stream->write($txtVal);
                } else {
                    $this->addLog('Product Sku not satisfying the feed mantadory fields : '.$offerId);
                }
            }
            $stream->unlock();
            $stream->close();
            unset($collection);
            $this->addLog('End Data');
        }
    }

    public function getPriceByTierPrice($tierPriceData, $customerGroupId, $defaulZone)
    {
        $saleprice = '';
        foreach ($tierPriceData as $price) {
            if (($price['cust_group'] == $customerGroupId)
                && ($price['customer_zone'] == $defaulZone)
                && ((int)$price['price_qty'] == 1)) {
                $saleprice = (float)number_format((float)($price['price']), 2, '.', '');
            }
        }
        return $saleprice;
    }

    public function addLog($logData)
    {
        $filename = "google-supplimentary-feed.log";
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {
        $logEnable = $this->isLogEnabled();
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $logEnable;
    }
}

