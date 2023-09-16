<?php

namespace Embitel\ProductExport\Model\ResourceModel\EboExport;

use Embitel\ProductExport\Model\EboExport;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(EboExport::class, \Embitel\ProductExport\Model\ResourceModel\EboExport::class);
    }
}