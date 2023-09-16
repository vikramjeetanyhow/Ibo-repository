<?php

namespace Embitel\Facade\Model\ResourceModel\FacadeHistory;

use Embitel\Facade\Model\FacadeHistory;
use Embitel\Facade\Model\ResourceModel\FacadeHistory as FacadeHistoryResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(FacadeHistory::class, FacadeHistoryResourceModel::class);
    }
}
