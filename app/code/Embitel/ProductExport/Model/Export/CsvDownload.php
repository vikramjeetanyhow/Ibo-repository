<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\ProductExport\Model\Export;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as PathDirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Backend\Model\UrlInterface;

/**
 * This classs is to handle CSV operations
 */
class CsvDownload
{
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
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var UrlInterface
     */
    protected $backendUrl;
    protected $products = [];
    protected $headers = [];

    /**
     * @param Csv $csv
     * @param DirectoryList $directoryList
     * @param Filesystem $filesystem
     * @param FileFactory $fileFactory
     * @param UrlInterface $backendUrl
     */
    public function __construct(Csv $csv, DirectoryList $directoryList, Filesystem $filesystem, FileFactory $fileFactory, UrlInterface $backendUrl)
    {
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->directory = $filesystem->getDirectoryWrite(PathDirectoryList::VAR_DIR);
        $this->fileFactory = $fileFactory;
        $this->backendUrl = $backendUrl;
    }

    public function writeNewCSV($data, $pageNum, $filePath = 'export/products.csv')
    {
        $this->directory->create('export/' . $filePath);
        $fileDirectoryPath = $this->directoryList->getPath(PathDirectoryList::VAR_DIR);
        if (!file_exists($fileDirectoryPath . "/" . "export/")) {
            mkdir($fileDirectoryPath . "/" . "export", 0777, true);
        }
        $this->writeCsv($data, "export/" . $filePath . "/" . $pageNum . '.csv');
    }

    /**
     * Write data to CSV file.
     *
     * @param type $status
     * @return boolean
     */
    public function writeCsv($data, $filePath = 'export/products.csv')
    {
        $this->directory->create('export');
        $fileDirectoryPath = $this->directoryList->getPath(PathDirectoryList::VAR_DIR);

        $this->csv->setEnclosure('"')->setDelimiter(',')->saveData($fileDirectoryPath . '/' . $filePath, $data);

        return true;
    }

    /**
     * Get success and failure files url.
     *
     * @param type $status
     * @return type
     */
    public function getBackendUrl($status)
    {
        return $this->backendUrl->getUrl("productimport/import/download", ['filename' => $status]);
    }
}
