<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     mohit.pandit@embitel.com
 */

namespace Ibo\HomePage\Model\ResourceModel\HomeCategories;

use Ibo\HomePage\Model\HomeCategories as HomeCategoriesModel;
use Ibo\HomePage\Model\ResourceModel\HomeCategories as HomeCategoriesResourceModel;
 
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            HomeCategoriesModel::class,
            HomeCategoriesResourceModel::class
        );
    }
}      