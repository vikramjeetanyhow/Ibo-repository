<?php

namespace Embitel\ProductImport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ProductBulkImport
{

    const  CRON_STATUS_PATH = 'ebo/ebo_product_import/cron_status';

    const CRON_LOG_STATUS_PATH = 'ebo/ebo_product_import/log_active';
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig($path) {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    public function isCronActive() {
        return $this->getConfig(self::CRON_STATUS_PATH);
    }

}