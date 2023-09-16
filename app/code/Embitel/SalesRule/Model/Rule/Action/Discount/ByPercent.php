<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\SalesRule\Model\Rule\Action\Discount;

class ByPercent extends \Magento\SalesRule\Model\Rule\Action\Discount\ByPercent
{

    public function __construct(
        \Magento\SalesRule\Model\Validator $validator,
        \Magento\SalesRule\Model\Rule\Action\Discount\DataFactory $discountDataFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory
    ){
        $this->quoteRepository = $quoteRepository;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        parent::__construct($validator,$discountDataFactory,$priceCurrency);
    }

    /**
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param float $qty
     * @return Data
     */
    public function calculate($rule, $item, $qty)
    {
        $rulePercent = min(100, $rule->getDiscountAmount());
        $discountData = $this->_calculate($rule, $item, $qty, $rulePercent);

        return $discountData;
    }

    /**
     * @param float $qty
     * @param \Magento\SalesRule\Model\Rule $rule
     * @return float
     */
    public function fixQuantity($qty, $rule)
    {
        $step = $rule->getDiscountStep();
        if ($step) {
            $qty = floor($qty / $step) * $step;
        }

        return $qty;
    }

    /**
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param float $qty
     * @param float $rulePercent
     * @return Data
     */
    protected function _calculate($rule, $item, $qty, $rulePercent)
    {
        /** @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData */

        $discountData = $this->discountFactory->create();
        $quoteData = $this->quoteCollectionFactory->create()
            ->addFieldToSelect('trigger_recollect')
            ->addFieldToFilter('entity_id', ['eq'=>$item->getQuoteId()]);

        $quoteTriggerRecollect = isset($quoteData->getData()[0]['trigger_recollect']) ? $quoteData->getData()[0]['trigger_recollect'] : 0;

        $itemPrice = $this->validator->getItemPrice($item);
        $baseItemPrice = $this->validator->getItemBasePrice($item);
        $itemOriginalPrice = $this->validator->getItemOriginalPrice($item);
        $baseItemOriginalPrice = $this->validator->getItemBaseOriginalPrice($item);

        $_rulePct = $rulePercent / 100;
        
        if($quoteTriggerRecollect != 1) {
            $quoteItemData = $this->quoteRepository->get($item->getQuoteId());
            $cartTotal = 0;
            foreach ($quoteItemData->getAllItems() as $items) {
                $cartTotal += $items->getPriceInclTax() * $items->getQty();
            }

            $this->addLog("Rule Id :" . $rule->getId());

            $actualDiscountAmount = ($qty * $itemPrice - $item->getDiscountAmount()) * $_rulePct;
            $maxDiscountAmount = (($qty * $itemPrice - $item->getDiscountAmount()) / $cartTotal) * $rule->getMaxPercentAmount();

            $this->addLog("Discount Amount :" . $actualDiscountAmount);
            $this->addLog("Max Allowed discount Amount :" . $maxDiscountAmount);

            if (($rule->getMaxPercentAmount() == 0) || $actualDiscountAmount <= $maxDiscountAmount) {
                $discountData->setAmount($actualDiscountAmount);
            } else {
                $discountData->setAmount($maxDiscountAmount);
            }

            $actalBaseDiscountAmount = ($qty * $baseItemPrice - $item->getBaseDiscountAmount()) * $_rulePct;
            $maxBaseDiscount = (($qty * $baseItemPrice - $item->getBaseDiscountAmount()) / $cartTotal) * $rule->getMaxPercentAmount();

            $this->addLog("Base Discount Amount :" . $actalBaseDiscountAmount);
            $this->addLog("Base Max Allowed discount Amount :" . $maxBaseDiscount);

            if (($rule->getMaxPercentAmount() == 0) || $actalBaseDiscountAmount <= $maxBaseDiscount) {
                $discountData->setBaseAmount($actalBaseDiscountAmount);
            } else {
                $discountData->setBaseAmount($maxBaseDiscount);
            }

        }
        $discountData->setOriginalAmount(($qty * $itemOriginalPrice - $item->getDiscountAmount()) * $_rulePct);
        $discountData->setBaseOriginalAmount(
            ($qty * $baseItemOriginalPrice - $item->getBaseDiscountAmount()) * $_rulePct
        );

        if (!$rule->getDiscountQty() || $rule->getDiscountQty() > $qty) {
            $discountPercent = min(100, $item->getDiscountPercent() + $rulePercent);
            $item->setDiscountPercent($discountPercent);
        }
        $this->addLog(print_r($discountData,true));
        return $discountData;
    }

    public function addLog($logData, $filename = "coupon_percent.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {

        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }
}
