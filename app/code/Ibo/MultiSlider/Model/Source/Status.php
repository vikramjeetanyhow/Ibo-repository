<?php

namespace Ibo\MultiSlider\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public function toOptionArray()
    {
        // @codingStandardsIgnoreStart
        $statuses = [
            [
                'value' => self::STATUS_DISABLED, 'label' => '<span class="grid-severity-critical">'
                . htmlspecialchars(__("Disabled"))
                . '</span>'
            ],
            [
                'value' => self::STATUS_ENABLED, 'label' => '<span class="grid-severity-notice">'
                . htmlspecialchars(__("Enabled"))
                . '</span>'
            ]
        ];
        // @codingStandardsIgnoreEnd
        return $statuses;
    }
}
