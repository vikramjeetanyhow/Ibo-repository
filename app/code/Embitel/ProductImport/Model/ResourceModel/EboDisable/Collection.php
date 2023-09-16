<?php

namespace Embitel\ProductImport\Model\ResourceModel\EboDisable;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(\Embitel\ProductImport\Model\EboDisable::class, \Embitel\ProductImport\Model\ResourceModel\EboDisable::class);
    }
}
