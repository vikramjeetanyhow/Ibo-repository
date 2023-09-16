<?php 

namespace Ibo\Hdfc\Model;

use \Magento\Framework\Option\ArrayInterface;

class PaymentAction implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => '',
                'label' => __('Select Option'),
            ],
            [
                'value' => 'authorize',
                'label' => __('Authorize Only'),
            ],
            [
                'value' => 'capture',
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}