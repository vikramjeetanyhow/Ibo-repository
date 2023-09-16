<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\DynamicBlockScript\Model\ResourceModel\BlockScript;

use Ibo\DynamicBlockScript\Model\BlockScript as BlockScriptModel;
use Ibo\DynamicBlockScript\Model\ResourceModel\BlockResourceModel as BlockResourceModel;
 
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            BlockScriptModel::class,
            BlockResourceModel::class
        );
    }
}
