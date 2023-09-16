<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\HomePage\Model;

use Magento\Framework\Model\AbstractModel;
use Ibo\HomePage\Model\ResourceModel\HomeBestdeal as HomeBestdealResourceModel;

class HomeBestdeal extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init(HomeBestdealResourceModel::class);
    }
} 