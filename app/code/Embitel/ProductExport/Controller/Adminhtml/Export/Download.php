<?php

namespace Embitel\ProductExport\Controller\Adminhtml\Export;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Response\Http\FileFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use ZipArchive;

class Download extends Action implements HttpGetActionInterface
{

    private DirectoryList $directoryList;
    private FileFactory $fileFactory;
    private Filesystem $filesystem;

    /**
     * @param DirectoryList $directoryList
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     * @param Context $context
     */
    public function __construct(DirectoryList $directoryList, FileFactory $fileFactory, Filesystem $filesystem, Context $context)
    {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
    }

    public function execute()
    {
        try {
            $postData = $this->getRequest()->getParams();
            unset($postData['key']);
            if (!class_exists('\ZipArchive')) {
                throw new LocalizedException(__('ZipArchive class not found'));
            }

            $dir = $this->directoryList->getPath(DirectoryList::VAR_DIR);
            $destination = $dir . "/export/" . $postData['filename'] . "/" . $postData['filename'] . ".zip";
            $exportDir = $dir . "/export/" . $postData['filename'];
            $result = $exportDir . "/" . $postData['filename'] . ".csv";
            $files = scandir($exportDir, SCANDIR_SORT_ASCENDING);
            unset($files[0]);
            unset($files[1]);

            if (!file_exists($exportDir . "/" . $result . '.csv') && count($files) != 1) {
                $this->joinFiles($files, $result, $exportDir);
            }

            $zip = new ZipArchive();

            $res = $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $files = scandir($exportDir, SCANDIR_SORT_ASCENDING);
            unset($files[0]);
            unset($files[1]);

            if ($res) {

                foreach ($files as $file) {
                    $fileLocation = $exportDir . "/" . $file;

                    if (file_exists($fileLocation)) {
                        $zip->addFile($fileLocation);
                    }
                }
            } else {
                $error = "error while creating a zip file";
            }

            $zip->close();

            return $this->fileFactory->create($postData['filename'] . ".zip", ['type' => 'filename', 'value' => $destination, 'rm' => true]);

        } catch (Exception $exception) {
            $this->messageManager->addErrorMessage('No Products available to export');
            return $this->resultRedirectFactory->create()->setUrl($this->_redirect->getRefererUrl());
        }

    }

    public function joinFiles(array $files, $result, $exportDir)
    {
        $length = count($files);
        if (!is_array($files)) {
            throw new Exception('`$files` must be an array');
        }
        $wH = fopen($result, "w+");

        for ($i = 1; $i < $length + 1; $i++) {
            $file = $exportDir . "/" . $i . ".csv";
            if (file_exists($file)) {
                $fh = fopen($file, "r");
                while (!feof($fh)) {
                    fwrite($wH, fgets($fh));
                }
                fclose($fh);
                unset($fh);
                unlink($file);
            }
        }
        fclose($wH);
        unset($wH);
    }
}