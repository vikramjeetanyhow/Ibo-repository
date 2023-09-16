<?php

namespace Embitel\RequestCall\Model;

class RequestItem extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Embitel\RequestCall\Model\ResourceModel\RequestItem::class);
    }
}
