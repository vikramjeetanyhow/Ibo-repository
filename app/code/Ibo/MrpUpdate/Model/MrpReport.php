<?php
namespace Ibo\MrpUpdate\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as PathDirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Ibo\MrpUpdate\Model\Mail\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use \Psr\Log\LoggerInterface;

class MrpReport
{
    const CRON_ENABLED = 'ebo/ebo_product_publish/log_active';
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    const EMAIL_TEMPLATE = 'mrpPriceReport';
    
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'mrpprice_update/settings/consolidated_report_hours';
    
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
     * Generate MRP change over report for products.
     *
     * @param type $products
     */
    public function generateReport($products, $isCron = false)
    {
        $reportArray = [];
        foreach ($products as $_product) {
            $productStatus = "disabled";
            $productStatusEnable = Status::STATUS_ENABLED;
            if ($_product->getStatus() == $productStatusEnable) {
                $productStatus = "enabled";
            }
            $oldMrp = floatval(preg_replace('/[^\d.]/', '', $_product->getOldMrp()));
            $newMrp = floatval(preg_replace('/[^\d.]/', '', $_product->getMrp()));
            if ($oldMrp == $newMrp) {
                continue;
            }
            $reportArray[] = [
                'sku' => $_product->getSku(),
                'name' => $_product->getName(),
                'status' => $productStatus,
                'old_mrp' => $_product->getOldMrp(),
                'mrp' => $_product->getMrp(),
                'mrp_changeover_message' => $_product->getMrpChangeoverMessage(),
                'mrp_updated_at' => $_product->getMrpUpdatedAt(),
                'department' => $_product->getDepartment(),
                'class' => $_product->getClass(),
                'subclass' => $_product->getSubclass()
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
        $filePath = 'export/mrp_report_' . time() . '.csv';
        $this->directory->create('export');
        $fileDirectoryPath = $this->directoryList->getPath(PathDirectoryList::VAR_DIR);

        $header = [
            'sku' => 'sku',
            'name' => 'name',
            'status' => 'status',
            'old_mrp' => 'old_mrp',
            'mrp' => 'mrp',
            'mrp_changeover_message' => 'mrp_changeover_message',
            'mrp_updated_at' => 'mrp_updated_at',
            'department' => 'department',
            'class' => 'class',
            'subclass' => 'subclass'
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
                'mrpprice_update/settings/reciever_email',
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
                $this->logger->info('MRP change consolidated report sent by mail!');
            } catch (\Exception $e) {
                $this->logger->critical('Something went wrong while export process. ' . $e->getMessage());
            }
            $this->_inlineTranslation->resume();
        } else {
            $this->logger->info('MRP change consolidated report not found!');
        }
    }
}
