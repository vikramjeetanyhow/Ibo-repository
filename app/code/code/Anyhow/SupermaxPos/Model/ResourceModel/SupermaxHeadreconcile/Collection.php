<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxHeadreconcile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    protected $_idFieldName = 'pos_register_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxHeadreconcile',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxHeadreconcile'
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
        $title = "Opening Float";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }  
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $supermaxRegisterTransDetailTable = $resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $tableName = $resource->getTableName('ah_supermax_pos_report');
        $sqlReconcile = "SELECT * FROM $tableName Where type ='reconcile' ";
        $reconcileReportData = $connection->query($sqlReconcile);
        if (!empty($reconcileReportData)) {
            foreach ($reconcileReportData as $reconcileReport) {
                $to = date("Y-m-d", strtotime($reconcileReport['to']));
                $from = date("Y-m-d", strtotime($reconcileReport['from']));
                $posUserId = $reconcileReport['pos_user_id'];
                $posOutletId = json_decode($reconcileReport['pos_outlet_id']);
                $posPaymentMethod = $reconcileReport['payment_method'];
                if($reconcileReport['status'] == 'Pending')
                {
                    $posOrderStatus = 0;
                } elseif($reconcileReport['status'] == 'Done') {
                    $posOrderStatus = 1;
                } else {
                    $posOrderStatus = $reconcileReport['status'];
                }
            }
        }
        // $title = "Opening Float";
        //     $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected = 0;
        //     $getCustomTotalExpected = $connection->query("SELECT * FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = 'main_table.pos_user_id' AND title != '" . $title . "' ")->fetchAll();

        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['spu' => $this->getTable('ah_supermax_pos_user')],
            'main_table.pos_user_id = spu.pos_user_id',
            ['pos_outlet_id', 'store_view_id']
        )
            ->joinLeft(
                ['spo' => $this->getTable('ah_supermax_pos_outlet')],
                'spu.pos_outlet_id = spo.pos_outlet_id',
                ['outlet_name']
            )
            ->joinLeft(
                ['prtd' => $this->getTable('ah_supermax_pos_register_transaction_detail')],
                'prtd.pos_register_id = main_table.pos_register_id',                
                ['*']
            )
             ->where("title != '$title' And DATE(main_table.date_open) >= '$from' And DATE(main_table.date_open) <= '$to' ")
             ->group('prtd.pos_register_id');

        $this->addFilterToMap('pos_outlet_id', 'spu.pos_outlet_id');
        $this->addFilterToMap('pos_register_id', 'prtd.pos_register_id');
                
        // if ($posPaymentMethod == 'CASH') {            
            // $this->getSelect()->columns(['paymentexpected' => new \Zend_Db_Expr(
            //     "SUM((SELECT  FROM $supermaxRegisterTransDetailTable WHERE $supermaxRegisterTransDetailTable.pos_register_id = 'main_table.pos_user_id' AND title != '" . $title . "' ))")]);
        // }
        if (isset($posUserId) && $posUserId != 0) {
            $this->getSelect()->where("spu.pos_user_id = $posUserId");
        }
        // if (isset($posOutletId) && $posOutletId != 0) {
        //     $this->getSelect()->where("spu.pos_outlet_id = $posOutletId");
        // }
        if ($posOrderStatus != 'a') {
            $this->getSelect()->where("main_table.reconciliation_status = '$posOrderStatus'");
        }
        // if ($from >= $newReportDate && $to >= $newReportDate){        
            if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
                $this->getSelect()->where("prtd.code = '$posPaymentMethod'");
                // $this->getSelect()->where("JSON_UNQUOTE(JSON_EXTRACT(spo.payment_data, '$[0].payment_code')) = '$posPaymentMethod'");
            }
        // }
        if($assignedOutletIds){
            $this->getSelect()->where("spu.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId != ''){
            $this->getSelect()->where("spu.pos_outlet_id IN (?)", $posOutletId);
        }

        //  print_r($this->getSelect()->__toString());die;

        return $this;
    }
}
