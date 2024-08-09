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
 * All product unique code options for system.xml file
 */
namespace Anyhow\SupermaxPos\Model\Config\Source;
class AllSizeUnits implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'mm', 'label' => __('mm')],
            ['value' => 'cm', 'label' => __('cm')],
            ['value' => 'in', 'label' => __('in')],
            ['value' => 'px', 'label' => __('px')]
        ];
    }
}