<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_outlet_id';
	
	protected $_eventPrefix = 'outlet_collection';

    protected $_eventObject = 'outlet_collection';
    
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxPosOutlet',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet'
        );
    }
    
    /**
     *
     * @return $this
     */
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
        // $assignedOutletIds = [1];
        parent::_initSelect(); 
        $this->getSelect()->joinLeft(
            ['oa' => $this->getTable('ah_supermax_pos_outlet_address')], 
            'main_table.pos_outlet_id = oa.parent_outlet_id',
            ['*']
        );
        if($assignedOutletIds){
            $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $assignedOutletIds);
        } 
        // ->joinLeft(
        //     ['cto' => $this->getTable('ah_supermax_pos_category_to_outlet')], 
        //     'main_table.pos_outlet_id = cto.parent_outlet_id'
        // );
    }
}