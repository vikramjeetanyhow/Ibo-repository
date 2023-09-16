<?php
namespace Ibo\MultiSlider\Model\Primary\Model;

use Ibo\MultiSlider\Model\Primary\ResourceModel\HeroResourceModel as HeroResourceModel;
use Magento\Framework\Model\AbstractModel;

class HeroSlider extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    protected function _construct()
    {
        $this->_init(HeroResourceModel::class);
    }
}
