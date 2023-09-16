<?php

namespace Embitel\ProductExport\Model;

use Embitel\ProductExport\Model\Export\CsvDownload;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterfaceFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Setup\Exception;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;

class InventoryReportGenerate
{

    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';

    private CollectionFactory $collectionFactory;
    private ResourceConnection $resourceConnection;
    private CsvDownload $csvDownload;
    private ProductReportExportHelper $productReportExportHelper;
    private TimezoneInterfaceFactory $timezoneInterfaceFactory;
    private StateInterface $inlineTranslation;
    private ScopeConfigInterface $scopeConfig;
    private DirectoryList $directoryList;
    /**
     * @var \Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder|TransportBuilder
     */
    private $transportBuilder;
    private StoreManager $storeManager;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param CsvDownload $csvDownload
     * @param ProductReportExportHelper $productReportExportHelper
     * @param TimezoneInterfaceFactory $timezoneInterfaceFactory
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param DirectoryList $directoryList
     * @param \Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     */
    public function __construct(CollectionFactory $collectionFactory, ResourceConnection $resourceConnection, CsvDownload $csvDownload, ProductReportExportHelper $productReportExportHelper, TimezoneInterfaceFactory $timezoneInterfaceFactory, StateInterface $inlineTranslation, ScopeConfigInterface $scopeConfig, DirectoryList $directoryList, \Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder $transportBuilder, StoreManager $storeManager)
    {

        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->csvDownload = $csvDownload;
        $this->productReportExportHelper = $productReportExportHelper;
        $this->timezoneInterfaceFactory = $timezoneInterfaceFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->directoryList = $directoryList;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
    }

    public function exportStockData()
    {
        try {
            $this->productReportExportHelper->addLog("Stocks report cron started");
            $fileName = "stocks_report_" . $this->timezoneInterfaceFactory->create()->date()->format("Y-m-d-His") . ".csv";
            $result = $this->csvDownload->writeCsv($this->getStockData(), "export/stocks/" . $fileName);
            if ($result) {
                $emailBody = 'Please find latest stocks report in the attachment';
                $this->sentMail($emailBody);
            } else {
                $this->productReportExportHelper->addLog("Error while saving the report");
            }
            $this->productReportExportHelper->addLog("Stocks report cron ended");
        } catch (\Exception $e) {
            $emailBody = 'Something went wrong while export process';
            $this->sentMail($emailBody);
        }

    }

    public function getStockData(): array
    {
        $productList = $this->getEnabledSimpleProductList();
        $productIds = implode('","', array_column($productList, "entity_id"));
        $productIdSku = array_column($productList, "sku", "entity_id");
        $sql = ' select product_id, qty, is_in_stock
from cataloginventory_stock_item where product_id in ("' . $productIds . '") ';

        $stockData = $this->resourceConnection->getConnection()->fetchAll($sql);

        $combinedResult = [];
        $combinedResult[] = ["sku" => "sku", "quantity" => "quantity", "stock_status" => "stock_status"];
        foreach ($stockData as $stock) {
            $combinedResult[] = ["sku" => $productIdSku[$stock['product_id']], "quantity" => $stock['qty'], "stock_status" => $stock['is_in_stock'] ? "In Stock" : "Out Of Stock"];
        }

        return $combinedResult;
    }

    public function getEnabledSimpleProductList(): array
    {
        $productCollection = $this->collectionFactory->create();
        return $productCollection->addAttributeToFilter('type_id', ['eq' => 'simple'])->addAttributeToFilter('status', ['eq' => true])->toArray(['entity_id', 'sku']);
    }

    public function sentMail(string $emailBody)
    {
        $this->productReportExportHelper->addLog("Stock Report Email Send Start");

        $this->inlineTranslation->suspend();

        $emailTemplateVariables = ['message' => $emailBody];

        $receiverEmails = array_map('trim', explode(',', $this->productReportExportHelper->getConfigs()->getReceiverEmails()));

        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);

        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];
        $exportPath = $this->directoryList->getPath('var') . '/export/stocks/';
        $files = scandir($exportPath, SCANDIR_SORT_DESCENDING);

        if (isset($files[0])) {
            $this->productReportExportHelper->addLog("Stock Report Available End");
            $filePath = $exportPath . $files[0];
            $fileName = $files[0];

            $transport = $this->transportBuilder->setTemplateIdentifier('stocks_report')->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])->setTemplateVars($emailTemplateVariables)->setFrom($senderInfo)->addTo($receiverEmails)->addAttachment(file_get_contents($filePath), $fileName, 'application/csv')->getTransport();

            try {
                $transport->sendMessage();
            } catch (\Exception $exception) {
                $this->productReportExportHelper->addLog($exception->getMessage());
            }
            $this->inlineTranslation->resume();
        }

        $this->productReportExportHelper->addLog("Stock Report Email Send End");
    }

}