<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Outlet\Attribute\Source;

class AssignProductsOptions implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return  [
            [
                'value' => 'all', 
                'label' => __('All Products')
            ],
            [
                'value' => 'product', 
                'label' => __('Product Based')
            ],
            [
                'value' => 'category', 
                'label' => __("Category Based")
            ], 
        ];
        
    }
}