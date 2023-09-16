<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Config\Source;

class AllOrderStatus implements \Magento\Framework\Option\ArrayInterface
{
    protected $statusCollectionFactory;
    
    public function __construct(\Magento\Framework\View\Element\Template\Context $context,
            \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory
    ) {       
        $this->statusCollectionFactory = $statusCollectionFactory;      
        $this->context = $context;
    }

    public function toOptionArray() {
        $options = $this->statusCollectionFactory->create()->toOptionArray();        
        return $options;
    }
}