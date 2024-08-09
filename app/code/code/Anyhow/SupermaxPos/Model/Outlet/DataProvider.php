<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Outlet ;

use Magento\Framework\App\Request\DataPersistorInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\CollectionFactory;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;
    
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $outletCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ){
        $this->collection = $outletCollectionFactory->create();
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
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $outletCategoryTableName = $resource->getTableName('ah_supermax_pos_category_to_outlet');
        foreach($items as $outletdata){
            $this->loadedData[$outletdata->getId()]['general'] = $outletdata->getData();
            $outletId = $outletdata->getId();
            $outletCategoryData = $connection->query("SELECT category_id FROM $outletCategoryTableName WHERE parent_outlet_id = $outletId ");
            if(!empty($outletCategoryData)){
                $categoryId = array();
                foreach($outletCategoryData as $category){
                    $categoryId[] = $category['category_id'];
                }
                $this->loadedData[$outletdata->getId()]['assign_category']['category_id'] = $categoryId;
            }
        }
       
        $data = $this->dataPersistor->get('outlet');
        if(!empty($data)){
            $outletdata = $this->collection->getNewEmptyItem();
            $outletdata->setData($data);
            $this->loadedData[$outletdata->getId()] = $outletdata->getData();
            $this->dataPersistor->clear('outlet');
        }
        return $this->loadedData;

    }
}