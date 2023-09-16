<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_user_role_id';
	
	protected $_eventPrefix = 'cashier_role_collection';

    protected $_eventObject = 'cashier_role_collection';
   
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxUserRole',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole'
        );
    }
}