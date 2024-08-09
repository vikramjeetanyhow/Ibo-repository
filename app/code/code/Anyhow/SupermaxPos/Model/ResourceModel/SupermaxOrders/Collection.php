<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOrders;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    protected $_idFieldName = 'pos_order_id';
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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
    //    $assignedOutletIds = 1;
        
        // $assignedOutletId = array(1,2);
        $this->addFilterToMap('status', 'so.status');
        $this->addFilterToMap('increment_id', 'so.increment_id');
        $this->addFilterToMap('created_at', 'so.created_at');
        $this->addFilterToMap('grand_total', 'so.grand_total');
        $this->addFilterToMap('base_grand_total', 'so.base_grand_total');
        parent::_initSelect();
        $this->getSelect()
        ->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'main_table.order_id = so.entity_id'
        )
        ->joinLeft(
            ['sog' => $this->getTable('sales_order_grid')],
            'main_table.order_id = sog.entity_id',
            ['customer_name']
        )
        // ->joinLeft(
        //     ['sop' => $this->getTable('sales_order_payment')],
        //     'main_table.order_id = sop.parent_id'
        // )
        ->joinLeft(
            ['spot' => $this->getTable('ah_supermax_pos_outlet')],
            'main_table.pos_outlet_id = spot.pos_outlet_id',
            ['store'=>'outlet_name']
        );
        $this->addFilterToMap('pos_outlet_id', 'main_table.pos_outlet_id');
        // $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $assignedOutletId);
        if($assignedOutletIds){
            $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $assignedOutletIds);
         }

        // ->where("sop.method = 'pospayment'");
        // ->joinLeft(
        //     ['so' => $this->getTable('sales_order')],
        //     'main_table.order_id = so.entity_id'
        // )
        // ->columns(['stock'=> new \Zend_Db_Expr(
        //     "(SELECT SUM($salesInventoryTable.quantity) FROM $salesInventoryTable WHERE $salesInventoryTable.sku = main_table.sku GROUP BY $salesInventoryTable.sku)"
        // )])
        // ->columns('SUM(main_table.qty_ordered) AS quantity')
        // ->columns('SUM(main_table.row_total_incl_tax) AS total')
        // ->columns('MIN(so.created_at) as date_start')
        // ->columns('MAX(so.created_at) as date_end')
        
        // ->where('main_table.order_id = spo.order_id')
        // ->where("DATE(so.created_at) >= '$from' And DATE(so.created_at) <= '$to' ")
        // ->group('main_table.sku');

        // echo $this->getSelect()->__toString();
        return $this;
    }
}