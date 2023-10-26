<?php

namespace Embitel\Catalog\Model\Category\Attribute\Source;

class CategoryFulfillmentClass extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['label' => __('Please Select'), 'value' => ''],
                ['label' => __('Cross-dock at the ordering store'), 'value' => 'CDOS'],
                ['label' => __('Cross-dock at the city warehouse'), 'value' => 'CDWH'],
                ['label' => __('Customer door-step delivery'), 'value' => 'CDSD'],
            ];
        }
        return $this->_options;
    }
}
