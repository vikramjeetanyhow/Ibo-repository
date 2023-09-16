<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SupermaxUserRole extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ah_supermax_pos_user_role', 'pos_user_role_id');
    }
}