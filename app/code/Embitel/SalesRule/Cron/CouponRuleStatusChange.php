<?php
/**
 * @package: Embitel_SalesRule
 * @desc Get all active promotions in mail.
 *
 */
namespace Embitel\SalesRule\Cron;

use Embitel\Quote\Helper\Data;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CouponRuleStatusChange
{
    /**
     * @var Data
     */
    protected $helper;
    public function __construct(
        Data $helper,
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->helper = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->connection = $this->resourceConnection->getConnection();
    }
    public function execute()
    {
        $cronStatus = $this->scopeConfig->getValue('email_notification/sales_rule_cron_status/sales_rule_update_status_cron', ScopeInterface::SCOPE_STORE);
        if($cronStatus) {
            $this->helper->addLog("---------------------------------------------", "active-promo-status.log");
            $this->helper->addLog("Promotions Status Cron Started", "active-promo-status.log");
            $this->updateStatus();
            $this->helper->addLog("Promotions Status Cron End", "active-promo-status.log");
            $this->helper->addLog("---------------------------------------------", "active-promo-status.log");
        }
    }

protected function updateStatus(){
        $date = date('Y-m-d');
        $connection = $this->resourceConnection->getConnection();
        $query = "SELECT * FROM salesrule WHERE is_active = 1 && to_date <= '$date'";
        $result = $this->connection->fetchAll($query);
        foreach ($result as $rule){
            $rule_id = $rule['rule_id'];
            $query = "UPDATE salesrule SET is_active = 0 WHERE rule_id = $rule_id";
            $result = $connection->query($query);
            $this->helper->addLog('Rule Id: '. $rule_id . ' status is updated', "active-promo-status.log");
        }
    }
}
