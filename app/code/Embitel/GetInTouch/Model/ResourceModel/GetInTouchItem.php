<?php

namespace Embitel\GetInTouch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class GetInTouchItem extends AbstractDb
{
    /**
     * Initialize resource
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('ebo_contactus_master', 'id');
    }
}
