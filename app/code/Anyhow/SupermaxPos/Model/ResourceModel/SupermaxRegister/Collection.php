<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
// use Anyhow\SupermaxPos\Helper\Data;
class Collection extends AbstractCollection
{

    protected $_idFieldName = 'pos_register_id';
    protected $_eventPrefix = 'supermax_register_grid_collection';
    protected $_eventObject = 'supermax_register_grid_collection';
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
        // \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxRegister',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister'
        );
        parent::__construct(
            $entityFactory, $logger, $fetchStrategy, $eventManager, $connection,
            $resource
        );
        $this->storeManager = $storeManager;
        // $this->helper = $helper;
    }

    protected function _initSelect()
    {
        $assignedOutletId = array();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        // $assignedOutletIds =0;
        $this->addFilterToMap('status', 'main_table.status');
        $this->addFilterToMap('pos_user_id', 'main_table.pos_user_id');
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
        ->columns('main_table.reconciliation_status as reconcile_status');
        $this->addFilterToMap('pos_outlet_id', 'spu.pos_outlet_id');
        if($assignedOutletIds){
            $this->getSelect()->where("spu.pos_outlet_id IN (?)", $assignedOutletIds);
         } 
         
        // print_r($this->getSelect()->__toString());die();
        return $this;
    }

}
