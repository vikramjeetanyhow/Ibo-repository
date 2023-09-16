<?php

namespace Ibo\MultiSlider\Model\Secondary\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ResourceHeroSliderData extends AbstractDb
{
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init('category_hero_slider_secondary', 'id');
    }
}
