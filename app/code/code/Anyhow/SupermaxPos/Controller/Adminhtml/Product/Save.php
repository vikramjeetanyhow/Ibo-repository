<?php
/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Image\AdapterFactory;
use Magento\Store\Model\ScopeInterface; 


class Save extends \Magento\Backend\App\Action
{
    protected $fileSystem;
    protected $uploaderFactory;
    protected $request;
    protected $adapterFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $uploaderFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        AdapterFactory $adapterFactory,
        \Anyhow\SupermaxPos\Model\SupermaxProduct $supermaxProduct,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper

    ) {
        parent::__construct($context);
        $this->fileSystem = $fileSystem;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->adapterFactory = $adapterFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->supermaxProduct = $supermaxProduct;
        $this->resource = $resourceConnection;
        $this->helper = $helper;
    }

    public function execute()
    { 
        $fileData = $this->getRequest()->getFiles('importdata');
        if ( (isset($fileData)) && ($fileData != '') ) {
            try {    
                $f_object = fopen($fileData['tmp_name'], "r");
                $column = fgetcsv($f_object);
                if($f_object) {
                    $connection= $this->resource->getConnection();
                    $productTable = $this->resource->getTableName('catalog_product_entity');
                    
                    if( ($column[0] == 'entity_id') && ($column[1] == 'barcode')) {  
                        $count = 0;
                        while (($columns = fgetcsv($f_object)) !== FALSE) {
                            if($columns[0] != 'entity_id')  {
                                $productId = $columns[0];
                                $count++;
                                $where = $connection->quoteInto('entity_id = ?', $productId);
                                $query = $connection->update($productTable,
                                        ['barcode'=> $columns[1], 'barcode_type'=> 2], $where );
                                
                                // To make entry in database for connection update for product.
                                $this->helper->connectionUpdateEvent($productId, 'product');
                            }
                        } 
                        $this->messageManager->addSuccess(__('A total of %1 record(s) have been Added.', $count));
                        $this->_redirect('supermax/product/index');
                    } else {
                        $this->messageManager->addError(__("invalid Formated File"));
                        $this->_redirect('supermax/product/import');
                    }
                } else {
                    $this->messageManager->addError(__("File hase been empty"));
                    $this->_redirect('supermax/product/import');
                }
                  
            } 
            catch (\Exception $e) 
            {   
                $this->messageManager->addError(__($e->getMessage()));
                $this->_redirect('supermax/product/import');
            }
        }
        else
        {
            $this->messageManager->addError(__("Please try again."));
            $this->_redirect('supermax/product/import');
        }
    }
}
 