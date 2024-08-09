<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_receipt_id';
 
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxReceipt',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
    }
    protected function _initSelect()
    {
        $assignedOutletId = array();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        // $assignedOutletIds = [2];
        $http = $objectManager->get('Magento\Framework\App\Request\Http');
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_receipt_store'); 
        $storeId = $http->getParam('store');
        $receiptId = $http->getParam('id');
        $this->addFilterToMap('pos_receipt_id', 'main_table.pos_receipt_id');

        if(empty($storeId)){
            $storeId = 0;
        } else {
            $sql = "SELECT * FROM $tableName Where store_id = $storeId AND receipt_id = $receiptId";
            $receiptStoreData = $connection->query($sql)->fetchAll();
            if(empty($receiptStoreData)){
                $storeId = 0;
            }
        }
        parent::_initSelect();
        $this->getSelect()
        ->joinLeft(
            ['sprs' => $this->getTable('ah_supermax_pos_receipt_store')],
            'main_table.pos_receipt_id = sprs.receipt_id'
        );
        
        if($assignedOutletIds){
            $this->getSelect()->joinLeft(
                ['spo' => $this->getTable('ah_supermax_pos_outlet')],
                'sprs.receipt_id = spo.pos_receipt_id'
            );
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
            $this->getSelect()->limit(count($assignedOutletIds));

        } else {
            $this->getSelect()->where("sprs.store_id = $storeId");
        }
        // echo $this->getSelect()->__toString();
        return $this;
    }
}
