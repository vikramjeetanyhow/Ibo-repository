<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\PayLaterBharatpe\Model;

/**
 * Bank Transfer payment method model
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 *
 * @api
 * @since 100.0.2
 */
class Bharatpe extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_PAYLATER_BHARATPE_CODE = 'BHARATPE';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_PAYLATER_BHARATPE_CODE;

    /**
     * Instructions block path
     *
     * @var string
     */
    protected $_infoBlockType = \Magento\Payment\Block\Info\Instructions::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        return trim($this->getConfigData('instructions'));
    }
}
