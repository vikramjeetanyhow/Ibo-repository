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
 * all states for system.xml file
 */
namespace Anyhow\SupermaxPos\Model\Config\Source;

/**
 * Options provider for regions list
 *
 * @api
 * @since 100.0.2
 */
use Magento\Framework\Option\ArrayInterface;
use Magento\Framework\Data\OptionSourceInterface;
class OverrideType implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Product Price')],
            ['value' => '4', 'label' => __('Product Price Discount')],
            ['value' => '5', 'label' => __('Cart Discount')],
            ['value' => '2', 'label' => __('Delivery Charge Price')],
            ['value' => '3', 'label' => __('Hold Cart')],
         ];
    }}
