<?php

namespace Ibo\CategoryWidget\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

class CategoryEsindexer
{
    protected $scopeConfig;

    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig      
    ) {
        $this->scopeConfig = $scopeConfig;        
    }
    
    /**
     * import source price
     * @return void
     */
    public function execute()
    { 
        $isCronEnable = $this->scopeConfig->getValue("categorywidget/widget_config/cron_status");
        if (!$isCronEnable) {
            return;
        }
        $this->addLog("====START====Category ES Cron========");            
        $this->addLog("======".date('d-m-Y G:i:s')."========");
        if(file_exists(BP . '/var/search/esindexer.php')){
            require BP . '/var/search/esindexer.php';
        }    
        $this->addLog("======".date('d-m-Y G:i:s')."========");
        $this->addLog("====START====Category ES Cron========");               
                
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
                "categorywidget/widget_config/catindexer_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/escronindexer.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
