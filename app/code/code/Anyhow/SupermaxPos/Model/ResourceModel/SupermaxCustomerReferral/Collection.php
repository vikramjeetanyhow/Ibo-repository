<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxCustomerReferral;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_customer_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxReportCustomer',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReportCustomer'
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
       
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report'); 
        $sql = "SELECT * FROM $tableName Where type ='customer_referral' ";
        $reportData = $connection->query($sql);
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                // $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);
            }
        }

        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
        )->joinLeft(
            ['cg' => $this->getTable('customer_group')],
            'ce.group_id = cg.customer_group_id'
        )->joinLeft(
            ['spu' => $this->getTable('ah_supermax_pos_user')],
            'main_table.pos_user_id = spu.pos_user_id',
            ['pos_outlet_id', 'store_view_id','fname'=>'firstname', 'lname'=>'lastname']
        )->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_outlet')],
            'main_table.pos_outlet_id = spo.pos_outlet_id',
            ['outlet_name']
            )
        ->columns('CONCAT(spu.firstname, " ", spu.lastname) as user_name')
        // ->columns('CONCAT(ce.firstname, " ", ce.lastname) as customer_name')
        ->columns('ce.firstname as customer_name')

        ->columns('ce.created_at as date_start')
        // ->columns('MAX(ce.created_at) as date_end')
        // ->where('so.entity_id = spo.order_id')
        ->where("DATE(ce.created_at) >= '$from' And DATE(ce.created_at) <= '$to' ");

        // if(isset($posUserId) && $posUserId !=0){
        //     $this->getSelect()->where("spo.pos_user_id = $posUserId")
        //     ->where("main_table.pos_user_id = $posUserId");
        // }
        // if(isset($posOutletId) && $posOutletId !=0){
        //     $this->getSelect()->where("spo.pos_outlet_id = $posOutletId")
        //     ->where("main_table.pos_outlet_id = '$posOutletId'");
        // }

        // if(isset($posOrderStatus) && $posOrderStatus !="0"){
        //     $this->getSelect()->where("so.status = '$posOrderStatus'");
        // }
        if($assignedOutletIds){
            $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId != ''){
            $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $posOutletId);
        }
        
        // print_r($this->getSelect()->__toString());die;

        return $this;
    }

    

}