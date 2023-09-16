<?php

namespace Embitel\ProductImport\Model\ResourceModel;
  
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
  
class EboImport extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ebo_import_history', 'history_id');
    }
}
