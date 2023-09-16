<?php

namespace Embitel\GetInTouch\Model;

class GetInTouchItem extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Embitel\GetInTouch\Model\ResourceModel\GetInTouchItem::class);
    }
}
