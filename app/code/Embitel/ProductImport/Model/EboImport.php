<?php

namespace Embitel\ProductImport\Model;

use Magento\Framework\Model\AbstractModel;

class EboImport extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Embitel\ProductImport\Model\ResourceModel\EboImport::class);
    }
}
