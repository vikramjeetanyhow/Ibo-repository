<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\MrpUpdate\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Ibo\MrpUpdate\Model\MrpUpdateInOodo;

/**
 * Customer log observer.
 */
class UpdateMrpToOodo implements ObserverInterface
{
    /**
     * @var MrpUpdateInMage
     */
    private $_mrpUpdateInOodo;

    /**
     * @param MrpUpdateInOodo $_mrpUpdateInOodo
     */
    public function __construct(
        MrpUpdateInOodo $_mrpUpdateInOodo
    ) {        
        $this->_mrpUpdateInOodo = $_mrpUpdateInOodo;
    } 
   
    /**
     * Handler for 'customer_login' event.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) { 
        $mrpData = $observer->getData('mrp_data');
        $sku = $mrpData['sku'];
        $mrp = $mrpData['mrp'];        
        $this->_mrpUpdateInOodo->update($mrpData);
    }
}
