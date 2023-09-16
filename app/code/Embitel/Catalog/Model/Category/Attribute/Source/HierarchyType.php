<?php

namespace Embitel\Catalog\Model\Category\Attribute\Source;

class HierarchyType extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['label' => __('Please Select'), 'value' => ''],
                ['label' => __('DEPARTMENT'), 'value' => 'DEPARTMENT'],
                ['label' => __('CLASS'), 'value' => 'CLASS'],
                ['label' => __('SUBCLASS'), 'value' => 'SUBCLASS'],
                ['label' => __('CATEGORY'), 'value' => 'CATEGORY']
            ];
        }
        return $this->_options;
    }
}

