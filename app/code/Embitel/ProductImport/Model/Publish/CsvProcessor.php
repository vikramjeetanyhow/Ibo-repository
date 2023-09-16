<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Publish;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as PathDirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Backend\Model\UrlInterface;
use Embitel\ProductImport\Model\EboPublishFactory;

/**
 * This classs is to handle CSV operations
 */
class CsvProcessor
{
    /**
     * @var \Embitel\ProductImport\Model\EboPublishFactory
     */
    protected $eboPublishFactory;

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
     * @param EboPublishFactory $eboPublishFactory
     * @param UrlInterface $backendUrl
     */
    public function __construct(
        Csv $csv,
        DirectoryList $directoryList,
        Filesystem $filesystem,
        FileFactory $fileFactory,
        EboPublishFactory $eboPublishFactory,
        UrlInterface $backendUrl
    ) {
        $this->csv = $csv;
        $this->directoryList = $directoryList;
        $this->directory = $filesystem->getDirectoryWrite(PathDirectoryList::VAR_DIR);
        $this->fileFactory = $fileFactory;
        $this->backendUrl = $backendUrl;
        $this->eboPublishFactory = $eboPublishFactory;
    }

    /**
     * Read CSV data
     *
     * @param type $fileName
     * @return type
     */
    public function getCsvData($fileName)
    {
        return $this->csv->getData($fileName);
    }

    /**
     * Prepare response for download.
     *
     * @param type $products
     * @return boolean
     */
    public function prepareResponse($products)
    {
        $this->products = $products;
        $response = [];
        $uniq = uniqid();
        if (isset($products['success'])) {
            $this->headers = array_keys($products['success'][0]);
            $response['success'] = $this->writeCsv('success', 'success_'.$uniq);
        }
        
        if (isset($products['failure'])) {
            $this->headers = array_keys($products['failure'][0]);
            $response['failure'] = $this->writeCsv('failure', 'failure_'.$uniq);
        }

        $response['uniqid'] = $uniq;
        $ebopublish = $this->eboPublishFactory->create();
        $ebopublish->addData([
            'success_filename' => isset($response['success']) ? 'success_'.$uniq . '.csv':'',
            'failure_filename' => isset($response['failure']) ? 'failure_'.$uniq . '.csv':'',
            'created_date_time' => date('Y-m-d h:i:s')
        ])->save();

        return $response;
    }

    /**
     * Write data to CSV file.
     *
     * @param type $status
     * @return boolean
     */
    public function writeCsv($status, $fileName)
    {
        $filePath = 'export/' . $fileName . '.csv';
        $this->directory->create('export');
        $fileDirectoryPath = $this->directoryList->getPath(PathDirectoryList::VAR_DIR);

        $header = array_combine($this->headers, $this->headers);

        array_unshift($this->products[$status], $header);

        $this->csv
            ->setEnclosure('"')
            ->setDelimiter(',')
            ->saveData($fileDirectoryPath .'/'. $filePath, $this->products[$status]);

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
        return $this->backendUrl->getUrl("productimport/publish/download", ['filename' => $status]);
    }
    
    public function getEvalFormula($attributeSetCode)
    {
        $fileName = BP . "/var/import/product_name_rule.csv";
        $data = $this->csv->getData($fileName);
        foreach ($data as $rawData) {
            if ($rawData[0] == $attributeSetCode) {
                return $this->prepareFormula($rawData, $data[0]);
            }
        }
    }
    
    private function prepareFormula($rawData, $header)
    {
        return array_combine($header, $rawData);
    }
}
