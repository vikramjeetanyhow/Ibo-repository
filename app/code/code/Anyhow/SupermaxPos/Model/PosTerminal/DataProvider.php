<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\PosTerminal ;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $priceReductionCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $priceReductionCollectionFactory->create();
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

        foreach($items as $posTerminalData){
            $posTerminalData->setPassword('');
            $this->loadedData[$posTerminalData->getId()]= $posTerminalData->getData();
        }
        $data = $this->dataPersistor->get('posterminal');
        if(!empty($data)){
            $posTerminalData = $this->collection->getNewEmptyItem();
            $posTerminalData->setData($data);
            $this->loadedData[$posTerminalData->getId()]= $posTerminalData->getData();
            $this->dataPersistor->clear('posterminal');
        }
        return $this->loadedData;
    }
}