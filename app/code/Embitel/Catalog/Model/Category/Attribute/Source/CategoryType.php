<?php

namespace Embitel\Catalog\Model\Category\Attribute\Source;

class CategoryType extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['label' => __('Please Select'), 'value' => ''],
                ['label' => __('MERCHANDISING'), 'value' => 'MERCHANDISING'],
                ['label' => __('NAVIGATION'), 'value' => 'NAVIGATION'],
                ['label' => __('BRAND'), 'value' => 'BRAND'],
                ['label' => __('PROMOTION'), 'value' => 'PROMOTION']
            ];
        }
        return $this->_options;
    }
}
