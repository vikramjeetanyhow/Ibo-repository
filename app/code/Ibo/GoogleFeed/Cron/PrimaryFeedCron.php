<?php

namespace Ibo\GoogleFeed\Cron;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Eav\Model\Config;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem;

class PrimaryFeedCron
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';

    /**
     * @var ProductCollection
     */
    protected $productCollection;

    protected $productRepository;

    protected $productFactory;

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
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @param ProductSync $productSync
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Api\GroupManagementInterface $groupManagement,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface,
        TransportBuilder $transportBuilder,
        Config $eavConfig,
        Filesystem $filesystem
    ) {
        $this->productCollection = $productCollection;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->groupManagement = $groupManagement;
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

    public function getIboBaseUrl()
    {
        $url = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/feed_product_base_url");
        return $url;
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
        if ($feedEnable !=1) {
            $this->addLog('Google Feed Module is disabled');
            return;
        }
        $fileName = 'primary-google-feed.txt'; //textfile name
        $stream = $this->directory->openFile($fileName, 'w+');
        $stream->lock();

        /*$header = "id"."\t"."title"."\t"."description"."\t"."link"."\t"."image link"."\t"
        ."condition"."\t"."availability"."\t"."price"."\t"."sale price"."\t"."uom"."\t"
        ."brand"."\t"."item group id"."\t"."product type"."\t"."custom label 0"."\t"
        ."custom label 1"."\t"."custom label 2"."\t"."custom label 3"."\t"
        ."custom label 4"."\t"."gtin"."\t"."identifier_exists"."\n";*/
        $header = "id"."\t"."title"."\t"."description"."\t"."link"."\t"."image link"."\t"
        ."condition"."\t"."availability"."\t"."price"."\t"."sale price"."\t"."uom"."\t"
        ."brand"."\t"."item group id"."\t"."product type"."\t"."custom label 0"."\t"
        ."custom label 1"."\t"."custom label 2"."\t"."custom label 3"."\t"
        ."custom label 4"."\t"."gtin"."\n";
        $stream->write($header);

        $collection = $this->productCollection->addAttributeToSelect('*')
            ->addAttributeToFilter('ibo_category_id',array($this->getIboCategoriesId()));
        $collection = $collection->addAttributeToFilter(
            'status',
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
        );
        $collection = $collection->addAttributeToFilter(
            'type_id',
            \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
        );
        $collection = $collection->addAttributeToFilter(
            'allowed_channels',
             array('neq' => 'STORE')
        );
        $collection = $collection->setFlag('has_stock_status_filter', false)
                        ->joinField(
                            'stock_item',
                            'cataloginventory_stock_item',
                            'is_in_stock',
                            'product_id=entity_id',
                            'is_in_stock=1'
                        );

        $this->addLog('Collection Count : '.count($collection));
        $isGenerated = 0;
        $feedCount = 0;
        $offerId = '';
        $title = '';
        $description = '';
        $productUrl = '';
        $imageUrl = '';
        $condition = 'new';
        $availability = 'in stock';
        $mrpprice = '';
        $saleprice = '';
        $uom = '';
        $brand = '';
        $itemgroupId = '';
        $customlabel1 = '';
        $customlabel2 = '';
        $customlabel3 = '';
        $customlabel4 = '';
        $productType = '';
        $gtin = '';
        //$identifier_exists ='';
        $count = 0;

        foreach ($collection as $productData) {

            $product = $this->productRepository->get($productData->getSku(), false, null, true);
            if ((trim($productData->getSku()) != '') && (strlen($productData->getSku()) <= 50)) {
                $offerId = trim($productData->getSku());
            }

            if (trim($productData->getName()) != '') {
                if (strlen(trim($productData->getName())) >= 150) {
                    $title = substr(trim($productData->getName()), 0, 145).'...';
                } else {
                    $title = trim($productData->getName());
                }
            }

            if (trim($productData->getDescription()) != '') {
                if (strlen(trim(str_replace(
                    ["\r\n", "\r", "\n"],
                    "<br/>",
                    str_replace("\t", "", $productData->getDescription())
                ))) >= 5000) {
                    $description = substr(trim(str_replace(
                        ["\r\n", "\r", "\n"],
                        "<br/>",
                        str_replace("\t", "", $productData->getDescription())
                    )), 0, 4996).'...';
                } else {
                    $description = trim(str_replace(
                        ["\r\n", "\r", "\n"],
                        "<br/>",
                        str_replace("\t", "", $productData->getDescription())
                    ));
                }
            } else {
                $description = $title;
            }

            if ($productData->getUniqueGroupId() != '') {
                if (strlen($productData->getUniqueGroupId()) >= 50) {
                    $itemgroupId = substr($productData->getUniqueGroupId(), 0, 46).'...';
                } else {
                    $itemgroupId = $productData->getUniqueGroupId();
                }
            }

            if (($title != '') && ($product->getEsin() != '')) {
                $baseUrl = $this->getIboBaseUrl();
                $productUrl = $baseUrl.str_replace(' ', '-', strtolower(trim($title))).'/p/'.$product->getEsin();
            }

            $imageUrl = ($productData->getImage()) ? $this->productFactory->create()
            ->getMediaConfig()->getMediaUrl($productData->getImage()) : '';
            $baseImgUrl = '';

            $customSourceImage = $this->_scopeConfig->getValue("core_media/service/use_custom_source");
            if ($customSourceImage) {
                if ($productData->getBaseImageCustom() != '') {
                    $baseImgUrl = $productData->getBaseImageCustom();
                }
            } else {
                $baseImgUrl = $imageUrl;
            }
            $brand = !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '';
            $uom = !empty($product->getAttributeText('sale_uom')) ? $product->getAttributeText('sale_uom') : '';

            $customlabel1 = !empty($productData->getSubclass()) ? $productData->getSubclass() : '';
            $customlabel2 = ($productData->getMetaEboGrading() !== null
            && ($product->getAttributeText('meta_ebo_grading') != ''))
            ?$product->getAttributeText('meta_ebo_grading'):'New';

            $customlabel4 = ($product->getAttributeText('ebo_grading') != '')
            ?$product->getAttributeText('ebo_grading'):'KVI';

            if (($productData->getEan() !='') && (strlen($productData->getEan()) >= 13)) {
                $gtin = $productData->getEan();
                //$identifier_exists = 'Yes';
            } else {
                $gtin = '';
                //$identifier_exists = 'No';
            }

            if (($offerId != '') && ($title != '') && ($productUrl != '')
            && ($baseImgUrl != '') && ($brand != '')
            && ($productData->getAllowedChannels() != '')
            && ($productData->getAllowedChannels() != 'STORE')
            && ($productData->getIsPublished())) {

                $isGenerated = 1;
                $productType = "Home > ".$productData->getDepartment()." > "
                .$productData->getClass()." > ".$productData->getSubclass();

                $availabilityZoneData = $product->getAttributeText('availability_zone');

                if ((!is_array($availabilityZoneData)) && $availabilityZoneData !='') {
                    $availJson = '["'.$availabilityZoneData.'"]';
                    $availabilityZoneData = json_decode($availJson, true);
                }

                if (empty($availabilityZoneData)) {

                    $attribute = $this->eavConfig->getAttribute('catalog_product', 'availability_zone');
                    $availabilityZoneOptions = $attribute->getSource()->getAllOptions();
                    $availabilityZoneData = array_column($availabilityZoneOptions, 'label');
                    $availabilityZoneData =  array_values(array_filter(array_map(
                        'trim',
                        $availabilityZoneData
                    ), 'strlen'));
                    $availabilityZoneData[] = 'RestofIndia';
                }
                $productDataPrice = $productData->getPrice();
                if (is_array($availabilityZoneData) || is_object($availabilityZoneData)) {
                    $isTrue = 0;
                    foreach ($availabilityZoneData as $availabilityZone) {
                        if ($availabilityZone == 'BLR_ZONE') {
                            $offerIdVal = $offerId.'_blr';
                            $customlabelVal0 = 'Bangalore';
                            $salePriceValData = $this->getPriceByZone($productDataPrice, $product, 'BANGALORE');
                            $salePriceValData = explode('-',$salePriceValData);
                            $salePriceVal = $salePriceValData[0];
                            $urlparam = $this->getUrlParam($salePriceValData[1]);
                            $productUrlVal = $productUrl.$urlparam;
                            $mrpprice = $this->getMRPPrice($productData->getMrp(), $salePriceVal);
                            $customlabel3 = $this->getCustomLabelThree($salePriceVal);
                        } elseif ($availabilityZone == 'CHN_ZONE') {
                            $offerIdVal = $offerId.'_chn';
                            $customlabelVal0 = 'Chennai';
                            $salePriceValData = $this->getPriceByZone($productDataPrice, $product, 'CHENNAI');
                            $salePriceValData = explode('-',$salePriceValData);
                            $salePriceVal = $salePriceValData[0];
                            $urlparam = $this->getUrlParam($salePriceValData[1]);
                            $productUrlVal = $productUrl.$urlparam;
                            $mrpprice = $this->getMRPPrice($productData->getMrp(), $salePriceVal);
                            $customlabel3 = $this->getCustomLabelThree($salePriceVal);
                        } elseif ($availabilityZone == 'HYD_ZONE') {
                            $offerIdVal = $offerId.'_hyd';
                            $customlabelVal0 = 'Hyderabad';
                            $salePriceValData = $this->getPriceByZone($productDataPrice, $product, 'HYDERABAD');
                            $salePriceValData = explode('-',$salePriceValData);
                            $salePriceVal = $salePriceValData[0];
                            $urlparam = $this->getUrlParam($salePriceValData[1]);
                            $productUrlVal = $productUrl.$urlparam;
                            $mrpprice = $this->getMRPPrice($productData->getMrp(), $salePriceVal);
                            $customlabel3 = $this->getCustomLabelThree($salePriceVal);
                        } elseif ($availabilityZone == 'RestofIndia') {
                            $offerIdVal = $offerId.'_rest';
                            $customlabelVal0 = 'RestofIndia';
                            $salePriceValData = $this->getPriceByZone($productDataPrice, $product, 'REST');
                            $salePriceValData = explode('-',$salePriceValData);
                            $salePriceVal = $salePriceValData[0];
                            $urlparam = $this->getUrlParam($salePriceValData[1]);
                            $productUrlVal = $productUrl.$urlparam;
                            $mrpprice = $this->getMRPPrice($productData->getMrp(), $salePriceVal);
                            $customlabel3 = $this->getCustomLabelThree($salePriceVal);
                        }

                        if (($mrpprice != '') && ($salePriceVal != '')
                        && ($mrpprice != '0') && ($salePriceVal != '0')) {

                            /*$txtVal = $offerIdVal."\t".$title."\t".$description."\t"
                            .$productUrlVal."\t".$baseImgUrl."\t".$condition."\t".$availability."\t"
                            .$mrpprice."\t".$salePriceVal."\t".$uom."\t".$brand."\t".$itemgroupId."\t"
                            .$productType."\t".$customlabelVal0."\t".$customlabel1."\t"
                            .$customlabel2."\t".$customlabel3."\t".$customlabel4."\t"
                            .$gtin."\t".$identifier_exists."\n";*/
                            $txtVal = $offerIdVal."\t".$title."\t".$description."\t"
                            .$productUrlVal."\t".$baseImgUrl."\t".$condition."\t".$availability."\t"
                            .$mrpprice."\t".$salePriceVal."\t".$uom."\t".$brand."\t".$itemgroupId."\t"
                            .$productType."\t".$customlabelVal0."\t".$customlabel1."\t"
                            .$customlabel2."\t".$customlabel3."\t".$customlabel4."\t"
                            .$gtin."\n";
                            $stream->write($txtVal);
                            $isTrue = 1;
                        }
                    }
                    if ($isTrue == 1) {
                        $count++;
                    }
                }
                $feedCount++;
            } else {
                $this->addLog('Not Satisfied : '.$offerId);
            }
        }
        $stream->unlock();
        $stream->close();
        unset($collection);
        if ($isGenerated == 1) {
            $this->sendEmail($count);
        }
        $this->addLog('End Data');
    }

    /**
     * Get MRP
     *
     * @param Numeric $mrpValue
     * @param Numeric $salePriceVal
     * @return void
     */
    public function getMRPPrice($mrpValue, $salePriceVal)
    {
        if (($mrpValue != '') && ($mrpValue != 0)) {
            $mrpprice = (float)$mrpValue;
        } else {
            $mrpprice = $salePriceVal;
        }
        return $mrpprice;
    }

    protected function getUrlParam($zone) {
        $urlParam = '';
        if(($zone == 'BANGALORE') || ($zone == 'DEFAULT')) {
            $urlParam = '?str_id=ST002';
        } elseif ($zone == 'CHENNAI') {
            $urlParam = '?str_id=ST003';
        } elseif ($zone == 'HYDERABAD') {
            $urlParam = '?str_id=ST004';
        }
        return $urlParam;
    }

    /**
     * Send Email
     *
     * @param Mixed $emailBody
     * @return void
     */

    public function sendEmail($feedCount)
    {
        $isEnable = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/google_feed_email_status");
        if ($isEnable == 1) {
            $emailBody = $this->_scopeConfig->getValue("ibo_google_feed/google_feed_settings/google_feed_email_body");
            $this->_inlineTranslation->suspend();
            $emailTemplateVariables = [
                'message' => $emailBody.' '.$feedCount
            ];
            $storeScope = ScopeInterface::SCOPE_STORE;
            $receiverInfo = [
                'email' => $this->_scopeConfig->getValue(
                    "ibo_google_feed/google_feed_settings/google_feed_email_address"
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
            $transport = $this->_transportBuilder->setTemplateIdentifier('google_feed_generation')
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
                $this->addLog('Email error: ');
            }
            $this->_inlineTranslation->resume();
        } else {
            $this->addLog('Email Disabled');
        }
    }
    
    protected function getPriceByZone($productDataPrice, $product, $priceZone)
    {
        if ($productDataPrice != '') {
            $saleprice = '';
            $urlParam = 'DEFAULT';
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
                if($priceZone != 'REST') {
                    foreach ($tierPriceData as $price) {
                        if (($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == strtolower($priceZone))
                            && ((int)$price['price_qty'] == 1)) {
                            $saleprice = number_format((float)($price['price']), 2, '.', '');
                            $urlParam = $priceZone; 
                        }

                        if(($saleprice == '')
                            && ($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == 'default')
                            && ((int)$price['price_qty'] == 1)) {
                                $saleprice = number_format((float)($price['price']), 2, '.', '');
                                $urlParam = 'DEFAULT';
                            }
                    }
                } else {
                    foreach ($tierPriceData as $price) {
                        if (($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == 'default')
                            && ((int)$price['price_qty'] == 1)) {
                            $saleprice = number_format((float)($price['price']), 2, '.', '');
                            $urlParam = 'DEFAULT'; 
                        }

                        if(($saleprice == '')
                            && ($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == 'bangalore')
                            && ((int)$price['price_qty'] == 1)) {
                                $saleprice = number_format((float)($price['price']), 2, '.', '');
                                $urlParam = 'BANGALORE';
                            }

                        if(($saleprice == '')
                            && ($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == 'chennai')
                            && ((int)$price['price_qty'] == 1)) {
                                $saleprice = number_format((float)($price['price']), 2, '.', '');
                                $urlParam = 'CHENNAI';
                            }
                        if(($saleprice == '')
                            && ($price['cust_group'] == $customerGroupId)
                            && ($price['customer_zone'] == 'hyderabad')
                            && ((int)$price['price_qty'] == 1)) {
                                $saleprice = number_format((float)($price['price']), 2, '.', '');
                                $urlParam = 'HYDERABAD';
                            }
                    }
                }
            }
            if ($saleprice == '') {
                $finalPrice = $product->getFinalPrice();
                $saleprice = number_format((float)($finalPrice), 2, '.', '');
            }
        }
        return $saleprice.'-'.$urlParam;
    }

    protected function getCustomLabelThree($saleprice)
    {
        $customlabel3 = '';
        if ($saleprice != '') {

            if ($saleprice > 0 && $saleprice <= 100) {
                $customlabel3 = '0-100';
            } elseif ($saleprice > 100 && $saleprice <= 500) {
                $customlabel3 = '101-500';
            } elseif ($saleprice > 500 && $saleprice <= 1500) {
                $customlabel3 = '501-1500';
            } elseif ($saleprice > 1500 && $saleprice <= 2500) {
                $customlabel3 = '1501-2500';
            } elseif ($saleprice > 2500 && $saleprice <= 5000) {
                $customlabel3 = '2501-5000';
            } else {
                $customlabel3 = '5000+';
            }
        }
        return $customlabel3;
    }

    public function addLog($logData)
    {
        $filename = "google-primary-feed.log";
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
