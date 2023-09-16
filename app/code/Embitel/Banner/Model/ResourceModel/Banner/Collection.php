<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Model\ResourceModel\Banner;
 
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'banner_id';
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'Embitel\Banner\Model\Banner',
            'Embitel\Banner\Model\ResourceModel\Banner'
        );
    }
}