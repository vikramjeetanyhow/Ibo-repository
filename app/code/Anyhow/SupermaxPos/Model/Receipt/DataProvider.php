<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Receipt ;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $receiptCollectionFactory,
        DataPersistorInterface $dataPersistor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $receiptCollectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->meta= $this->prepareMeta($this->meta);
        $this->storeManager = $storeManager;

    }
    public function prepareMeta(array $meta){
        return $meta;
    }
    public function getData(){

        if(isset($this->loadedData)){
            return $this->loadedData;
        }
        
        $items = $this->collection->getItems();

        foreach($items as $receiptdata){
            if (isset($receiptdata['header_logo'])) {
                $image = [];
                $image[0]['name'] = $receiptdata['header_logo'];
                $image[0]['url'] = $this->storeManager->getStore()->getBaseUrl(
                    \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                ).$receiptdata['header_logo_path'];
                $receiptdata['header_logo'] = $image;
            }
            $this->loadedData[$receiptdata->getId()]= $receiptdata->getData();
        }
        $data = $this->dataPersistor->get('receipt');
        if(!empty($data)){
            $receiptdata = $this->collection->getNewEmptyItem();
            $receiptdata->setData($data);
            $this->loadedData[$receiptdata->getId()]= $receiptdata->getData();
            $this->dataPersistor->clear('receipt');
        }
        return $this->loadedData;

    }

    public function getMeta()
    {
        $meta = parent::getMeta();
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $http = $objectManager->get('Magento\Framework\App\Request\Http');

        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_receipt_store'); 
        $items = $this->collection->getItems();
        
        foreach($items as $receiptdata){
            $storeParam = $http->getParam('store');
            $receiptId = $http->getParam('id');

            if(!empty($storeParam)){
                $receiptAllStoreData = $connection->query("SELECT * FROM $tableName WHERE store_id = 0 AND receipt_id = $receiptId")->fetchAll();

                $sql = "SELECT * FROM $tableName WHERE store_id = $storeParam AND receipt_id = $receiptId";
                $receiptStoreData = $connection->query($sql)->fetchAll();

                if(!empty($receiptStoreData)){
                    if($receiptStoreData[0]['title'] != $receiptAllStoreData[0]['title']){
                        $meta['general']['children']['title']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['title']['arguments']['data']['config']['enabled'] = 1;
                    } else {
                        $meta['general']['children']['title']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['title']['arguments']['data']['config']['disabled'] = 1;
                    }
                    if($receiptStoreData[0]['header_details'] != $receiptAllStoreData[0]['header_details']){
                        $meta['general']['children']['header_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['header_details']['arguments']['data']['config']['enabled'] = 1;
                    } else {
                        $meta['general']['children']['header_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['header_details']['arguments']['data']['config']['disabled'] = 1;
                    }

                    if($receiptStoreData[0]['footer_details'] != $receiptAllStoreData[0]['footer_details']){
                        $meta['general']['children']['footer_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['footer_details']['arguments']['data']['config']['enabled'] = 1;
                    } else {
                        $meta['general']['children']['footer_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['footer_details']['arguments']['data']['config']['disabled'] = 1;
                    }

                } else {
                    $meta['general']['children']['title']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                    $meta['general']['children']['title']['arguments']['data']['config']['disabled'] = 1;
                    $meta['general']['children']['header_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                    $meta['general']['children']['header_details']['arguments']['data']['config']['disabled'] = 1;
                    $meta['general']['children']['footer_details']['arguments']['data']['config']['service']['template'] = 'ui/form/element/helper/service';
                        $meta['general']['children']['footer_details']['arguments']['data']['config']['disabled'] = 1;
                }

            } else {
                $meta['general']['children']['title']['arguments']['data']['config']['service']['template'] = '';
                $meta['general']['children']['title']['arguments']['data']['config']['enabled'] = 1;
                $meta['general']['children']['header_details']['arguments']['data']['config']['service']['template'] = '';
                $meta['general']['children']['header_details']['arguments']['data']['config']['enabled'] = 1;
                $meta['general']['children']['footer_details']['arguments']['data']['config']['service']['template'] = '';
                $meta['general']['children']['footer_details']['arguments']['data']['config']['enabled'] = 1;
            }
        }
        return $meta;
    }
}