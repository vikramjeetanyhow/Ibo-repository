<?php

namespace Embitel\ProductImport\Model\ResourceModel\EboPublish;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Embitel\ProductImport\Model\EboPublish::class, \Embitel\ProductImport\Model\ResourceModel\EboPublish::class);
    }
}
