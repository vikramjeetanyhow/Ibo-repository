<?php

namespace Ibo\CategoryWidget\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    const TYPE_CATEGORY_SCROLL = 'category_scroll';
    const TYPE_CATEGORY_LIST_CIRCLE = 'category_list_circle';
    const TYPE_CATEGORY_LIST_SQUARE = 'category_list_square';    

    public function toOptionArray()
    {
        // @codingStandardsIgnoreStart
        $types = [
            [
                'value' => self::TYPE_CATEGORY_SCROLL, 'label' => __("Category Scroll")
            ],            
            [
                'value' => self::TYPE_CATEGORY_LIST_CIRCLE, 'label' => __("Category List Circle")
            ],
            [
                'value' => self::TYPE_CATEGORY_LIST_SQUARE, 'label' => __("Category List Square")
            ]
        ];
        // @codingStandardsIgnoreEnd
        return $types;
    }
}
