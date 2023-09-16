<?php
namespace Embitel\Quote\Observer;

class PushOrderOms implements \Magento\Framework\Event\ObserverInterface
{ 
	protected $helper;
    public function __construct( 
    	\Embitel\Quote\Helper\Data $helper
    ){
        $this->helper = $helper;

    }

	public function execute(\Magento\Framework\Event\Observer $observer)
	{ 
        $order = $observer->getOrder();
        if($order->getPayment()->getMethod() == 'cashondelivery' || $order->getPayment()->getMethod() == 'free') {
            $orderID = $order->getId();
            $this->helper->SuccessOrderExecute($orderID);
        }
	}   

}