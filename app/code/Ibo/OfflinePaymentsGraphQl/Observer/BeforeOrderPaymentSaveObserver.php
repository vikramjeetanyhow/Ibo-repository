<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ibo\OfflinePaymentsGraphQl\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Ibo\OfflinePaymentsGraphQl\Model\OfflinePayment;

/**
 * Sets payment additional information.
 */
class BeforeOrderPaymentSaveObserver implements ObserverInterface
{
    /**
     * Sets current instructions for bank transfer account
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        $instructionMethods = [
            OfflinePayment::PAYMENT_METHOD_OFFLINEPAYMENT_CODE
        ];
        if (in_array($payment->getMethod(), $instructionMethods)
            && empty($payment->getAdditionalInformation('instructions'))) {
            $payment->setAdditionalInformation(
                'instructions',
                $payment->getMethodInstance()->getConfigData(
                    'instructions',
                    $payment->getOrder()->getStoreId()
                )
            );
        }
    }
}
