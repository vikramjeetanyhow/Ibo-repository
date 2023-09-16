<?php

namespace Embitel\Facade\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class FacadeHistory extends AbstractDb
{

    protected function _construct()
    {
        $this->_init('ebo_facade_log', 'entity_id');
    }
}
