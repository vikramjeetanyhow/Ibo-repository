<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOverrides;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    // protected $_idFieldName = 'pos_user_override_detail_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxOverrides',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOverrides'
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

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        // $assignedOutletIds = 1;
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report');
        $salesOrderItemTable = $resource->getTableName('sales_order_item');
        $sql = "SELECT * FROM $tableName Where type ='overrides' ";
        $reportData = $connection->query($sql)->fetchAll();
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                $posUserId = $report['pos_user_id'];
                $posApproverId = $report['pos_approver_id'];
                $posOverridePermission = json_decode($report['override_permission']);
                $posOutletId = json_decode($report['pos_outlet_id']);
                $posOrderStatus = $report['status'];
            }
        }
        parent::_initSelect();
        $this->getSelect()
        ->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'main_table.order_id = so.entity_id',
            ['status', 'entity_id', 'increment_id']
        )
        ->joinLeft(
            ['sos' => $this->getTable('sales_order_status')],
            'so.status = sos.status',
            ['order_status' => 'label']
        )
        ->joinLeft(
            ['od' => $this->getTable('ah_supermax_pos_user_override_details')],
            'main_table.pos_user_override_id = od.parent_pos_user_override_id'
        )
        ->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            ['firstname', 'lastname']
        )
        ->joinLeft(
            ['ct' => $this->getTable('ah_supermax_pos_user')],
            'main_table.pos_user_id = ct.pos_user_id',
            ['firstname', 'lastname']
        )
        ->joinLeft(
            ['ca' => $this->getTable('ah_supermax_pos_user')],
            'od.approver_id = ca.pos_user_id',
            ['firstname', 'lastname']
        )->
        joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_orders')],
            'main_table.order_id = spo.order_id',
            ['pos_id_order'=>'spo.pos_order_id','payment_method','payment_data']
        )
        ->joinLeft(
            ['ot' => $this->getTable('ah_supermax_pos_outlet')],
            'ot.pos_outlet_id = spo.pos_outlet_id',
            ['outlet_name']
        )
        ->joinLeft(
            ['cg' => $this->getTable('customer_group')],
            'main_table.customer_group_id = cg.customer_group_id',
            ['customer_group_code']
        )
        ->columns('od.overrided_price as product_overrided_price')
        ->columns('od.product_id as Priduct_ids')
        ->columns('CONCAT(ca.firstname, " ", ca.lastname) as approver_name')
        ->columns('CONCAT(ct.firstname, " ", ct.lastname) as user_name')
        ->columns('ce.firstname as customer_name')
        ->where("DATE(main_table.date_added) >= '$from' And DATE(main_table.date_added) <= '$to' ");
        // ->group('main_table.order_id');
        if(isset($posUserId) && $posUserId !=0){
            $this->getSelect()->where("main_table.pos_user_id = $posUserId");
        }
        if(isset($posApproverId) && $posApproverId !=0){
            $this->getSelect()->where("od.approver_id = $posApproverId");
        }
        if(isset($posOverridePermission) && !empty($posOverridePermission)){
            $this->getSelect()->where("od.permission_type IN (?)", $posOverridePermission);
        }
        // if(isset($posOutletId) && $posOutletId !=0){
        //     $this->getSelect()->where("main_table.pos_outlet_id = $posOutletId");
        // }
        if(isset($posOrderStatus) && $posOrderStatus !="0"){
            $this->getSelect()->where("so.status = '$posOrderStatus'");
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