<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\ProductImport\Controller\Adminhtml\Import;

use Embitel\ProductImport\Controller\Adminhtml\Import;
use Embitel\ProductImport\Model\Import\ProductImportHandler;
use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\TimezoneInterfaceFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;

class Post extends Import
{
    private Context $context;
    private Filesystem $filesystem;
    private UploaderFactory $fileUploaderFactory;
    private TimezoneInterfaceFactory $timezoneInterfaceFactory;
    private Filesystem\Directory\WriteInterface $__varDirectory;
    private \Embitel\ProductImport\Model\EboImportFactory $eboImportFactory;
    private \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     * @param UploaderFactory $fileUploaderFactory
     * @param TimezoneInterfaceFactory $timezoneInterfaceFactory
     * @param \Embitel\ProductImport\Model\EboImportFactory $eboImportFactory
     * @param \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(Context $context,
                                FileFactory $fileFactory,
                                Filesystem $filesystem,
                                UploaderFactory $fileUploaderFactory,
                                TimezoneInterfaceFactory $timezoneInterfaceFactory,
    \Embitel\ProductImport\Model\EboImportFactory $eboImportFactory,
    \Embitel\ProductImport\Model\ResourceModel\EboImport $eboImportResourceModel
    )
    {
        $this->context = $context;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->timezoneInterfaceFactory = $timezoneInterfaceFactory;
        $this->__varDirectory = $filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $this->eboImportFactory = $eboImportFactory;
        $this->eboImportResourceModel = $eboImportResourceModel;
        parent::__construct($context, $fileFactory);
    }

    /**
     * Product import action
     *
     * @return Redirect
     */
    public function execute()
    {
        $productImportFile = $this->getRequest()->getFiles('productimport_file');
        $bulkUpload = $this->getRequest()->getParam('is_bulk_upload');
        if ($bulkUpload == "on") {

            try {

                /** @var $importHandler ProductImportHandler */
                $importHandler = $this->_objectManager->create(ProductImportHandler::class);
                $importHandler->getFilteredCsvData($productImportFile['tmp_name']);

                $target = $this->__varDirectory->getAbsolutePath('import');

                $uploader = $this->fileUploaderFactory->create(['fileId' => 'productimport_file']);
                /** Allowed extension types */
                $uploader->setAllowedExtensions(['csv']);
                $timestamp = $this->timezoneInterfaceFactory->create()->date()->getTimestamp();
                $filename = $timestamp . '_' . $productImportFile['name'];
                $result = $uploader->save($target, $filename);

                $eboImport = $this->eboImportFactory->create();

                $eboImport->setIsBulkUpload(true);
                $eboImport->setUploadFileName($filename);

                $this->eboImportResourceModel->save($eboImport);


                $this->messageManager->addSuccessMessage("Bulk Import Scheduled Successfully");
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }


        } else {
            if ($this->getRequest()->isPost() && isset($productImportFile['tmp_name'])) {
                try {
                    /** @var $importHandler ProductImportHandler */
                    $importHandler = $this->_objectManager->create(ProductImportHandler::class);
                    $response = $importHandler->importFromCsvFile($productImportFile);

                    //$this->messageManager->addSuccess(__('The products has been imported.'));
                    if (isset($response['success'])) {
                        $message = __("Success records. Click <a href=\"%1\">here</a> to download file.", $importHandler->getBackendUrl('success_' . $response['uniqid']));
                        $this->messageManager->addSuccess($message);
                    }

                    if (isset($response['odoo'])) {
                        $message = __("Odoo records. Click <a href=\"%1\">here</a> to download file.", $importHandler->getBackendUrl('odoo_' . $response['uniqid']));
                        $this->messageManager->addSuccess($message);
                    }

                    if (isset($response['failure'])) {
                        $message = __("Failed records. Click <a href=\"%1\">here</a> to download file.", $importHandler->getBackendUrl('failure_' . $response['uniqid']));
                        $this->messageManager->addError($message);
                    }
                } catch (LocalizedException $e) {
                    $this->messageManager->addError($e->getMessage());
                } catch (Exception $e) {
                    $this->messageManager->addError(__('Invalid file upload attempt' . $e->getMessage()));
                }
            } else {
                $this->messageManager->addError(__('Invalid file upload attempt'));
            }
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRedirectUrl());
        return $resultRedirect;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Embitel_ProductImport::import');
    }
}
