<?php

namespace Ibo\MultiSlider\Model\Secondary\Collection;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ibo\MultiSlider\Model\Secondary\SecondaryHeroSliderData as SecondaryHeroSliderData;
use Ibo\MultiSlider\Model\Secondary\ResourceModel\ResourceHeroSliderData as ResourceHeroSliderData;

class CollectionHeroSliderData extends AbstractCollection
{
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init(SecondaryHeroSliderData::class, ResourceHeroSliderData::class);
    }
}
