<?php

/**
 * TaxMaster Grid Collection.
 *
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Model\ResourceModel\Grid;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';
    /**
     * Define resource model.
     */
    protected function _construct()
    {
        $this->_init(
            \Embitel\TaxMaster\Model\Grid::class,
            \Embitel\TaxMaster\Model\ResourceModel\Grid::class
        );
    }
}
