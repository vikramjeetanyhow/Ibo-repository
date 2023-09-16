<?php

namespace Embitel\ProductImport\Helper;

use DateTime;
#use Fastly\Cdn\Block\System\Config\Form\Field\Export\Fastly;
#use Fastly\Cdn\Model\PurgeCache;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;
use Zend_Http_Client;

class ProductImportApiHelper
{
    const XML_WATER_MARK = 'ebo/ebo_watermark/image_watermark_service_url';
    const XML_WATER_MARK_UPLOAD = 'ebo/ebo_watermark/image_watermark_upload_endpoint';
    private ScopeConfigInterface $scopeConfig;
    private UrlBuilder $urlBuilder;
    private StoreManager $storeManager;
    private PurgeCache $purgeCache;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManager $storeManager
     * @param PurgeCache $purgeCache
     */
    public function __construct(
        ScopeConfigInterface         $scopeConfig,
        StoreManager                 $storeManager
        // PurgeCache $purgeCache
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        //$this->purgeCache = $purgeCache;
    }

    /**
     * @param $imageUrl
     * @param $fileName
     * @return int|string
     * @throws \Zend_Uri_Exception
     */
    public function send($imageUrl): string
    {
        $imageUrl = $this->getMediaUrl() . 'catalog/import/' . $imageUrl;
        return true;
        // $this->purgeCache->sendPurgeRequest($imageUrl);

        // $fileName = basename($imageUrl);
        // $apiUrl = $this->getWaterMarkApiUrl();

        // $curl = curl_init();
        // $url = $apiUrl . "?uri= " . $imageUrl . "?q=" . $this->getTimeStamp() . "&file-name=" . $fileName;

        // curl_setopt_array($curl, array(
        //     CURLOPT_URL => $url,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_ENCODING => "",
        //     CURLOPT_MAXREDIRS => 10,
        //     CURLOPT_TIMEOUT => 30,
        //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST => "POST",
        //     CURLOPT_HTTPHEADER => array(
        //         "cache-control: no-cache"
        //     ),
        // ));

        // $response = curl_exec($curl);
        // $err = curl_error($curl);

        // curl_close($curl);

        // if ($err) {
        //     return "cURL Error #:" . $err;
        // } else {
        //     return $response;
        // }

    }

    public function getMediaUrl(): string
    {
        if (isset($this->getBaseImageUrl) && $this->getBaseImageUrl != null) {
            return $this->getBaseImageUrl;
        }
        $this->getBaseImageUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return $this->getBaseImageUrl;
    }

    /**
     * @return string
     */
    public function getWaterMarkApiUrl(): string
    {
        if (isset($this->apiUrl) && $this->apiUrl != null) {
            return $this->apiUrl;
        }
        $this->apiUrl = $this->scopeConfig->getValue(self::XML_WATER_MARK, ScopeConfigInterface::SCOPE_TYPE_DEFAULT) . $this->scopeConfig->getValue(self::XML_WATER_MARK_UPLOAD, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        return $this->apiUrl;
    }

    /**
     * @return int
     */
    public function getTimeStamp(): int
    {
        $now = new DateTime();
        return $now->getTimestamp();
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        if (isset($this->baseUrl) && $this->baseUrl != null) {
            return $this->baseUrl;
        }
        $this->baseUrl = $this->scopeConfig->getValue(self::XML_WATER_MARK, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        return $this->baseUrl;
    }

}
