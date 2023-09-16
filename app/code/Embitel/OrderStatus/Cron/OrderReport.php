<?php
namespace Embitel\OrderStatus\Cron;
use Embitel\Quote\Helper\Data;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Filesystem\Driver\File;

class OrderReport
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';
    public const RECEIVER_EMAIL = 'email_notification/order_report_cron/order_report_email';

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
        File $file
    ) {
        $this->helper = $helper;
        $this->_resourceConnection = $resourceConnection;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->inlineTranslation = $inlineTranslation;
        $this->file = $file;
    }
    public function execute()
    {
        $cronStatus = $this->scopeConfig->getValue('email_notification/order_report_cron/order_report_cron_status', ScopeInterface::SCOPE_STORE);
        if($cronStatus) {
            $this->helper->addLog("---------------------------------------------", "order-report.log");
            $this->helper->addLog("Order Report Cron Started", "order-report.log");
            $this->generateReport();
            $this->helper->addLog("Order Report Cron End", "order-report.log");
            $this->helper->addLog("---------------------------------------------", "order-report.log");
        }
    }

    public function generateReport() {

        $connection = $this->_resourceConnection->getConnection();

        $ruleSql = "select rule_id, name from salesrule";
        $ruleData = $connection->fetchAll($ruleSql);
        $appliedRules = [];
        foreach ($ruleData as $data) {
            $appliedRules[$data['rule_id']][] = $data['name'];
        }

        $query = "SELECT
                so.increment_id AS `order_id`,
                so.order_channel AS `order_channel`,
                so.created_at,
                so.status,
                so.coupon_code AS `coupon`,
                soi.sku AS `sku`,
                soi.qty_ordered AS `qty`,
                soi.price AS `price`,
                soi.price_incl_tax AS `price_with_tax`,
                soi.discount_amount AS `discount_amount`,
                soi.applied_rule_ids AS `applied_rule_ids`,
                '' AS `rule_data`,
                cped.value AS `department`,
                cped.value AS `class`,
                cpes.value AS `sub_class`,
                cg.customer_group_code AS `customer_group`,
                cei.value AS `business_activity`,
                (SELECT
                        GROUP_CONCAT(eaov.value, ' ')
                    FROM
                        customer_entity_int cei
                            LEFT JOIN
                        eav_attribute_option_value eaov ON cei.value = eaov.option_id
                            && cei.entity_id = so.customer_id
                    WHERE
                        attribute_id = (SELECT
                                attribute_id
                            FROM
                                eav_attribute ea
                            WHERE
                                ea.attribute_code = 'customer_type'
                                    && ea.entity_type_id = (SELECT
                                        eav_entity_type.entity_type_id
                                    FROM
                                        eav_entity_type
                                    WHERE
                                        entity_type_code = 'customer'))) AS `customer_type`,
                aspo.pos_user_id,
                aspu.firstname AS `cashier_name`
            FROM
                sales_order so
                    LEFT JOIN
                ah_supermax_pos_orders aspo ON aspo.order_id = so.entity_id
                    LEFT JOIN
                ah_supermax_pos_user aspu ON aspo.pos_user_id = aspu.pos_user_id
                    LEFT JOIN
                sales_order_item soi ON so.entity_id = soi.order_id
                    LEFT JOIN
                customer_group cg ON cg.customer_group_id = so.customer_group_id
                    LEFT JOIN
                catalog_product_entity_varchar cped ON cped.entity_id = soi.product_id
                    && cped.store_id = 0
                    && cped.attribute_id = (SELECT
                        attribute_id
                    FROM
                        eav_attribute
                    WHERE
                        entity_type_id = (SELECT
                                entity_type_id
                            FROM
                                eav_entity_type
                            WHERE
                                entity_type_code = 'catalog_product')
                            && attribute_code = 'department')
                    LEFT JOIN
                catalog_product_entity_varchar cpec ON cpec.entity_id = soi.product_id
                    && cpec.store_id = 0
                    && cpec.attribute_id = (SELECT
                        attribute_id
                    FROM
                        eav_attribute
                    WHERE
                        entity_type_id = (SELECT
                                entity_type_id
                            FROM
                                eav_entity_type
                            WHERE
                                entity_type_code = 'catalog_product')
                            && attribute_code = 'class')
                    LEFT JOIN
                catalog_product_entity_varchar cpes ON cpes.entity_id = soi.product_id
                    && cpes.store_id = 0
                    && cpes.attribute_id = (SELECT
                        attribute_id
                    FROM
                        eav_attribute
                    WHERE
                        entity_type_id = (SELECT
                                entity_type_id
                            FROM
                                eav_entity_type
                            WHERE
                                entity_type_code = 'catalog_product')
                            && attribute_code = 'subclass')
                    LEFT JOIN
                customer_entity_text cei ON cei.entity_id = so.customer_id
                    && cei.attribute_id = (SELECT
                        attribute_id
                    FROM
                        eav_attribute ea
                    WHERE
                        ea.attribute_code = 'business_activities'
                            && ea.entity_type_id = (SELECT
                                eav_entity_type.entity_type_id
                            FROM
                                eav_entity_type
                            WHERE
                                entity_type_code = 'customer'))
            WHERE
            so.created_at > NOW() - INTERVAL 30 DAY";

        $result = $connection->fetchAll($query);

        if (count($result) > 0) {
            $this->helper->addLog("Total Record: ".count($result), "order-report.log");
            $fileName = BP . '/pub/media/' . date('d_m_Y') . "_order_export.csv";
            $header_keys = array_keys($result[0]);
            $header = array_combine($header_keys, $header_keys);
            $this->writeInCsv($header, $header, $appliedRules, $fileName, true);
            foreach ($result as $record) {
                $this->writeInCsv($header, $record, $appliedRules, $fileName);
            }
            //to send mail
            if ($this->file->isExists($fileName)) {
                $this->sendEmail($fileName);
            }
        } else {
            $this->helper->addLog("No Record Found ", "order-report.log");
        }

    }

    public function writeInCsv($header, $record, $appliedRules, $fileName, $is_header = false)
    {
        try {
            foreach ($header as $key) {
                if (!isset($record[$key])) {
                    $record[$key] = ' ';
                }
            }
            if (!$is_header) {
                $appliedRuleIds = $record['applied_rule_ids'];
                $rids = explode(',', $appliedRuleIds);
                $rule_data = '';
                $arids = '';
                foreach ($rids as $id) {
                    if (isset($appliedRules[$id])) {
                        $arids = $arids . ' ' . $id;
                        $rule_data = $rule_data . ' ' . implode(' , ', $appliedRules[$id]);
                    } else {
                        $rule_data = $rule_data . ' ';
                        $arids = $arids . ' ';
                    }

                }
                $record['applied_rule_ids'] = $arids;
                $record['rule_data'] = $rule_data;

            }

            $file = fopen($fileName, "a");
            fputcsv($file, $record);
        } catch (Exception $ex){
            $this->helper->addLog($ex->getMessage(), "order-report.log");
        }
    }

    public function sendEmail($fileUrl){

        $this->inlineTranslation->suspend();
        $emailTemplateVariables = ['message' => "Please find latest order report in the attachment"];

        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);
        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];

        $receiverEmails = explode(',',trim($this->scopeConfig->getValue(self::RECEIVER_EMAIL, ScopeInterface::SCOPE_STORE)));

        $fileName = basename($fileUrl);

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('order_report')
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
            $this->helper->addLog($exception->getMessage(), "order-report.log");
        }
        $this->file->deleteFile($fileUrl);
    }

}
