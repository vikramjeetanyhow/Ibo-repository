<?php

namespace Embitel\ProductExport\Model;

use Exception;
use Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterfaceFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;

class CustomerReportGenerateAdditional
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';
    private ResourceConnection $resourceConnection;
    private TimezoneInterfaceFactory $timezoneInterfaceFactory;
    private StateInterface $inlineTranslation;
    private ScopeConfigInterface $scopeConfig;
    private DirectoryList $directoryList;
    private TransportBuilder $transportBuilder;
    private StoreManager $storeManager;
    private CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper
     * @param TimezoneInterfaceFactory $timezoneInterfaceFactory
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param DirectoryList $directoryList
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     */
    public function __construct(
        ResourceConnection         $resourceConnection,
        CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper,
        TimezoneInterfaceFactory   $timezoneInterfaceFactory,
        StateInterface             $inlineTranslation,
        ScopeConfigInterface       $scopeConfig,
        DirectoryList              $directoryList,
        TransportBuilder           $transportBuilder,
        StoreManager               $storeManager
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->timezoneInterfaceFactory = $timezoneInterfaceFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->directoryList = $directoryList;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->customerReportAdditionalExportHelper = $customerReportAdditionalExportHelper;
    }

    public function exportCustomerDetails()
    {
        try {
            $this->customerReportAdditionalExportHelper->addLog('Customer Report Additional Cron Started');
            $fileLocation = $this->getCustomerReport();
            $emailBody = 'Please find latest customer additional export';
            $this->sendMail($emailBody, $fileLocation);
            $this->customerReportAdditionalExportHelper->addLog('Customer Report Additional Cron Ended');
        } catch (Exception $exception) {
            $emailBody = 'Something went worng while export process';
            $this->sendMail($emailBody);
        }
    }

    public function getCustomerReport(): string
    {
        $rootExportFolder = BP . '/var/export/customer_data/';
        $filename = "customer_export_additional_" . $this->timezoneInterfaceFactory->create()->date()->format('Y_m_d') . ".csv";
        $absoluteFilePath = $rootExportFolder . $this->timezoneInterfaceFactory->create()->date()->format('Y_m_d') . '/' . $filename;

        if (file_exists($absoluteFilePath)) {
            return $absoluteFilePath;
        }

        $customerGroupSql = "select customer_group_id, customer_group_code from customer_group;";

        $customerGroupData = array_column($this->resourceConnection->getConnection()->fetchAll($customerGroupSql), 'customer_group_code', 'customer_group_id');


        $customerDetailsSql = "select ce.entity_id    as `customer_id`,
       ce.firstname    as `firstname`,
       ce.lastname     as `lastname`,
       ce.group_id     as `customer_group`,
       ce.mobilenumber as 'mobile_number',
       eaov.value      as `customer_type`,
       eaova.value     as `approval_status`,
       ce.created_at   as `created_at`,
       ce.updated_at   as `updated_at`,
       eaova.value     as `approval_status`
from customer_entity ce
         left join customer_entity_int cei
                   on ce.entity_id = cei.entity_id && cei.attribute_id = (select ea.attribute_id
                                                                          from eav_attribute ea
                                                                          where ea.attribute_code = 'customer_type' &&
                                                                                ea.entity_type_id =
                                                                                (select entity_type_id
                                                                                 from eav_entity_type
                                                                                 where entity_type_code = 'customer'))
         left join eav_attribute_option_value eaov on cei.value = eaov.option_id && eaov.store_id = 0
         left join customer_entity_int ceia
                   on ce.entity_id = ceia.entity_id && ceia.attribute_id = (select ea.attribute_id
                                                                           from eav_attribute ea
                                                                           where ea.attribute_code like
                                                                                 'approval_status' &&
                                                                                 ea.entity_type_id =
                                                                                 (select entity_type_id
                                                                                  from eav_entity_type
                                                                                  where entity_type_code = 'customer'))
        left join eav_attribute_option_value eaova on ceia.value = eaova.option_id && eaova.store_id = 0
where eaov.value = 'Professional - under approval' && ceia.value is not null;";

        $customerData = $this->resourceConnection->getConnection()->fetchAll($customerDetailsSql);

        $pagedArray = array_chunk($customerData, 1000, true);

        $header = [];
        $count = 0;
        if (!is_dir($rootExportFolder)) {
            mkdir($rootExportFolder);
        }

        $folder = $rootExportFolder . $this->timezoneInterfaceFactory->create()->date()->format('Y_m_d');

        if (!is_dir($folder)) {
            mkdir($folder);
        }
        $path = $folder . '/' . $filename;

        $file = fopen($path, 'w');

        foreach ($pagedArray as $key => $value) {

            if (!$count) {
                $header_keys = array_keys($value[0]);
                $header = array_combine($header_keys, $header_keys);
                $this->writeInCsv($header, $file);
                $count++;
            }

            foreach ($value as $data) {
                foreach ($header as $rKey) {
                    if (!isset($data[$rKey])) {
                        $data[$rKey] = ' ';
                    }
                    if ($rKey == 'customer_group') {
                        $data[$rKey] = $customerGroupData[$data[$rKey]];
                    }
                }
                $this->writeInCsv($data, $file, $key);
            }
        }
        return $absoluteFilePath;
    }

    public function writeInCsv($record, $file, $key = 0)
    {
        fputcsv($file, $record);
    }

    public function sendMail($emailBody, $fileLocation = null)
    {
        $this->customerReportAdditionalExportHelper->addLog('Customer Export Additional Email Send Start');

        $this->inlineTranslation->suspend();

        $emailTemplateVariables = ['message' => $emailBody];

        $receiverEmails = array_map('trim', explode(',', $this->customerReportAdditionalExportHelper->getConfigs()->getReceiverEmails()));

        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);

        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];

        $transport = $this->transportBuilder->setTemplateIdentifier('customer_report')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])->setTemplateVars($emailTemplateVariables)->setFrom($senderInfo)->addTo($receiverEmails)->addAttachment(file_get_contents($fileLocation), 'customerExport.csv', 'application/csv')->getTransport();

        try{
            $transport->sendMessage();
        } catch (\Exception $exception) {
            $this->customerReportAdditionalExportHelper->addLog($exception->getMessage());
        }

        $this->inlineTranslation->resume();

        $this->customerReportAdditionalExportHelper->addLog('Customer Export Additional Email Send End');
    }

}
