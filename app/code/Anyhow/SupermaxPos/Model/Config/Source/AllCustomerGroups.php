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

class AllCustomerGroups implements \Magento\Framework\Option\ArrayInterface
{
    protected $customerGroup;
    
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroup
    ) {       
        $this->customerGroup = $customerGroup;      
        $this->context = $context;
    }

    public function toOptionArray() {
        $options = $this->customerGroup->toOptionArray();        
        return $options;
    }
}