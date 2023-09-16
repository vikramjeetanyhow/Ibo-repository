<?php

namespace Embitel\ProductImport\Cron;

use Embitel\ProductImport\Model\Import\CsvProcessor;
use Embitel\ProductImport\Model\Import\ProductFieldProcessor;
use Embitel\ProductImport\Model\Import\ProductImportHandler;
use Embitel\ProductImport\Model\ProductBulkImport;
use Magento\Framework\App\State;

class ProductImport
{
    private ProductImportHandler $productImportHandler;
    private \Embitel\ProductImport\Model\ResourceModel\EboImport\CollectionFactory $collectionFactory;
    private \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel;
    private CsvProcessor $csvProcessor;
    private ProductFieldProcessor $productFieldProcessor;
    private State $appState;
    private ProductBulkImport $bulkImportHelper;

    /**
     * @param ProductImportHandler $productImportHandler
     * @param \Embitel\ProductImport\Model\ResourceModel\EboImport\CollectionFactory $collectionFactory
     * @param \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel
     * @param CsvProcessor $csvProcessor
     * @param ProductFieldProcessor $productFieldProcessor
     * @param ProductBulkImport $bulkImportHelper
     */
    public function __construct(
        ProductImportHandler $productImportHandler,
        \Embitel\ProductImport\Model\ResourceModel\EboImport\CollectionFactory $collectionFactory,
        \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel,
        CsvProcessor $csvProcessor,
        ProductFieldProcessor $productFieldProcessor,
        ProductBulkImport $bulkImportHelper
    )
    {
        $this->productImportHandler = $productImportHandler;
        $this->collectionFactory = $collectionFactory;
        $this->eboImportResourceModel = $eboImportResourceModel;
        $this->csvProcessor = $csvProcessor;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->bulkImportHelper = $bulkImportHelper;
    }

    public function execute()
    {

        if (!$this->bulkImportHelper->isCronActive()) {
            return;
        }

        /** @var \Embitel\ProductImport\Model\ResourceModel\EboImport\Collection $productImportCollection */
        $productImportCollection = $this->collectionFactory->create();
        $productImportCollection->addFieldToFilter('is_bulk_upload', ['eq' => true])->setPageSize(1)->addFieldToFilter('bulk_upload_status', ['eq' => 'pending'])->load();

        /** @var \Embitel\ProductImport\Model\EboImport $eboImport */
        foreach ($productImportCollection as $eboImport) {
            $eboImport->setBulkUploadStatus('processing');
            $this->eboImportResourceModel->save($eboImport);
            $filepath['tmp_name'] = BP . '/var/import/' . $eboImport->getUploadFileName();
            $filepath['name'] = $eboImport->getUploadFileName();
            $this->productImportHandler->importFromCsvFile($filepath, $eboImport->getId());
        }

    }

}
