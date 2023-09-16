<?php

namespace Embitel\RequestCall\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RequestItem extends AbstractDb
{
    /**
     * Initialize resource
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('ebo_request_call_master', 'id');
    }
}
