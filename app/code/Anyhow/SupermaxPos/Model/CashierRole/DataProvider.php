<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\CashierRole ;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $cashierRoleCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $cashierRoleCollectionFactory->create();
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

        foreach($items as $cashierRoledata){
            $cashierRoledata->setPassword('');
            $this->loadedData[$cashierRoledata->getId()]= $cashierRoledata->getData();
        }
        $data = $this->dataPersistor->get('cashierrole');
        if(!empty($data)){
            $cashierRoledata = $this->collection->getNewEmptyItem();
            $cashierRoledata->setData($data);
            $this->loadedData[$cashierRoledata->getId()]= $cashierRoledata->getData();
            $this->dataPersistor->clear('cashierrole');
        }
        return $this->loadedData;
    }
}