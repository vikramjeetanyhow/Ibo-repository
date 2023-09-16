<?php

namespace Embitel\GetInTouch\Model\ResourceModel\GetInTouchItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Initialize resource collection
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            \Embitel\GetInTouch\Model\GetInTouchItem::class,
            \Embitel\GetInTouch\Model\ResourceModel\GetInTouchItem::class
        );
    }
}
