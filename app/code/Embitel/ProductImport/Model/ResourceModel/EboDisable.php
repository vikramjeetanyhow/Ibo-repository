<?php

namespace Embitel\ProductImport\Model\ResourceModel;
  
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
  
class EboDisable extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ebo_disable_history', 'history_id');
    }
}
