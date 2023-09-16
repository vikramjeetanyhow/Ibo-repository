<?php
namespace Embitel\OodoPriceImport\Model\ResourceModel\OodoPrice;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Oodo Price collection
 */
class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Embitel\OodoPriceImport\Model\OodoPrice::class, \Embitel\OodoPriceImport\Model\ResourceModel\OodoPrice::class);
    }
}
