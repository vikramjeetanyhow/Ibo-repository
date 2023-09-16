<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */

namespace Ibo\ProductUpdate\Model\ResourceModel\Subclass;

use Ibo\ProductUpdate\Model\Subclass as SubclassModel;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass as SubclassResourceModel;
 
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            SubclassModel::class,
            SubclassResourceModel::class
        );
    }
}      