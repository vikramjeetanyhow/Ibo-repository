<?php

namespace Embitel\ProductFilter\Model\Category\Attribute\Source;

class Sortby extends \Magento\Catalog\Model\Category\Attribute\Source\Sortby
{
    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            //$this->_options = [['label' => __('Position'), 'value' => 'position']];
            foreach ($this->_getCatalogConfig()->getAttributesUsedForSortBy() as $attribute) {
                if ($attribute['attribute_code'] == 'sort_order') {
                    $this->_options[] = [
                        'label' => __('Popularity'),
                        'value' => $attribute['attribute_code']
                    ];
                } elseif ($attribute['attribute_code'] == 'price') {
                    $this->_options[] = [
                        'label' => $attribute['frontend_label'] . ' ' . __('Low-High'),
                        'value' => $attribute['attribute_code']
                    ];
                    $this->_options[] = [
                        'label' => $attribute['frontend_label'] . ' ' . __('High-Low'),
                        'value' => $attribute['attribute_code']
                    ];
                } /*else {
                    $this->_options[] = [
                        'label' => __($attribute['frontend_label']),
                        'value' => $attribute['attribute_code']
                    ];
                }*/
            }
        }
        return $this->_options;
    }
}
