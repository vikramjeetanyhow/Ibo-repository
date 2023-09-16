<?php

/**
 * TaxMaster Grid Collection.
 *
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Model\ResourceModel\TaxMaster;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
   // protected $_idFieldName = 'value_id';
    /**
     * Define resource model.
     */
    protected function _construct()
    {
        $this->_init(
            \Embitel\TaxMaster\Model\TaxMaster::class,
            \Embitel\TaxMaster\Model\ResourceModel\TaxMaster::class
        );
    }
}
