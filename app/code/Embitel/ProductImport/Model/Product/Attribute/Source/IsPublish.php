<?php

namespace Embitel\ProductImport\Model\Product\Attribute\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IsPublish implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => '',
                'label' => __('No'),
            ],
            [
                'value' => 1,
                'label' => __('Published'),
            ],
            [
                'value' => 2,
                'label' => __('Unpublished')
            ]
        ];
    }
}
