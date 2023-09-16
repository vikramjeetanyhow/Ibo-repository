<?php

namespace Embitel\OodoPriceImport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OodoPrice extends AbstractDb
{
   
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('oodo_price_import', 'price_id');
    }
}
