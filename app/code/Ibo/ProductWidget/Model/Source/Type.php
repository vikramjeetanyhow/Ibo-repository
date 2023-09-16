<?php

namespace Ibo\ProductWidget\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    const TYPE_PRODUCT_STACK_VERTICAL = 'product_stack_vertical';
    const TYPE_PRODUCT_STACK_HORIZONTAL = 'product_stack_horizontal';
        

    public function toOptionArray()
    {
        // @codingStandardsIgnoreStart
        $types = [
            [
                'value' => self::TYPE_PRODUCT_STACK_VERTICAL, 'label' => __("Product Widget Vertical")
            ],            
            [
                'value' => self::TYPE_PRODUCT_STACK_HORIZONTAL, 'label' => __("Product Widget Horizontal")
            ]
        ];
        // @codingStandardsIgnoreEnd
        return $types;
    }
}
