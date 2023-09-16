<?php

namespace Embitel\ProductExport\Model;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;

class CustomerReportAdditionalExportHelper
{
    const LOG_PATH = '/var/log/ebo_product_export_report.log';
    const XML_PATH_CRON_STATUS = 'eboexport/ebo_customer_report_config_additional/cron_status';
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
        $this->configs = $configs;
        $this->scopeConfig = $scopeConfig;
    }

    public function isCronEnabled(): bool
    {
        $isCronEnable = $this->getConfigs()->getCronStatus();
        if (!$isCronEnable || $isCronEnable == 0) {
            $this->addLog('Cron to export customer additional disabled');
        }
        return $isCronEnable;
    }

    function getConfigs(): DataObject
    {
        if (!$this->configs->getCronStatus()) {
            $this->configs->addData(
                $this->scopeConfig->getValue('eboexport/ebo_customer_report_config_additional', ScopeInterface::SCOPE_WEBSITE)
            );
        }
        return $this->configs;
    }

    public function addLog($logData) {
        if ($this->canWriteLog()) {
            if (is_array($logData)) {
                $this->getCustomerExportLog()->info(print_r($logData, true));
            } else {
                $this->getCustomerExportLog()->info($logData);
            }
        }
    }

    private function canWriteLog(){
        if (!isset($this->isLogEnabled)) {
            $this->isLogEnabled = $this->getConfigs()->getLogStatus();
        }
        return $this->isLogEnabled;
    }

    /**
     * @return Logger
     */
    private function getCustomerExportLog(): Logger
    {
        $writter = new Stream(BP . self::LOG_PATH);
        $logger = new Logger();
        $logger->addWriter($writter);
        return $logger;
    }
}
