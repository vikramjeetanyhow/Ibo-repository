<?php
namespace Embitel\ProductImport\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as PathDirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Embitel\ProductImport\Model\Mail\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use \Psr\Log\LoggerInterface;

class UnpublishProduct
{
    const CRON_ENABLED = 'ebo/ebo_product_publish/send_consolidated_report';
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    const EMAIL_TEMPLATE = 'unpublishedProductReport';
    
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'ebo/ebo_product_publish/consolidated_report_hours';
    
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;
    
    /**
     * @var Csv
     */
    protected $csv;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var Filesystem
     */
    protected $filesystem;
    
    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;
    
    /**
     * @var StateInterface
     */
    protected $inlineTranslation;
    
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Csv $csv
     * @param DirectoryList $directoryList
     * @param Filesystem $filesystem
     * @param string $transportBuilder
     * @param string $inlineTranslation
     * @param string $storeManagerInterface
     * @param string $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Csv $csv,
        DirectoryList $directoryList,
        Filesystem $filesystem,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface,
        LoggerInterface $logger
    ) {
        $this->scopeConfigInterface = $scopeConfig;
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->directory = $filesystem->getDirectoryWrite(PathDirectoryList::VAR_DIR);
        $this->_transportBuilder = $transportBuilder;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_storeManager = $storeManagerInterface;
        $this->logger = $logger;
    }
    
    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfigInterface->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Check if cron is active or not.
     */
    public function isCronActive()
    {
        return $this->getConfig(self::CRON_ENABLED);
    }
    
    /**
     * Generate unpublished report for products.
     *
     * @param type $products
     */
    public function generateReport($products, $isCron = false)
    {
        $reportArray = [];
        foreach ($products as $_product) {
            $isPublished = "no";
            if ($_product->getIsPublished() == 1) {
                $isPublished = "yes";
            }
            $manualUnpublish = "no";
            if ($_product->getManualUnpublish() == 1) {
                $manualUnpublish = "yes";
            }
            $reportArray[] = [
                'sku' => $_product->getSku(),
                'name' => $_product->getName(), 
                'is_published' => $isPublished, 
                'publish_failure_report' => $_product->getPublishFailureReport(), 
                'manual_unpublish' => $manualUnpublish, 
                'manual_unpublish_reason' => $_product->getManualUnpublishReason()
            ];
        }
        
        $this->logger->info(print_r($reportArray, true));
        $this->writeCsv($reportArray);
    }
    
    /**
     * Write data to CSV file.
     *
     * @param type $data
     * @return boolean
     */
    public function writeCsv($data)
    {
        $filePath = 'export/unpublished_report_' . time() . '.csv';
        $this->directory->create('export');
        $fileDirectoryPath = $this->directoryList->getPath(PathDirectoryList::VAR_DIR);

        $header = [
            'sku' => 'sku',
            'name' => 'name',
            'is_published' => 'is_published',
            'publish_failure_report' => 'publish_failure_report',
            'manual_unpublish' => 'manual_unpublish',
            'manual_unpublish_reason' => 'manual_unpublish_reason'
        ];

        array_unshift($data, $header);

        $this->csv
            ->setEnclosure('"')
            ->setDelimiter(',')
            ->saveData($fileDirectoryPath .'/'. $filePath, $data);
        $this->sendEmail($filePath);
        
        return true;
    }
    
    /**
     * Send Email
     *
     * @param string $hours
     * @return void
     */
    public function sendEmail($csvPath)
    {
        $this->logger->info('Starts sending consildated report for MRP change over.');
        $this->_inlineTranslation->suspend();
        $configHours = (int) $this->getConfig(self::CRON_UPDATED_HOURS);
        $emailTemplateVariables = [
            'hours' => $configHours
        ];

        $storeScope = ScopeInterface::SCOPE_STORE;
        $receiverInfo = [
            'email' => $this->scopeConfigInterface->getValue(
                'ebo/ebo_product_publish/consolidated_report_email',
                $storeScope
            )
        ];
        $receiverInfoEmail = str_replace(' ', '', $receiverInfo['email']);
        $recieverEmails = array_map('trim', explode(',', $receiverInfoEmail));

        $senderEmail = $this->scopeConfigInterface->getValue(self::SENDER_EMAIL, $storeScope);
        $senderName = $this->scopeConfigInterface->getValue(self::SENDER_NAME, $storeScope);
        $senderInfo = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        $varExportPath = $this->directoryList->getPath('var');
        if (!empty($csvPath)) {
            $getFileName = explode("/", $csvPath);
            $fileName = $getFileName[1];
            $filePath = $varExportPath.'/' . $csvPath;
            $this->logger->info('Path: '. $filePath);
            $storeId = (int)$this->_storeManager->getStore()->getId();

            $transport = $this->_transportBuilder->setTemplateIdentifier(self::EMAIL_TEMPLATE)
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ]
                )
                ->setTemplateVars($emailTemplateVariables)
                ->setFromByScope($senderInfo)
                ->addTo($recieverEmails)
                ->addAttachment(file_get_contents($filePath), $fileName, 'application/csv')
                ->getTransport();
            try {
                $transport->sendMessage();
                $this->logger->info('Unpublished consolidated report sent by mail!');
            } catch (\Exception $e) {
                $this->logger->critical('Something went wrong while export process. ' . $e->getMessage());
            }
            $this->_inlineTranslation->resume();
        } else {
            $this->logger->info('Unpublished consolidated report not found!');
        }
    }
}
