<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_terminal_id';
	
	protected $_eventPrefix = 'terminal_collection';

    protected $_eventObject = 'terminal_collection';
   
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxPosTerminal',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal'
        );
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
        // $assignedOutletIds = [1];
        parent::_initSelect();        
        if($assignedOutletIds != 0){
            $this->getSelect()->where("main_table.pos_outlet_id IN (?)", $assignedOutletIds);
         } 
        
    }
}