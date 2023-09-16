<?php

namespace Ibo\MultiSlider\Model\Secondary;

use Ibo\MultiSlider\Model\Secondary\ResourceModel\ResourceHeroSliderData as ResourceHeroSliderData;
use Magento\Framework\Model\AbstractModel;

class SecondaryHeroSliderData extends AbstractModel
{
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init(ResourceHeroSliderData::class);
    }
}
