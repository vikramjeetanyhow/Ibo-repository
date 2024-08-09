<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxHeadreconcileSummary;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    // protected $_idFieldName = 'pos_register_id';
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
            'Anyhow\SupermaxPos\Model\SupermaxHeadreconcileSummary',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxHeadreconcileSummary'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
    }

    protected function _initSelect()
    {
        $title = "Opening Float";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        // $http = $objectManager->get('Magento\Framework\App\Request\Http');
        // $posRegisterId = $http->getParam('pos_register_id');
        parent::_initSelect();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $reportTable = $resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetch();

        if (!empty($reportData)) {
            $posRegisterId = $reportData['pos_register_id'];
            $posOutletId = json_decode($reportData['pos_outlet_id']);
        }
        $this->getSelect()
        ->joinLeft(
            ['prtd' => $this->getTable('ah_supermax_pos_register_transaction_detail')],
            'main_table.pos_register_id = prtd.pos_register_id',
            ['*']
        )->joinLeft(
            ['spu' => $this->getTable('ah_supermax_pos_user')],
            'main_table.pos_user_id = spu.pos_user_id',
            ['pos_outlet_id', 'store_view_id']
        )
        ->where("prtd.pos_register_id = '$posRegisterId' And title != '$title' ")
        ->group('prtd.code')
        ->columns('prtd.code as paymentCode');
        if($assignedOutletIds){
            $this->getSelect()->where("spu.pos_outlet_id IN (?)", $assignedOutletIds);
         } else if(isset($posOutletId) && $posOutletId != ''){
            $this->getSelect()->where("spu.pos_outlet_id IN (?)", $posOutletId);
        }
        // parent::_initSelect();
        // $this->getSelect()->limit(6);
        return $this;
    }
}
