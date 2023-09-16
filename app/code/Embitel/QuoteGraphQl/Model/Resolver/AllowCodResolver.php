<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
/**
 * @inheritdoc
 */
class AllowCodResolver implements ResolverInterface
{
    private $helper;

    public function __construct(
        \Embitel\Quote\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var Quote $cart */
        $cart = $value['model'];
        $grandTotal = $cart->getGrandTotal();
        $allow_cod = false;
        if($this->helper->getCodStatus() == "1" && $grandTotal <= $this->helper->getCodMaxValue()){ 
            $allow_cod = true;
        }

        return $allow_cod;
    }
}