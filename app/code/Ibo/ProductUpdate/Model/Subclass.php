<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */

namespace Ibo\ProductUpdate\Model;

use Magento\Framework\Model\AbstractModel;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass as SubclassResourceModel;

class Subclass extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(SubclassResourceModel::class);
    }
} 