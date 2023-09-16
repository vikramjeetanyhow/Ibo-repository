<?php
namespace Ibo\DynamicBlockScript\Model;

use Ibo\DynamicBlockScript\Model\ResourceModel\BlockResourceModel as BlockResourceModel;
use Magento\Framework\Model\AbstractModel;

class BlockScript extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init(BlockResourceModel::class);
    }
}
