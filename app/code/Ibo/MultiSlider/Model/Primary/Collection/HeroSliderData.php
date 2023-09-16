<?php

namespace Ibo\MultiSlider\Model\Primary\Collection;

use Ibo\MultiSlider\Model\Primary\Model\HeroSlider as HeroSlider;
use Ibo\MultiSlider\Model\Primary\ResourceModel\HeroResourceModel as HeroResourceModel;

class HeroSliderData extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $idFieldName = 'id';
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init(HeroSlider::class, HeroResourceModel::class);
    }
}
