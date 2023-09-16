<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Cashier ;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $cashierCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $cashierCollectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->meta= $this->prepareMeta($this->meta);

    }
    public function prepareMeta(array $meta){
        return $meta;
    }
    public function getData(){
        if(isset($this->loadedData)){
            return $this->loadedData;
        }
        $items = $this->collection->getItems();

        foreach($items as $cashierdata){
            $cashierdata->setPassword('');
            $this->loadedData[$cashierdata->getId()]= $cashierdata->getData();
        }
        $data = $this->dataPersistor->get('cashier');
        if(!empty($data)){
            $cashierdata = $this->collection->getNewEmptyItem();
            $cashierdata->setData($data);
            $this->loadedData[$cashierdata->getId()]= $cashierdata->getData();
            $this->dataPersistor->clear('cashier');
        }
        return $this->loadedData;

    }
}