<?php
namespace Ibo\Hdfc\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;


class Paymenttype implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'PAY-ON-DELIVERY', 'label' => __('PAY-ON-DELIVERY')],
            ['value' => 'CREDIT-CARD', 'label' => __('CREDIT-CARD')],
            ['value' => 'DEBIT-CARD', 'label' => __('DEBIT-CARD')],
            ['value' => 'CASH-CARD', 'label' => __('CASH-CARD')],
            ['value' => 'NET-BANKING', 'label' => __('NET-BANKING')],
            ['value' => 'UPI', 'label' => __('UPI')]
        ];
    }
}