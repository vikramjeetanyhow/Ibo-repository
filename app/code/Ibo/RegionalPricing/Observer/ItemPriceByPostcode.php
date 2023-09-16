<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\RegionalPricing\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Ibo\RegionalPricing\Model\PriceByPostcode;

/**
 * Customer log observer.
 */
class ItemPriceByPostcode implements ObserverInterface
{
    /**
     * @var MrpUpdateInMage
     */
    private $_priceByPostcode;

    /**
     * @param PriceByPostcode $_priceByPostcode
     */
    public function __construct(
        PriceByPostcode $_priceByPostcode
    ) {        
        $this->_priceByPostcode = $_priceByPostcode;
    } 
   
    /**
     * Handler for 'customer_login' event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) {
        $cart = $observer->getData('quote');
        $this->_priceByPostcode->getItemPrice($cart); 
    }
}
