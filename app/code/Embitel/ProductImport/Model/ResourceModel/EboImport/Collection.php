<?php

namespace Embitel\ProductImport\Model\ResourceModel\EboImport;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Embitel\ProductImport\Model\EboImport::class, \Embitel\ProductImport\Model\ResourceModel\EboImport::class);
    }
}
