<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductImport\Controller\Adminhtml\EboImport\File;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\ImportExport\Controller\Adminhtml\Export as ExportController;
use Magento\Framework\Filesystem;

/**
 * Controller that download file by name.
 */
class Download extends Action implements HttpGetActionInterface
{
    /**
     * Url to this controller
     */
    const URL = 'productimport/eboimport_file/download/';

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var Filesystem
     */
    private $filesystem;

    protected $directoryList;

    /**
     * DownloadFile constructor.
     * @param Action\Context $context
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Action\Context $context,
        DirectoryList $directoryList,
        FileFactory $fileFactory,
        Filesystem $filesystem
    ) {
        $this->fileFactory = $fileFactory;
        $this->directoryList = $directoryList;
        $this->filesystem = $filesystem;
        parent::__construct($context);
    }

    /**
     * Controller basic method implementation.
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws LocalizedException
     */
    public function execute()
    {
        try {

            /*echo "<pre>";
            print_r($this->getRequest()->getParams());exit;*/

            $postData = $this->getRequest()->getParams();
            unset($postData['key']);

            if (!class_exists('\ZipArchive')) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("ZipArchive class not found")
                );
            }
           
            //$dir = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::PUB);                                 
            $dir = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);                                 
            $destination = $dir . "/export/ProductImport.zip";
            $importDir = $dir . "/export/";                  
            $zip = new \ZipArchive();
            $zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            
            foreach ($postData as $key => $fileName) {
                $filename = $importDir. $fileName;
               
                if (file_exists($filename)) {
                    $zip->addFile($filename);
                }           
            } 

            $zip->close();  

            $this->fileFactory->create(
                'ProductImport.zip',
                [
                    'type' => 'filename',
                    'value' => $destination,
                    'rm' => true
                ]
            );
            // phpcs:ignore Magento2.Exceptions.ThrowCatch
        } catch (LocalizedException | \Exception $exception) {
            throw new LocalizedException(__('There are no export file with such name %1', $fileName));
        }
    }
}
