<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;

use Magento\Framework\App\Filesystem\DirectoryList;


class Exportdetaildata extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory; 

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegisterTransactionDetail\Collection $registerDetailData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency
    ) {
        parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->_registerDetailData = $registerDetailData;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
    }

    public function execute()
    {   
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/register-detail-import-' .$name. '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();

        $columns = ['ID','Date Added','Transaction Method','Title','Description','Amount'];
        foreach ($columns as $column) 
        {
            $header[] = $column;
        }

        $stream->writeCsv($header); 
        $registerId = $this->getRequest()->getParam('pos_register_id');
        $register = $this->_registerDetailData->addFieldToFilter('pos_register_id', $registerId);
        $register_collection = $register->getData();

        // for store base currency symbol
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }

        foreach($register_collection as $item){
            $itemData = [];
            $itemData[] = $item['pos_register_transaction_detail_id'];
            $itemData[] = $item['date_added'];
            $itemData[] = $item['code'];
            $itemData[] = $item['title'];
            $itemData[] = $item['description'];
            $itemData[] = $storeCurrencySymbol.$item['amount'];
            $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'register-detail-import-'.$name.'.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}