<?php

namespace Embitel\ProductExport\Model;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;

class ProductReportExportHelper
{

    const LOG_PATH = '/var/log/ebo_product_export_report.log';
    const XML_PATH_CRON_STATUS = 'eboexport/ebo_report_config/cron_status';
    private DataObject $configs;
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param DataObject $configs
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        DataObject           $configs,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->configs = new $configs();
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isCronEnabled(): bool
    {
        $isCronEnable = $this->getConfigs()->getCronStatus();
        if (!$isCronEnable || $isCronEnable == 0) {
            $this->addLog('Cron to export products disabled');
        }
        return $isCronEnable;
    }

    function getConfigs(): DataObject
    {
        if (!$this->configs->getCronStatus()) {
            $this->configs->addData(
                $this->scopeConfig->getValue('eboexport/ebo_report_config', ScopeInterface::SCOPE_WEBSITE)
            );
        }
        return $this->configs;
    }

    /**
     * @param $logdata
     * @return void
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            if (is_array($logdata)) {
                $this->getProductExportLog()->info(print_r($logdata, true));
            } else {
                $this->getProductExportLog()->info($logdata);
            }
        }
    }

    /**
     * @return mixed
     */
    private function canWriteLog()
    {
        if (!isset($this->isLogEnabled)) {
            $this->isLogEnabled = $this->getConfigs()->getLogStatus();
        }
        return $this->isLogEnabled;
    }

    /**
     * @return Logger
     */
    private function getProductExportLog(): Logger
    {
        $writer = new Stream(BP . self::LOG_PATH);
        $logger = new Logger();
        $logger->addWriter($writer);
        return $logger;
    }

}