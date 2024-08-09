<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerDeleteAfter implements ObserverInterface
{    
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->helper = $helper;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $posStatus = (bool)$this->helper->getPosStatus();
        if($posStatus){
            
            $customer = $observer->getCustomer(); 
            $customerId = $customer->getId(); 

            if(!empty($customerId)){
                $code = 'customer';
                $this->helper->connectionUpdateEvent($customerId, $code, $flag = 'customerDelete');
            }

        }
    }   
}