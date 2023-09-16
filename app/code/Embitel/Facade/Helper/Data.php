<?php
/**
 * @category   Embitel
 * @package    Embitel_Facade
 * @author     Muthu Ganesh
 */
namespace Embitel\Facade\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl as CurlObj;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;

/**
 * Core Media data helper
 *
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $generateTokenApi;

    protected $xAuthToken;

    protected $uploadMediaApi;

    protected $getMediaApi;

    protected $mediatoken = '';

    protected $curl;
    
    protected $scopeConfig;

    protected $_coreSession;

    /**
     * @param ProductFactory $productFactory
     * @param CsvProcessor $csvProcessor
     */
    public function __construct(        
        ScopeConfigInterface $scopeConfig,
        CoreSession $coreSession,     
        CurlObj $curlObj               
    ) {        
        $this->scopeConfig = $scopeConfig;
        $this->curlObj = $curlObj;
        $this->_coreSession = $coreSession;        
    }


    private function getSystemConfig()
    {
        $this->generateTokenApi = trim($this->scopeConfig->getValue("core_media/service/generate_token_api"));
        $this->xAuthToken     = trim($this->scopeConfig->getValue("core_media/service/x_auth_token"));
        $this->productSyncApi = trim($this->scopeConfig->getValue("catalog_service/general/product_sync_api"));
        
        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->productSyncApi)){
             $this->addLog('IBO Product Sync Catalog service configuration missing.');
             return 0;
        }
        return 1;
    }

    public function getAuthToken()
    {
        $this->_coreSession->start();
        $this->generateTokenApi = trim($this->scopeConfig->getValue("core_media/service/generate_token_api"));
        $this->xAuthToken     = trim($this->scopeConfig->getValue("core_media/service/x_auth_token"));
        if($this->_coreSession->getIboAuthToken() != ""){
            return $this->_coreSession->getIboAuthToken();
        }
        $tokenResult  = '';
        $this->curlObj->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curlObj->setOption(CURLOPT_HTTPGET, true);
        $headers = ["x-auth-token" => $this->xAuthToken];
        $this->addLog(print_r($headers,true));
        $this->curlObj->setHeaders($headers);
        try {
            $this->addLog('Media Auth Curl Initiated');
            $this->curlObj->get($this->generateTokenApi);
            $this->addLog($this->generateTokenApi);
            $authresult = $this->curlObj->getBody();
            $authresultData = json_decode($authresult, true);
            $this->addLog('Media Auth Curl response');
            $this->addLog(json_encode($authresultData,JSON_PRETTY_PRINT));
        } catch (\Exception $e) {           
            $this->addLog("Media Auth Curl There is some error"."=======".$e->getMessage());
        }

        if ($authresultData) {
            $this->addLog('Media Curl Executed');

            if (isset($authresultData['errors'])) {
                $this->addLog(print_r($authresultData['errors'],true));
            }

            if (isset($authresultData['token'])) {
                $tokenResult  = $authresultData['token'];

            }            
            $this->_coreSession->setIboAuthToken($tokenResult);
            $this->addLog("_coreSession Set"."=======".$this->_coreSession->getIboAuthToken());
            return $tokenResult;
        }       

    }

    public function curlMediaExecute($payload,$url,$token)
    {
        $returnResult = '';
        $this->curlObj->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curlObj->setOption(CURLOPT_POST, true);
        $headers = ["Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->addLog(print_r($headers,true));
        $this->curlObj->setHeaders($headers);

        try {
            $this->addLog('Curl Initiated');
            $this->curlObj->post($url,$payload);
            $result = $this->curlObj->getBody();
            $resultData = json_decode($result, true);
            $this->addLog('==============response==========================');
            $this->addLog(json_encode($resultData, JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        if ($resultData) {
            $this->addLog('Curl Executed');
            
            if (isset($resultData['errors'])) {
                $this->addLog(print_r($resultData['errors'],true));
                $returnResult = "There is some error";
            }
        }

        if ($returnResult != '') {
            return 'Error thrown';
        } elseif(is_array($resultData)) {
            $resultData = reset($resultData);
            //$this->addLog(print_r($resultData,true));
            return $resultData['media_url'];
        }
    }   

    public function send($data,$product) {
        $this->addLog('======Request======');
        $this->addLog(print_r($data,true));
        $returnResult = '';
        $url = $this->getProductSyncApi();
        $token = $this->getAuthToken();
        $curlTimeout = trim($this->scopeConfig->getValue("core_media/service/curl_timeout"));
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        $this->curlObj->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curlObj->setOption(CURLOPT_POST, true);
        $headers = ["x-channel-id" => $this->xChannelId,"Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->addLog(print_r($headers,true));
        $this->curlObj->setHeaders($headers);
        if($curlTimeout != '') {
            $this->curlObj->setOption(CURLOPT_TIMEOUT, $curlTimeout);
        }

        try {
            $this->addLog('Curl Initiated');
            $this->curlObj->post($url,$data);
            $result = $this->curlObj->getBody();
            $resultData = json_decode($result, true);
            if($resultData != null && (isset($resultData['esin']) && $resultData['esin'] != '')) {
                $this->addLog('==============success-response==========================');
                $this->addLog(json_encode($resultData, JSON_UNESCAPED_SLASHES));
                $returnResult = 200;
            } else {
                $this->addLog('==============failure-response==========================');          
                $this->addLog("Esin:".$product->getEsin().", Sku:".$product->getSku().", Attribute Set Id:".$product->getAttributeSetId().", IBO Category ID:".$product->getIboCategoryId().", ".json_encode($resultData, JSON_UNESCAPED_SLASHES));
                $returnResult = 0;
            }
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }
        return $returnResult;
    }

    public function sendOfferData($data) {
        $this->addLog('======Request======');
        $this->addLog(print_r($data,true));
        $returnResult = '';
        $url = $this->getProductOfferSyncApi();
        $token = $this->getAuthToken();
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        $curlTimeout = trim($this->scopeConfig->getValue("core_media/service/curl_timeout"));
        $this->curlObj->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curlObj->setOption(CURLOPT_POST, true);
        $headers = ["x-channel-id" => $this->xChannelId,"Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->addLog(print_r($headers,true));
        $this->curlObj->setHeaders($headers);
        if($curlTimeout != '') {
            $this->curlObj->setOption(CURLOPT_TIMEOUT, $curlTimeout);
        }
        

        try {
            $this->addLog('Curl Initiated');
            $this->curlObj->post($url,$data);
            $result = $this->curlObj->getBody();
            $resultData = json_decode($result, true);
            $this->addLog('==============response==========================');
            $this->addLog(json_encode($resultData, JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        return $returnResult;

    }

    public function getStoreCatalogUrlApi() {
        $productsynApi = trim($this->scopeConfig->getValue("catalog_service/general/store_product_sync_api"));
        if ($productsynApi == '') {
            return false;
        }
        return $productsynApi;
    }

    public function sendStoreStockData($data) {
        $this->addLog('======Store Data Request======');
        $this->addLog(print_r($data,true));
        $returnResult = '';
        $url = $this->getStoreCatalogUrlApi();
        $token = $this->getAuthToken();
        $this->xChannelId = trim($this->scopeConfig->getValue("cate/ibo_cat_config/x_channel_id"));
        $curlTimeout = trim($this->scopeConfig->getValue("core_media/service/curl_timeout"));
        $this->curlObj->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curlObj->setOption(CURLOPT_POST, true);
        $headers = ["x-channel-id" => $this->xChannelId,"Accept" => "application/json","Content-Type" => "application/json", "Authorization" => $token];
        $this->addLog(print_r($headers,true));
        $this->curlObj->setHeaders($headers);
        if($curlTimeout != '') {
            $this->curlObj->setOption(CURLOPT_TIMEOUT, $curlTimeout);
        }
        

        try {
            $this->addLog('Curl Initiated');
            $this->curlObj->post($url,$data);
            $result = $this->curlObj->getBody();
            $resultData = json_decode($result, true);
            $this->addLog('==============response==========================');
            $this->addLog(json_encode($resultData, JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            $returnResult = "There is some error";
        }

        return $returnResult;

    }

    public function isCronEnabled() {
        $isCronEnable = $this->scopeConfig->getValue("catalog_service/general/cron_status");
        if (!$isCronEnable || $isCronEnable == 0) {
            $this->addLog("Cron to sync product to catalog service is disabled.");
            return false;
        }
        return true;
    }

    public function getFailureHitLimit() {
        $failurelimit = trim($this->scopeConfig->getValue("catalog_service/general/failure_hit_limit"));
        return $failurelimit;
    }

    public function getProductSyncApi() {
        $productsynApi = trim($this->scopeConfig->getValue("catalog_service/general/product_sync_api"));
        if ($productsynApi == '') {
            return false;
        }
        return $productsynApi;
    }

    public function getProductOfferSyncApi() {
        $productsynApi = trim($this->scopeConfig->getValue("catalog_service/general/product_offer_sync_api"));
        return $productsynApi;
    }



    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->scopeConfig->getValue(
                "catalog_service/general/log_status"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/catalog_service_product_syn.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
