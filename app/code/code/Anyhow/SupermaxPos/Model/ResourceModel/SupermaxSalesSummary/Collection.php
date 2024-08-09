<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesSummary;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    // protected $_idFieldName = 'entity_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxSalesSummary',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesSummary'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
    }

    protected function _initSelect() {
        parent::_initSelect();

        $from = date('Y-m-01');
        $to = date('Y-m-d');
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
        $sqlSales = "SELECT * FROM $tableName Where type ='sales' ";
            $salesReportData = $connection->query($sqlSales);
            if(!empty($salesReportData)){
                foreach($salesReportData as $salesReport){
                    $posUserId = $salesReport['pos_user_id'];
                    $posOutletId = json_decode($salesReport['pos_outlet_id']);
                    $posOrderStatus = $salesReport['status'];
                    $posPaymentMethod = $salesReport['payment_method'];
                }
            }
        $reportData = $connection->query("SELECT * FROM $tableName Where type ='detail' ")->fetch();
        if (!empty($reportData)) {
            $to = date("Y-m-d", strtotime($reportData['to']));
            $from = date("Y-m-d", strtotime($reportData['from']));
        }
            $this->addFilterToMap('order_id', 'spo.order_id');
            $this->addFilterToMap('status', 'main_table.status');
            $this->getSelect()->joinLeft(
                ['spo' => $this->getTable('ah_supermax_pos_orders')],
                'main_table.entity_id = spo.order_id',
            )
            ->joinLeft(
                ['ppd' => $this->getTable('ah_supermax_pos_payment_detail')],
                'spo.pos_order_id = ppd.pos_order_id',
                [ 'method'=>'payment_code','methods'=>'payment_code', 'amount_detail'=>'amount', 'pos_id_order'=>'pos_order_id']
                )
                ->columns(['amount_details' => 'SUM(ppd.amount)'])

                // ->group('spo.pos_order_id')
            // ->where('main_table.entity_id = spo.order_id')
            ->where("DATE(main_table.created_at) >= '$from' And DATE(main_table.created_at) <= '$to' ");
            
            if(isset($posUserId) && $posUserId !=0) {
                $this->getSelect()->where("spo.pos_user_id = $posUserId");
            }
            // if(isset($posOutletId) && $posOutletId !=0) {
            //     $this->getSelect()->where("spo.pos_outlet_id = $posOutletId");
            // }
            if(isset($posOrderStatus) && $posOrderStatus !="0") {
                $this->getSelect()->where("main_table.status = '$posOrderStatus'");
            }
            if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
                    $this->getSelect()->where("ppd.payment_code = '$posPaymentMethod'");
            } 
            if($assignedOutletIds){
                $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
             } else if(isset($posOutletId) && $posOutletId != ''){
                $this->getSelect()->where("spo.pos_outlet_id IN (?)", $posOutletId);
            }
            $this->getSelect()->group('ppd.payment_code');
            // ->having('main_table.entity_id > ?', 1);
            // echo '<pre>';
            // print_r($this->getSelect()->__toString());
            // echo '</pre>';
            // die();
            
       return $this;
    }
}