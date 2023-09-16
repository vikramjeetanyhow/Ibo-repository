<?php
namespace Embitel\Quote\Observer;

class SaveLandmarkData implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
     protected $scopeConfig;

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
     }

    public function getPromoFlag()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('tax/calculation/discount_tax', $storeScope);
    }

    public function getPriceFlag()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('tax/calculation/price_includes_tax', $storeScope);
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();

        if ($quote->getBillingAddress()) {
             $order->getBillingAddress()->setLandmark($quote->getBillingAddress()->getLandmark());
             $order->save();
        }
        if (!$quote->isVirtual()) {
            $order->getShippingAddress()->setLandmark($quote->getShippingAddress()->getLandmark());
            $order->save();
        }
        if ($quote->getPromise_id()) {
            $order->setPromiseId($quote->getPromise_id());
            $order->save();
        }
        if ($quote->getPromise_options()) {
            $order->setPromise_options($quote->getPromise_options());
            $order->save();
        }
        if ($quote->getDelivery_group()) {
            $order->setDelivery_group($quote->getDelivery_group());
            $order->save();
        }
        if ($quote->getPromise_created_at()) {
            $order->setPromise_created_at($quote->getPromise_created_at());
            $order->save();
        }
        if ($quote->getPromise_expires_at()) {
            $order->setPromise_expires_at($quote->getPromise_expires_at());
            $order->save();
        }
        if($quote->getChannelInfo()) {
            $order->setOrderChannelInfo($quote->getChannelInfo());
            $order->save();
        }
        if ($quote->getIsProfessionalReferralApplied()) {
            $order->setIsProfessionalReferralApplied($quote->getIsProfessionalReferralApplied());
            $order->save();
        }
        if ($quote->getProfessionalNumber()) {
            $order->setProfessionalNumber($quote->getProfessionalNumber());
            $order->save();
        }

        if ($quote->getAdditionalData()) {
            $order->setAdditionalData($quote->getAdditionalData());
            $order->save();
        }

        $order->setTaxInclInPromo($this->getPromoFlag());
        $order->setTaxInclInItem($this->getPriceFlag());
        $order->save();
        return $this;
    }
}
