<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\HomePage\Model\ResourceModel\HomeBestdeal;

use Ibo\HomePage\Model\HomeBestdeal as HomeBestdealModel;
use Ibo\HomePage\Model\ResourceModel\HomeBestdeal as HomeBestdealResourceModel;
 
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            HomeBestdealModel::class,
            HomeBestdealResourceModel::class
        );
    }
}      