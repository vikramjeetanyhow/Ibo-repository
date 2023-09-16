<?php

namespace Embitel\ProductImport\Model\ResourceModel;
  
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
  
class EboPublish extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('ebo_publish_history', 'history_id');
    }
}
