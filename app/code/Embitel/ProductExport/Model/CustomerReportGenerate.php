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

class CustomerReportGenerate
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';
    private ResourceConnection $resourceConnection;
    private CustomerReportExportHelper $customerReportExportHelper;
    private TimezoneInterfaceFactory $timezoneInterfaceFactory;
    private StateInterface $inlineTranslation;
    private ScopeConfigInterface $scopeConfig;
    private DirectoryList $directoryList;
    private TransportBuilder $transportBuilder;
    private StoreManager $storeManager;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CustomerReportExportHelper $customerReportExportHelper
     * @param TimezoneInterfaceFactory $timezoneInterfaceFactory
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param DirectoryList $directoryList
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     */
    public function __construct(
        ResourceConnection         $resourceConnection,
        CustomerReportExportHelper $customerReportExportHelper,
        TimezoneInterfaceFactory   $timezoneInterfaceFactory,
        StateInterface             $inlineTranslation,
        ScopeConfigInterface       $scopeConfig,
        DirectoryList              $directoryList,
        TransportBuilder           $transportBuilder,
        StoreManager               $storeManager
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->customerReportExportHelper = $customerReportExportHelper;
        $this->timezoneInterfaceFactory = $timezoneInterfaceFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->directoryList = $directoryList;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
    }

    public function exportCustomerDetails()
    {
        try {
            $this->customerReportExportHelper->addLog('Customer Report Cron Started');
            $fileLocation = $this->getCustomerReport();
            $emailBody = 'Please find latest customer export';
            $this->sendMail($emailBody, $fileLocation);
            $this->customerReportExportHelper->addLog('Customer Report Cron Ended');
        } catch (Exception $exception) {
            $emailBody = 'Something went worng while export process';
            $this->sendMail($emailBody);
        }
    }

    public function getCustomerReport(): string
    {
        $rootExportFolder = BP . '/var/export/customer_data/';
        $filename = "customer_export_" . $this->timezoneInterfaceFactory->create()->date()->format('Y_m_d') . ".csv";
        $absoluteFilePath = $rootExportFolder . $this->timezoneInterfaceFactory->create()->date()->format('Y_m_d') . '/' . $filename;

        if (file_exists($absoluteFilePath)) {
            return $absoluteFilePath;
        }

        $customerGroupSql = "select customer_group_id, customer_group_code from customer_group;";

        $customerGroupData = array_column($this->resourceConnection->getConnection()->fetchAll($customerGroupSql), 'customer_group_code', 'customer_group_id');


        $customerDetailsSql = "select ce.entity_id as `customer_id`,
       ce.mobilenumber as `mobile_number`,
       group_id       `customer_group`,
       eaov.value  as `customer_type`,
       ce.created_at as `created_at`
from customer_entity ce
         left join customer_entity_int cei
                   on ce.entity_id = cei.entity_id && cei.attribute_id = (select ea.attribute_id
                                                                          from eav_attribute ea
                                                                          where ea.attribute_code = 'customer_type' &&
                                                                                ea.entity_type_id =
                                                                                (select entity_type_id
                                                                                 from eav_entity_type
                                                                                 where entity_type_code = 'customer'))
         left join eav_attribute_option_value eaov on cei.value = eaov.option_id && eaov.value = 0;";

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
        $this->customerReportExportHelper->addLog('Customer Export Email Send Start');

        $this->inlineTranslation->suspend();

        $emailTemplateVariables = ['message' => $emailBody];

        $receiverEmails = array_map('trim', explode(',', $this->customerReportExportHelper->getConfigs()->getReceiverEmails()));

        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);

        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];

        $transport = $this->transportBuilder->setTemplateIdentifier('customer_report')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])->setTemplateVars($emailTemplateVariables)->setFrom($senderInfo)->addTo($receiverEmails)->addAttachment(file_get_contents($fileLocation), 'customerExport.csv', 'application/csv')->getTransport();

        try{
            $transport->sendMessage();
        } catch (\Exception $exception) {
            $this->customerReportExportHelper->addLog($exception->getMessage());
        }

        $this->inlineTranslation->resume();

        $this->customerReportExportHelper->addLog('Customer Export Email Send End');
    }

}