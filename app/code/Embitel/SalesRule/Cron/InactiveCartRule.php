<?php
Namespace Embitel\SalesRule\Cron;


class InactiveCartRule {
    const  CRON_STATUS_PATH = 'inactive_cart_rule_cron/sales_rule_cron_status/sales_rule_report_cron_status';
    protected $logger;
    protected $rule;
    protected $helper;
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory $salesRuleCollection,
        \Magento\SalesRule\Api\RuleRepositoryInterface $rule,
        \Embitel\Quote\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection

    ) {
        $this->logger = $logger;
        $this->rule = $rule;
        $this->helper = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->salesRuleCollection = $salesRuleCollection;
        $this->scopeConfig = $scopeConfig;
        $this->connection = $this->resourceConnection->getConnection();
       
    }
    /**
    * Write to system.log
    *
    * @return void
    */
    public function execute() {
        $cronStatus = $this->scopeConfig->getValue('inactive_cart_rule_cron/sales_rule_cron_status/sales_rule_report_cron_status', ScopeInterface::SCOPE_STORE);
        if($cronstatus){
            $connection = $this->resourceConnection->getConnection();
            $salesRule = $this->salesRuleCollection->create()->addFieldToSelect(['rule_id','to_date','is_active','name'])->addFieldToFilter('is_active',1);
            $todaydate = date("Y-m-d");
            if($salesRule->count() > 0) {
                 foreach ($salesRule->getData() as $rules) {
                    $Ruledate = $rules['to_date'];
                    if (!empty($Ruledate)) {
                        $rule_id = $rules['rule_id'];
                        $name = $rules['name'];
                        if ($Ruledate < $todaydate) {
                            $this->helper->addLog("---------------------------------------------", "inactive-promo-report.log");
                            $this->helper->addLog("Inactive Cron  Started", "inactive-promo-report.log");
                            $query = "UPDATE salesrule SET is_active = 0 WHERE rule_id = $rule_id";
                            $result = $connection->query($query);
                            $this->helper->addLog('Rule Id: '. $rule_id . ' status is inactive.Date is Expire '.$Ruledate, "inactive-promo-report.log");
                            $this->helper->addLog("Inactive Report Cron End", "inactive-promo-report.log");
                            $this->helper->addLog("---------------------------------------------", "inactive-promo-report.log");
                        }
                    }
                }
            }
        }else{
             $this->helper->addLog("No Record Found ", "inactive-promo-report.log");
        }
    }
}