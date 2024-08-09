<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxTerminal;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_user_login_id';
	
	protected $_eventPrefix = 'pos_terminal_collection';

    protected $_eventObject = 'pos_terminal_collection';
   
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxTerminal',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxTerminal'
        );
    }
    protected function _initSelect()
    {
        parent::_initSelect();  
        $this->getSelect()->joinLeft(
            ['spu' => $this->getTable('ah_supermax_pos_user')],
            'main_table.pos_user_id = spu.pos_user_id',
            ['pos_outlet_id', 'store_view_id','fname'=>'firstname', 'lname'=>'lastname']
        )->joinLeft(
            ['spo' => $this->getTable('ah_supermax_pos_outlet')],
            'spu.pos_outlet_id = spo.pos_outlet_id',
            ['outlet_name']
        )->joinLeft(
            ['spt' => $this->getTable('ah_supermax_pos_terminals')],
            'main_table.pos_terminal_id = spt.pos_terminal_id',
            ['code']
        )->columns('CONCAT(spu.firstname, " ", spu.lastname) as firstname')
        ->columns('spu.username as username')
        ->columns('spt.code as outlet_name')
        ->where("main_table.status", ['eq' => 1]);
        

          return $this;      
    }
}