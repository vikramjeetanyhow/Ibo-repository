<?php

namespace Embitel\RequestCall\Model\ResourceModel\RequestItem;

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
            \Embitel\RequestCall\Model\RequestItem::class,
            \Embitel\RequestCall\Model\ResourceModel\RequestItem::class
        );
    }
}
