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

class CustomerAddressSaveAfter implements ObserverInterface
{    
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxConnection\Collection $posConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxConnectionUpdate\Collection $connectionUpdate,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper; 
        $this->posConnection = $posConnection;
        $this->connectionUpdate = $connectionUpdate;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $posStatus = (bool)$this->helper->getPosStatus();
        if($posStatus){
            $connection = $this->resource->getConnection();
            $connectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
            
            $customerAddress = $observer->getCustomerAddress(); 
            $customerId = $customerAddress->getParentId(); 
            $code = 'customer';
            
            if(!empty($customerId)){
                $this->helper->connectionUpdateEvent($customerId, $code);
            }
        }
    }   
}