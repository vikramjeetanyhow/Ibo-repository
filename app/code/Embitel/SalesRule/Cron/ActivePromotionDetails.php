<?php
/**
 * @package: Embitel_SalesRule
 * @desc Get all active promotions in mail.
 *
 */
namespace Embitel\SalesRule\Cron;

use Embitel\Quote\Helper\Data;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder;
use Magento\Framework\Filesystem;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;

class ActivePromotionDetails
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';
    public const RECEIVER_EMAIL = 'email_notification/sales_rule_cron/sales_rule_report_email';

    /**
     * @var Data
     */
    protected $helper;
    public function __construct(
        Data $helper,
        ResourceConnection $resourceConnection,
        TransportBuilder $transportBuilder,
        StoreManager $storeManager,
        ScopeConfigInterface $scopeConfig,
        StateInterface $inlineTranslation,
        File $file,
        CollectionFactory $salesRuleCollection,
        Filesystem $filesystem
    ) {
        $this->helper = $helper;
        $this->_resourceConnection = $resourceConnection;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->inlineTranslation = $inlineTranslation;
        $this->file = $file;
        $this->salesRuleCollection = $salesRuleCollection;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }
    public function execute()
    {
        $cronStatus = $this->scopeConfig->getValue('email_notification/sales_rule_cron/sales_rule_report_cron_status', ScopeInterface::SCOPE_STORE);
        if($cronStatus) {
            $this->helper->addLog("---------------------------------------------", "active-promo-report.log");
            $this->helper->addLog("Promotions Report Cron Started", "active-promo-report.log");
            $this->generateReport();
            $this->helper->addLog("Promotions Report Cron End", "active-promo-report.log");
            $this->helper->addLog("---------------------------------------------", "active-promo-report.log");
        }
    }

    public function generateReport() {

        $salesRule = $this->salesRuleCollection->create()
            ->addFieldToSelect(['rule_id','name','from_date','to_date',
                'uses_per_customer','sort_order','stop_rules_processing',
                'description','terms_cond','simple_action','discount_amount',
                'max_percent_amount','coupon_use_in'
                ])
            ->addFieldToFilter('is_active', ['eq'=>1]);

        if($salesRule->count() > 0) {
            $name = date('mdY');
            $filepath = 'export/active_promotion_report' . $name . '.csv';

            $this->directory->create('export');

            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();
            $columns = $this->getColumnHeader();
            foreach ($columns as $column) {
                $header[] = $column;
            }
            $stream->writeCsv($header);

            foreach ($salesRule->getData() as $rules) {
                $rules['customer_group'] = $this->getCustomerGroup($rules['rule_id']);
                unset($rules['rule_id']);
                $stream->writeCsv($rules);

            }

            $filepath = BP.'/var/'.$filepath;
            if ($this->file->isExists($filepath)) {
                $this->sendEmail($filepath);
            }
        } else {
            $this->helper->addLog("No Record Found ", "active-promo-report.log");
        }

    }

    public function getCustomerGroup($rule_id){
        $customerGroup = '';

        $connection = $this->_resourceConnection->getConnection();
        $query = "SELECT * FROM salesrule as sr INNER JOIN salesrule_customer_group as scg
                    ON sr.rule_id = scg.rule_id INNER JOIN customer_group as cg
                    ON scg.customer_group_id = cg.customer_group_id
                    WHERE sr.rule_id=".$rule_id;
        $result = $connection->fetchAll($query);

        foreach ($result as $rule){
            $customerGroup .= $rule['customer_group_code']."," ?? '';
        }

        return rtrim($customerGroup,',');
    }

    protected function getColumnHeader()
    {
        return [
            "Rule Id", "Promotion Name", "From Date", "To Date",'Uses Per Customer',
            "Priority",'Discard subsequent rules',"Description", "Terms & Conditions",
            "Action","Discount Amount",'Maximum Percent Discount',"Plateform",
            "Code","Customer Group"
        ];
    }


    public function sendEmail($fileUrl){

        $this->inlineTranslation->suspend();
        $emailTemplateVariables = ['message' => "Please find active promotion's details in the attachment"];

        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);
        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];

        $receiverEmails = explode(',',trim($this->scopeConfig->getValue(self::RECEIVER_EMAIL, ScopeInterface::SCOPE_STORE)));

        $fileName = basename($fileUrl);

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('active_promotion_report')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])
            ->setTemplateVars($emailTemplateVariables)
            ->setFrom($senderInfo)
            ->addTo($receiverEmails)
            ->addAttachment(file_get_contents($fileUrl), $fileName, 'application/csv')
            ->getTransport();
        try {
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $exception) {
            $this->helper->addLog($exception->getMessage(), "active-promo-report.log");
        }
        $this->file->deleteFile($fileUrl);
    }

}
