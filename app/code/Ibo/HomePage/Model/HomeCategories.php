<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     mohit.pandit@embitel.com
 */

namespace Ibo\HomePage\Model;

use Magento\Framework\Model\AbstractModel;
use Ibo\HomePage\Model\ResourceModel\HomeCategories as HomeCategoriesResourceModel;

class HomeCategories extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(HomeCategoriesResourceModel::class);
    }
} 