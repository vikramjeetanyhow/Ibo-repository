<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Report;

use Magento\Framework\App\Filesystem\DirectoryList;


class Customerreferralexport extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory; 

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxCustomerReferral\Collection $registerData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->_registerData = $registerData;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
    }

    public function execute()
    {   
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/customer-report-import-' .$name. '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();

        $columns = ['Customer Name','Email','Customer Group','Store Name', 'Cashier Name', 'Status','Referral','Referral Phone Number','Date Start'];

        foreach ($columns as $column) 
        {
            $header[] = $column;
        }

        $stream->writeCsv($header);
        $register = $this->_registerData;
        $register_collection = $register->getData();
        
        foreach($register_collection as $item){
            if($item['is_active'] == 1){
                $status = 'Enabled';
            }else{
                $status = 'Disabled';
            }
            $itemData = [];
            $itemData[] = $item['firstname'].' '.$item['lastname'];
            // $itemData[] = $item['date_end'];
            $itemData[] = $item['email'];            
            $itemData[] = $item['customer_group_code'];
            $itemData[] = $item['outlet_name'];
            $itemData[] = $item['user_name'];
            $itemData[] = $status;
            $itemData[] = $item['customer_referral_title'];
            $itemData[] = $item['customer_referral_Phone'];
            $itemData[] = $item['date_start'];

            $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'customer-report-import-'.$name.'.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}