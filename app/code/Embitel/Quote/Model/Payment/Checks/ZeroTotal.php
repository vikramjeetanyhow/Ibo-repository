<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Quote\Model\Payment\Checks;

use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Payment\Model\Checks\ZeroTotal as MagentoZeroTotal;

/**
 * Checks that order total is meaningful
 *
 * @api
 * @since 100.0.2
 */
class ZeroTotal extends MagentoZeroTotal
{
    /**
     * Check whether payment method is applicable to quote
     * Purposed to allow use in controllers some logic that was implemented in blocks only before
     *
     * @param MethodInterface $paymentMethod
     * @param \Magento\Quote\Model\Quote $quote
     * @return bool
     */
    public function isApplicable(MethodInterface $paymentMethod, Quote $quote)
    {
        $allowPaymentMethods = ['free','cashondelivery'];
        return !($quote->getBaseGrandTotal() < 0.0001 && !in_array($paymentMethod->getCode(), $allowPaymentMethods));
    }
}
