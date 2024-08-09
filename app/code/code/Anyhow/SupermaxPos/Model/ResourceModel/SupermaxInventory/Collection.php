<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxInventory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    protected $_idFieldName = 'item_id';
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxInventory',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxInventory'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
    }

    protected function _initSelect()
    {
        $to = date('Y-m-d');
        $from = date('Y-m-01');
        $period = 'day';
        $filter = 'top';
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report'); 
        $sql = "SELECT * FROM $tableName Where type ='inventory' ";
        $reportData = $connection->query($sql);
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                $period = $report['period'];
                $filter = $report['filter'];
                $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);

            }
        }

        $installer = $objectManager->get('Magento\Framework\Setup\SchemaSetupInterface');
        $salesStockTable = $resource->getTableName('cataloginventory_stock_item'); 
        $salesInventoryTable = $resource->getTableName('inventory_source_item'); 
        parent::_initSelect();
        $this->getSelect()
        ->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_orders')],
            'main_table.order_id = spo.order_id'
        )->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'main_table.order_id = so.entity_id'
        )
        ->columns('SUM(main_table.qty_ordered) AS quantity')
        ->columns('SUM(main_table.row_total_incl_tax) AS total')
        ->columns('MIN(so.created_at) as date_start')
        ->columns('MAX(so.created_at) as date_end')
        ->columns('SUM((main_table.price - main_table.cost) * main_table.qty_ordered) as total_profit_loss')
        ->where('main_table.order_id = spo.order_id')
        ->where("DATE(so.created_at) >= '$from' And DATE(so.created_at) <= '$to' ")
        ->group('main_table.sku');

        if($installer->tableExists('inventory_source_item')){
            $this->getSelect()->columns(['stock'=> new \Zend_Db_Expr(
                "(SELECT SUM($salesInventoryTable.quantity) FROM $salesInventoryTable WHERE $salesInventoryTable.sku = main_table.sku GROUP BY $salesInventoryTable.sku)"
            )]);
        } else {
            $this->getSelect()->columns(['stock'=> new \Zend_Db_Expr(
                "(SELECT SUM($salesStockTable.qty) FROM $salesStockTable WHERE $salesStockTable.product_id = main_table.product_id GROUP BY $salesStockTable.product_id)"
            )]);
        }

        // echo $this->getSelect()->__toString();
        if(isset($posUserId) && $posUserId !=0){
            $this->getSelect()->where("spo.pos_user_id = $posUserId");
        }
        // if(isset($posOutletId) && $posOutletId !=0){
        //     $this->getSelect()->where("spo.pos_outlet_id = $posOutletId");
        // }

        if($period == 'day'){
            $this->getSelect()->group('Date(so.created_at)');
        }elseif($period == 'week'){
            $this->getSelect()->group('Week(so.created_at)');
        }elseif($period == 'month'){
            $this->getSelect()->group('Month(so.created_at)');
        }elseif($period == 'year'){
            $this->getSelect()->group('Year(so.created_at)');
        }
        if($filter == 'top'){
            $this->getSelect()->order('SUM(main_table.qty_ordered) DESC');
        }elseif($filter == 'worst'){
            $this->getSelect()->order('SUM(main_table.qty_ordered) ASC');
        }
        
        if($assignedOutletIds){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId !=''){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $posOutletId);
        }
        return $this;
    }    
}