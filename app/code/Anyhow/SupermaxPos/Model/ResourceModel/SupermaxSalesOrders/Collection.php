<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesOrders;

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
            'Anyhow\SupermaxPos\Model\SupermaxSalesOrders',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesOrders'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
    }

    protected function _initSelect()
    {
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        $period = 'day';
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
        $sql = "SELECT * FROM $tableName Where type ='sales' ";
        $reportData = $connection->query($sql)->fetchAll();
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                $period = $report['period'];
                $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);
                $posOrderStatus = $report['status'];
                $posPaymentMethod = $report['payment_method'];
            }
        }
        $salesItemtableName = $resource->getTableName('sales_order_item'); 
        parent::_initSelect();
        $this->getSelect()
        ->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_orders')],
            'main_table.entity_id = spo.order_id',
            ['name'=> 'pos_order_id', 'order_id', 'device_type']
        )
        ->columns(['products' => new \Zend_Db_Expr(
            "SUM((SELECT SUM($salesItemtableName.qty_ordered) FROM $salesItemtableName WHERE $salesItemtableName.order_id = main_table.entity_id GROUP BY $salesItemtableName.order_id))"
        )])
        ->joinLeft(
            ['ppd' => $this->getTable('ah_supermax_pos_payment_detail')],
            'spo.pos_order_id = ppd.pos_order_id',
            ['payment_code']
        )
        ->columns('SUM(main_table.tax_amount) as tax')
        // ->columns('SUM(main_table.grand_total) as total')
        ->columns('SUM(ppd.amount) as total')
        ->columns(['total_profit_loss' => new \Zend_Db_Expr(
            "SUM((SELECT (SUM($salesItemtableName.price - $salesItemtableName.cost)*$salesItemtableName.qty_ordered) FROM $salesItemtableName WHERE $salesItemtableName.order_id = main_table.entity_id GROUP BY $salesItemtableName.order_id))"
        )])
        ->columns('MIN(main_table.created_at) as date_start')
        ->columns('MAX(main_table.created_at) as date_end')
        ->columns('COUNT(DISTINCT main_table.entity_id) as orders')
        // ->where("spo.pos_user_id = $posUserId")
        // ->where("spo.pos_outlet_id = $posOutletId")
        // ->where("main_table.status = '$posOrderStatus'")
        ->where('main_table.entity_id = spo.order_id')
        ->where("DATE(main_table.created_at) >= '$from' And DATE(main_table.created_at) <= '$to' ");

        if(isset($posUserId) && $posUserId !=0){
            $this->getSelect()->where("spo.pos_user_id = $posUserId");
        }
        // if(isset($posOutletId) && $posOutletId !=0){
        //     $this->getSelect()->where("spo.pos_outlet_id = $posOutletId");
        // }
        if(isset($posOrderStatus) && $posOrderStatus !="0"){
            $this->getSelect()->where("main_table.status = '$posOrderStatus'");
        }
        
            if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
                $this->getSelect()->where("ppd.payment_code = '$posPaymentMethod'");
                // $this->getSelect()->where("JSON_UNQUOTE(JSON_EXTRACT(spo.payment_data, '$[0].payment_code')) = '$posPaymentMethod'");
            }

        if($period == 'day'){
            $this->getSelect()->group('Date(main_table.created_at)');
        } elseif($period == 'week'){
            $this->getSelect()->group('Week(main_table.created_at)');
        } elseif($period == 'month'){
            $this->getSelect()->group('Month(main_table.created_at)');
        } elseif($period == 'year'){
            $this->getSelect()->group('Year(main_table.created_at)');
        }
        
        if($assignedOutletIds){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId !=''){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $posOutletId);
        }
        
        // echo $this->getSelect()->__toString();
        return $this;
    }
}