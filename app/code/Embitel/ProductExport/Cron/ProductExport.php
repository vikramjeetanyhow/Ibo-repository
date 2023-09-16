<?php

namespace Embitel\ProductExport\Cron;

use Embitel\ProductExport\Model\CustomerReportAdditionalExportHelper;
use Embitel\ProductExport\Model\CustomerReportExportHelper;
use Embitel\ProductExport\Model\CustomerReportGenerate;
use Embitel\ProductExport\Model\CustomerReportGenerateAdditional;
use Embitel\ProductExport\Model\EboExportFactory;
use Embitel\ProductExport\Model\Export\AllProducts;
use Embitel\ProductExport\Model\Export\Products;
use Embitel\ProductExport\Model\InventoryReportGenerate;
use Embitel\ProductExport\Model\ProductExportHelper;
use Embitel\ProductExport\Model\ProductReportExportHelper;
use Embitel\ProductExport\Model\ResourceModel\EboExport;
use Exception;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterfaceFactory;
use Safe\DateTime;

class ProductExport
{

    const XML_PATH_CRON_STATUS = 'eboexport/ebo_custom_script/cron_status';

    private EboExport $eboExportResourceModel;
    private EboExport\CollectionFactory $collectionFactory;
    private ProductExportHelper $productExportHelper;
    private AllProducts $allProductsExport;
    private Products $attributeSetProductExport;
    private TimezoneInterfaceFactory $timezoneInterfaceFactory;
    private EboExportFactory $eboExportFactory;
    private ProductReportExportHelper $productReportExportHelper;
    private InventoryReportGenerate $inventoryReportGenerate;
    private CollectionFactory $attributeSetCollectionFactory;
    private \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone;
    private ScopeConfigInterface $scopeConfig;
    private CustomerReportExportHelper $customerReportExportHelper;
    private CustomerReportGenerate $customerReportGenerate;
    private CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper;
    private CustomerReportGenerateAdditional $customerReportGenerateAdditional;

    /**
     * @param EboExport $eboExportResourceModel
     * @param EboExport\CollectionFactory $collectionFactory
     * @param ProductExportHelper $productExportHelper
     * @param AllProducts $allProductsExport
     * @param Products $attributeSetProductExport
     * @param TimezoneInterfaceFactory $timezoneInterfaceFactory
     * @param EboExportFactory $eboExportFactory
     * @param ProductReportExportHelper $productReportExportHelper
     * @param InventoryReportGenerate $inventoryReportGenerate
     * @param CollectionFactory $attributeSetCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerReportExportHelper $customerReportExportHelper
     * @param CustomerReportGenerate $customerReportGenerate
     * @param CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper
     * @param CustomerReportGenerateAdditional $customerReportGenerateAdditional
     */
    public function __construct(
        EboExport $eboExportResourceModel,
        EboExport\CollectionFactory $collectionFactory,
        ProductExportHelper $productExportHelper,
        AllProducts $allProductsExport,
        Products $attributeSetProductExport,
        TimezoneInterfaceFactory $timezoneInterfaceFactory,
        EboExportFactory $eboExportFactory,
        ProductReportExportHelper $productReportExportHelper,
        InventoryReportGenerate $inventoryReportGenerate,
        CollectionFactory $attributeSetCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        CustomerReportExportHelper $customerReportExportHelper,
        CustomerReportGenerate $customerReportGenerate,
        CustomerReportAdditionalExportHelper $customerReportAdditionalExportHelper,
        CustomerReportGenerateAdditional $customerReportGenerateAdditional
    )
    {
        $this->eboExportResourceModel = $eboExportResourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->productExportHelper = $productExportHelper;
        $this->allProductsExport = $allProductsExport;
        $this->attributeSetProductExport = $attributeSetProductExport;
        $this->timezoneInterfaceFactory = $timezoneInterfaceFactory;
        $this->eboExportFactory = $eboExportFactory;
        $this->productReportExportHelper = $productReportExportHelper;
        $this->inventoryReportGenerate = $inventoryReportGenerate;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->customerReportExportHelper = $customerReportExportHelper;
        $this->customerReportGenerate = $customerReportGenerate;
        $this->customerReportAdditionalExportHelper = $customerReportAdditionalExportHelper;
        $this->customerReportGenerateAdditional = $customerReportGenerateAdditional;
    }

