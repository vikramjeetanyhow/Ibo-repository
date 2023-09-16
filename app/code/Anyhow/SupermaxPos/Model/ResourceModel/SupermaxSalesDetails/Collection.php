<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesDetails;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    protected $_idFieldName = 'entity_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxSalesDetails',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesDetails'
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
        // $newReportDate = date('2022-03-22');
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
        $salesItemtableName = $resource->getTableName('sales_order_item'); 
        $sql = "SELECT * FROM $tableName Where type ='detail' ";
        $reportData = $connection->query($sql);
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to =  date("Y-m-d", strtotime($report['to']));
                $from = date("Y-m-d", strtotime($report['from']));
            }
        }
        // print_r($to);die;
        $sqlSales = "SELECT * FROM $tableName Where type ='sales' ";
        $salesReportData = $connection->query($sqlSales);
        if(!empty($salesReportData)){
            foreach($salesReportData as $salesReport){
                // print_r($salesReport);die;
                $posUserId = $salesReport['pos_user_id'];
                $posOutletId = json_decode($salesReport['pos_outlet_id']);
                $posOrderStatus = $salesReport['status'];
                $posPaymentMethod = $salesReport['payment_method'];
            }
        }

        // $this->addFilterToMap('order_id', 'ppd.order_id');
        $this->addFilterToMap('status', 'main_table.status');


        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_orders')],
            'main_table.entity_id = spo.order_id',
            ['cashier'=> 'pos_user_id', 'pos_outlet_id', 'payment_code', 'payment_method', 'payment_data', 'ah_pos_order_id' => 'order_id']
        )->joinLeft(
            ['sog' => $this->getTable('sales_order_grid')],
            'main_table.entity_id = sog.entity_id',
            ['customer_name']
        )
        // ->joinLeft(
        //     ['sop' => $this->getTable('sales_order_payment')],
        //     'main_table.entity_id = sop.parent_id',
        //     ['method']
        // )
        ->joinLeft(
            ['spot' => $this->getTable('ah_supermax_pos_outlet')],
            'spo.pos_outlet_id = spot.pos_outlet_id',
            ['store'=>'outlet_name']
        )->columns(['total_profit_loss' => new \Zend_Db_Expr(
            "(SELECT (SUM($salesItemtableName.price - $salesItemtableName.cost) * $salesItemtableName.qty_ordered) FROM $salesItemtableName WHERE $salesItemtableName.order_id = main_table.entity_id GROUP BY $salesItemtableName.order_id)"
        )])
        ->joinLeft(
            ['ppd' => $this->getTable('ah_supermax_pos_payment_detail')],
            'spo.pos_order_id = ppd.pos_order_id',
            ['pos_payment_id','method'=>'payment_code', 'amount_detail'=>'amount', 'pos_id_order'=>'pos_order_id']
        )
        ->group('spo.order_id')
        // ->where("spo.pos_user_id = $posUserId")
        // ->where("spo.pos_outlet_id = $posOutletId")
        // ->where("main_table.status = '$posOrderStatus'")
        ->where('main_table.entity_id = spo.order_id')
        ->where("DATE(main_table.created_at) >= '$from' And DATE(main_table.created_at) <= '$to' ");
        if(isset($posUserId) && $posUserId !=0){
            $this->getSelect()->where("spo.pos_user_id = $posUserId");
        }        
        if(isset($posOrderStatus) && $posOrderStatus !="0"){
            $this->getSelect()->where("main_table.status = '$posOrderStatus'");
        }
        if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
            $this->getSelect()->where("ppd.payment_code = '$posPaymentMethod'");
            // $this->getSelect()->where("JSON_UNQUOTE(JSON_EXTRACT(spo.payment_data, '$[0].payment_code')) = '$posPaymentMethod'");
            
        }
        if($assignedOutletIds){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId !=0){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $posOutletId);
        }
        return $this;
    }
}