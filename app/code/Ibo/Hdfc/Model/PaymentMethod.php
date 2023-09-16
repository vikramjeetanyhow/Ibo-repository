<?php

namespace Ibo\Hdfc\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Pay In Store payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'prepaid';

        /**
     * @var bool
     */
    protected $_canAuthorize = true;

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $payment->setAdditionalInformation('payment_type', 'authorize');
    }

}