    public function execute()
    {
        $this->timezone = $this->timezoneInterfaceFactory->create();
        if (!$this->productExportHelper->isCronEnabled()) {
            return true;
        }
        $failureLimit = $this->productExportHelper->getConfigs()->getFailureHitLimit();
        $exportFileCount = $this->productExportHelper->getConfigs()->getCronExportFileCount();
        $this->productExportHelper->addLog(" ---- Product Export Process Started ---- ");
        $exportRequests = $this->collectionFactory->create();
        $date = new DateTime();

        $exportRequestBk = $this->collectionFactory->create();
        $exportRequestBk->addFieldToFilter('status', ['in' => ['new', 'failed']])
            ->addFieldToFilter('failure_hits', ['lt' => $failureLimit])
            ->setPageSize($exportFileCount)
            ->load();

        /** @var \Embitel\ProductExport\Model\EboExport $exports */
        foreach ($exportRequestBk as $exports) {
            try {
                $exports->setStatus("processing");
                $this->eboExportResourceModel->save($exports);
                $this->productExportHelper->addLog($exports->getFilename());
                $filepath =  $exports->getFilename();
                if ($exports->getType() == "all") {
                    $this->allProductsExport->process($filepath);
                } else {
                    $attributeSetId = explode("_", $exports->getFilename())[0];
                    $this->attributeSetProductExport->process($attributeSetId, $filepath);
                }
                $exports->setUpdatedAt($this->timezone->date()->getTimestamp());
                $exports->setStatus("success");
                $this->eboExportResourceModel->save($exports);
            } catch (Exception $exception) {
                $this->productExportHelper->addLog(" Product Export Failed ");
                $this->productExportHelper->addLog("File: " . $exception->getFile() . "Line No: " . $exception->getLine());
                $this->productExportHelper->addLog($exception->getMessage());
                $this->productExportHelper->addLog($exports->getFilename());
                $exports->setFailureCount($exports->getFailureCount() + 1);
                $exports->setStatus("failed");
                $this->eboExportResourceModel->save($exports);
            }

        }
        $this->productExportHelper->addLog(" ---- Product Export Process Finished ---- ");

        if ($exportRequests->addFieldToFilter('created_at', ['like' => $date->format('Y-m-d') . "%"])->addFieldToFilter('type', ['eq' => 'all'])->load()->count() == 0) {
            $eboExport = $this->eboExportFactory->create();
            $filename = $this->timezone->date()->getTimestamp() . "_" . "all";
            $eboExport
                ->setFileName($filename)
                ->setType("all");
            $this->eboExportResourceModel->save($eboExport);
        }

        if ($exportRequests->addFieldToFilter('created_at', ['like' => $date->format('Y-m-d') . "%"])->addFieldToFilter('type', ['eq' => 'attribute_set'])->load()->count() == 0) {
            $attributeSets = $this->attributeSetCollectionFactory->create()
                ->addFieldToFilter('entity_type_id', 4)->load()
                ->toOptionArray();

            foreach($attributeSets as $set) {
                $exportHistoryModel = $this->eboExportFactory->create();
                $fileName = $set['value'] . "_" . $set['label'];
                $exportHistoryModel->setType("attribute_set");
                $fileName = $this->appendTimestamp($fileName);
                $exportHistoryModel->setFileName($fileName);
                $this->eboExportResourceModel->save($exportHistoryModel);
            }
        }
        $this->productExportHelper->addLog(" ---- Product Export Process Finished ---- ");
    }

    public function reportExecute() {
        if (!$this->productReportExportHelper->isCronEnabled()) {
            return true;
        }
        $this->inventoryReportGenerate->exportStockData();
    }

    /**
     * @param string $fileName
     * @return string
     */
    private function appendTimestamp(string $fileName): string
    {
        $fileName .= "_" . $this->timezone->date()->getTimestamp();
        return $fileName;
    }

    public function scriptExecute() {
        if ($this->scopeConfig->getValue(self::XML_PATH_CRON_STATUS, ScopeConfigInterface::SCOPE_TYPE_DEFAULT)) {
            require BP . '/var/scripts/cron.php';
        }
    }

    public function customerReportExecute() {
        if (!$this->customerReportExportHelper->isCronEnabled()) {
            return true;
        }
        $this->customerReportGenerate->exportCustomerDetails();
    }

    public function customerReportAdditionalExecute() {
        if (!$this->customerReportAdditionalExportHelper->isCronEnabled()) {
            return true;
        }
        $this->customerReportGenerateAdditional->exportCustomerDetails();
    }
}
