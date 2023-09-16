<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\SalesRule\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class for adding Channel conditions section
 */
class AddPromotionalReferralToSalesRuleCombineObserver implements ObserverInterface
{

    /**
     * Add Channel Info condition to the salesrule management
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $additional = $observer->getEvent()->getAdditional();
        $conditions = (array) $additional->getConditions();
        $conditions = array_merge_recursive(
            $conditions,
            [
                [
                    'label' => __('Promotional Referral'),
                    'value' => \Embitel\SalesRule\Model\Rule\Condition\PromotionalReferral::class,
                ],
            ]
        );
        $additional->setConditions($conditions);
    }
}
