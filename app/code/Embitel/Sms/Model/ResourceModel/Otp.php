<?php

namespace Embitel\Sms\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Otp extends AbstractDb
{

    protected function _construct()
    {
        $this->_init('embitel_mobile_otp', 'mobile_id');
    }
}
