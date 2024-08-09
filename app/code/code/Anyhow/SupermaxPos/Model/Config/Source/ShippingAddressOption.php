<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

/**
 * Shipping address radio options for system.xml file
 */
namespace Anyhow\SupermaxPos\Model\Config\Source;

class ShippingAddressOption implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return  [
                    [
                        'value' => '1', 
                        'label' => __('Set Shipping Address')
                    ], 
                    [
                        'value' => "2", 
                        'label' => __("No I'll use the store address")
                    ],
                ];
    }
}