<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\PincodeMaster;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPincodeMaster\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $pincodeCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $pincodeCollectionFactory->create();
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

        foreach($items as $pincodeMasterData){
            $this->loadedData[$pincodeMasterData->getId()]= $pincodeMasterData->getData();
        }
        $data = $this->dataPersistor->get('pincodemaster');
        if(!empty($data)){
            $pincodeMasterData = $this->collection->getNewEmptyItem();
            $pincodeMasterData->setData($data);
            $this->loadedData[$pincodeMasterData->getId()]= $pincodeMasterData->getData();
            $this->dataPersistor->clear('pincodemaster');
        }
        return $this->loadedData;
    }
}