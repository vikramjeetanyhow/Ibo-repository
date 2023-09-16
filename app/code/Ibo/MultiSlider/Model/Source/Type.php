<?php

namespace Ibo\MultiSlider\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    const TYPE_MASTER_BANNER = 'master_banner';
    const TYPE_ADLAYOUT_BANNER = 'adlayout_banner';
    const TYPE_MASTER_BANNER_CAROUSEL = 'master_banner_carousel';
    const TYPE_KIOSK_LARGE_SLIDER = 'kiosk_large_slider';
    const TYPE_KIOSK_MEDIUM_SLIDER = 'kiosk_medium_slider';
    const TYPE_KIOSK_SMALL_SLIDER = 'kiosk_small_slider';
    const TYPE_QUAD_CARDS = 'quad_cards';

    public function toOptionArray()
    {
        // @codingStandardsIgnoreStart
        $types = [
            [
                'value' => self::TYPE_MASTER_BANNER, 'label' => __("master_banner")
            ],
            [
                'value' => self::TYPE_MASTER_BANNER_CAROUSEL, 'label' => __("master_banner_carousel")
            ],            
            [
                'value' => self::TYPE_KIOSK_LARGE_SLIDER, 'label' => __("kiosk_large_slider")
            ],
            [
                'value' => self::TYPE_KIOSK_MEDIUM_SLIDER, 'label' => __("kiosk_medium_slider")
            ],
            [
                'value' => self::TYPE_KIOSK_SMALL_SLIDER, 'label' => __("kiosk_small_slider")
            ],
            [
                'value' => self::TYPE_QUAD_CARDS, 'label' => __("quad_cards")
            ],
            [
                'value' => self::TYPE_ADLAYOUT_BANNER, 'label' => __("adlayout_banner")
            ]
        ];
        // @codingStandardsIgnoreEnd
        return $types;
    }
}
