<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */

namespace Ibo\ProductUpdate\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Subclass extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('ibo_exclude_subclass', 'id');
    }
}