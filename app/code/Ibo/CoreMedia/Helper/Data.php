<?php
/**
 * @category   IBO
 * @package    Ibo_CoreMedia
 * @author     Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\CoreMedia\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl as CurlObj;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Session\SessionManagerInterface as CoreSession;
use Embitel\Facade\Model\ProductServiceSync;

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
        CurlObj $curlObj,
        \Magento\Framework\App\ResourceConnection $resource,
        ProductServiceSync $productPush               
    ) {        
        $this->scopeConfig = $scopeConfig;
        $this->curlObj = $curlObj;
        $this->_coreSession = $coreSession; 
        $this->resource = $resource;
        $this->productPush = $productPush;       
    }


    private function getSystemConfig()
    {
        $isSyncEnable = $this->scopeConfig->getValue("core_media/service/sync_status");
        if (!$isSyncEnable) {
            $this->addLog('Core media Sync service disable.');
            return;
        }
        $this->generateTokenApi = trim($this->scopeConfig->getValue("core_media/service/generate_token_api"));
        $this->xAuthToken     = trim($this->scopeConfig->getValue("core_media/service/x_auth_token"));
        $this->uploadMediaApi = trim($this->scopeConfig->getValue("core_media/service/upload_media_api"));
        $this->getMediaApi    = trim($this->scopeConfig->getValue("core_media/service/get_media_api"));
        
        if(empty($this->generateTokenApi) || empty($this->xAuthToken) || empty($this->uploadMediaApi) || empty($this->getMediaApi)){             
             return 0;
        }
        return 1;
    }

    public function getCoreMediaImageUrl($imagefullPath)
    {   
        if($this->getSystemConfig() == 0){
            $this->addLog('IBO core media service configuration missing.');
            return;
        }
        
        $baseImageUrl = '';
        $this->baseImageData = $baseImagePayload = [];        
            $this->baseImageData[] = ["url" => $imagefullPath,"file_type" => "images"];                   
            $baseImagePayload = json_encode($this->baseImageData, JSON_UNESCAPED_SLASHES);
            if($this->mediatoken == ''){ 
                $this->mediatoken = $this->getMediaAuthToken();
            }
            if($this->mediatoken != ''){
                $this->addLog('==============request==========================');
                $this->addLog(json_encode($this->baseImageData, JSON_UNESCAPED_SLASHES));                
                $baseImageUrl = $this->curlMediaExecute($baseImagePayload,$this->uploadMediaApi,$this->mediatoken);               
            }        

        return $baseImageUrl;
    }

    public function getMediaAuthToken()
    {
        $this->_coreSession->start();
        if($this->_coreSession->getIboAuthToken() != ""){
            return $this->_coreSession->getIboAuthToken();
        }
        $tokenResult  = '';
        $authresultData = [];
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
        $resultData = [];
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
            $result = '';
            $resultData = reset($resultData);
            if(isset($resultData['media_url'])){
              $result = $resultData['media_url'];  
            }else{                
                $this->addLog("Result data media_url missing.There is some error");
            }
            return $result;
        }
    }   

    public function updateCatalogServicePushData($productId) {
         $realTimeSync = $this->scopeConfig->getValue("core_media/service/realtime_sync");
        if($realTimeSync) {
            $this->productPush->prepareAndSendData($productId);
        } else {
            $this->addLog('Entered Log Function');
            $tableName = "catalog_service_product_push";
            $connection = $this->resource->getConnection();
            $data = ["product_id"=>$productId,"status_flag"=> 0]; // Key_Value Pair
            $connection->insertOnDuplicate($tableName, $data);
        }
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
                "core_media/service/media_sync_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/core_media_import.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
