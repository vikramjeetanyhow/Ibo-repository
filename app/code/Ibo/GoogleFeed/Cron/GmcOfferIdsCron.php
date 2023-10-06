<?php

namespace Ibo\GoogleFeed\Cron;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Eav\Model\Config;
use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem;

class GmcOfferIdsCron
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ProductCollection
     */
    protected $productCollection;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var Model/Config
     */
    protected $eavConfig;

    /**
     * @param ProductSync $productSync
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface,
        TransportBuilder $transportBuilder,
        Config $eavConfig,
        Filesystem $filesystem
    ) {
        $this->productCollection = $productCollection;
        $this->productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_storeManager = $storeManagerInterface;
        $this->_transportBuilder = $transportBuilder;
        $this->eavConfig = $eavConfig;
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
            $path = 'gmc_offer_ids.csv';
            $stream = $this->directory->openFile($path, 'w+');
            $stream->lock();
            $this->addLog('Entered GMC Offer Ids Cron Path : '.$path);

            $header = ['offer_id'];
            $stream->writeCsv($header);

            $collection = $this->productCollection->addAttributeToSelect(
                ['sku',
                'allowed_channels',
                'is_published',
                'price',
                'status',
                'type_id',
                'availability_zone',
                'ibo_category_id']
            );
            if(count($this->getIboCategoriesId())>0){
                $collection->addAttributeToFilter('ibo_category_id',array($this->getIboCategoriesId()));
            }
            $collection->addAttributeToFilter(
                'status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
            )->addAttributeToFilter(
                'type_id',
                \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
            )->addAttributeToFilter(
                'allowed_channels',
                array('neq' => 'STORE')
            )->setFlag('has_stock_status_filter', false);
                    $collection->joinField(
                        'stock_item',
                        'cataloginventory_stock_item',
                        'is_in_stock',
                        'product_id=entity_id',
                        'is_in_stock=1'
                    );
            $this->addLog('Collection Count : '.count($collection));

            $isGenerated = 0;
            $count = 0;
            foreach ($collection as $productData) {
                $offerId = '';
                $csvData = [];

                $product = $this->productRepository->get($productData->getSku(), false, null, true);
                if ((trim($productData->getSku()) != '') && (strlen($productData->getSku()) <= 50)) {
                    $offerId = trim($productData->getSku());
                }

                if (($offerId != '')
                    && ($productData->getAllowedChannels() != '')
                    && ($productData->getAllowedChannels() != 'STORE')
                    && ($productData->getIsPublished())) {

                    $isGenerated = 1;
                    /*$availabilityZoneData = $productData->getResource()
                    ->getAttribute('availability_zone')
                    ->getFrontend()
                    ->getValue($productData);*/
                    $availabilityZoneData = $product->getAttributeText('availability_zone');

                    if ((!is_array($availabilityZoneData)) && $availabilityZoneData !='') {
                        $availJson = '["'.$availabilityZoneData.'"]';
                        $availabilityZoneData = json_decode($availJson, true);
                    }

                    if (empty($availabilityZoneData)) {

                        $attribute = $this->eavConfig->getAttribute('catalog_product', 'availability_zone');
                        $availabilityZoneOptions = $attribute->getSource()->getAllOptions();
                        $availabilityZoneData = array_column($availabilityZoneOptions, 'label');
                        $availabilityZoneData =  array_values(array_filter(
                            array_map('trim', $availabilityZoneData),
                            'strlen'
                        ));
                        $availabilityZoneData[] = 'RestofIndia';
                    }
                    if (is_array($availabilityZoneData) || is_object($availabilityZoneData)) {
                        $csvData[] = $offerId;
                        $stream->writeCsv($csvData);
                        $count++;
                    }

                } else {
                    $this->addLog('Not Satisfied : '.$offerId);
                }
            }
            $this->addLog(' Count: '.$count);
            unset($collection);
            if ($isGenerated == 1) {
                $this->sendEmail($count);
            }
            $this->addLog('End Data');
        }
    }

    /**
     * Send Email
     *
     * @param Mixed $emailBody
     * @return void
     */

    public function sendEmail($count)
    {
        $isEnable = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/offerid_csv_email_status");
        if ($isEnable == 1) {
            $emailBody = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/offerid_csv_email_body");
            $this->_inlineTranslation->suspend();
            $emailTemplateVariables = [
                'message' => $emailBody.' '.$count
            ];
            $storeScope = ScopeInterface::SCOPE_STORE;
            $receiverInfo = [
                'email' => $this->_scopeConfig->getValue(
                    "ibo_google_feed/google_feed_settings/offerid_csv_email_address"
                )
            ];
            $receiverInfoEmail = array_map('trim', explode(',', $receiverInfo['email']));

            $senderEmail = $this->_scopeConfig->getValue(self::SENDER_EMAIL, $storeScope);
            $senderName = $this->_scopeConfig->getValue(self::SENDER_NAME, $storeScope);
            $senderInfo = [
                'name' => $senderName,
                'email' => $senderEmail,
            ];

            $this->addLog('email Sent');

            $storeId = (int)$this->_storeManager->getStore()->getId();
            $transport = $this->_transportBuilder->setTemplateIdentifier('offerid_csv_generation')
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ]
                )->setTemplateVars($emailTemplateVariables)
                ->setFrom($senderInfo)
                ->addTo($receiverInfoEmail)
                ->getTransport();
            try {
                $transport->sendMessage();
                $this->addLog('Email Success');
            } catch (\Exception $e) {
                $this->addLog('Email error: '.$e);
            }
            $this->_inlineTranslation->resume();
        } else {
            $this->addLog('Email Disabled');
        }
    }

    public function addLog($logData)
    {
        $filename = "gmc_offerIds.log";
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {
        $logEnable = $this->isLogEnabled();
        ;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $logEnable;
    }
}
