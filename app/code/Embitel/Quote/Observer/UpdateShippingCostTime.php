<?php

namespace Embitel\Quote\Observer;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class UpdateShippingCostTime implements \Magento\Framework\Event\ObserverInterface
{ 
	
    public function __construct( 
    	TimezoneInterface $timezoneInterface
    ){
        $this->timezoneInterface = $timezoneInterface;

    }

	public function execute(\Magento\Framework\Event\Observer $observer)
	{ 
        $cart = $observer->getData('quote');
        $this->addLog('Cart updated after set shipping charge');
        $updatedTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $cart->setShippingUpdateAt($updatedTime);
        $cart->save();

	} 
    public function addLog($logData){
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/place-order.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    }    

}