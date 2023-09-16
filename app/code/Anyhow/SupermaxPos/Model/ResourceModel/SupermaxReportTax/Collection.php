<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReportTax;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    protected $_idFieldName = 'tax_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxReportTax',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReportTax'
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
        // $assignedOutletIds = 1;     
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report'); 
        $sql = "SELECT * FROM $tableName Where type ='tax' ";
        $reportData = $connection->query($sql);
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                $period = $report['period'];
                $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);
            }
        }

        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_orders')],
            'main_table.order_id = spo.order_id'
        )->joinLeft(
            ['so' => $this->getTable('sales_order')],
            'main_table.order_id = so.entity_id'
        )
        ->columns('COUNT(so.entity_id) AS orders')
        ->columns('MIN(so.created_at) AS date_start')
        ->columns('MAX(so.created_at) AS date_end')
        ->columns('SUM(so.base_tax_amount) AS total')
        ->where('so.entity_id = spo.order_id')
        ->where("DATE(so.created_at) >= '$from' And DATE(so.created_at) <= '$to'")
        ->group('main_table.title');

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
        if($assignedOutletIds){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId !=''){
            $this->getSelect()->where("spo.pos_outlet_id IN (?)", $posOutletId);
        }
        return $this;
    }
}