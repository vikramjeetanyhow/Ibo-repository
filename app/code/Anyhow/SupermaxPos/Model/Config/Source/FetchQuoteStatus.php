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

class FetchQuoteStatus implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(\Magento\Framework\View\Element\Template\Context $context
    ) {          
        $this->context = $context;
    }

    public function toOptionArray() {
        return [
            ['value' => 'disable', 'label' => __('Disable')],
            ['value' => 'by_quote_object', 'label' => __('By Quote Object')],
            ['value' => 'by_simple_sql', 'label' => __('By Simple SQL')]
        ];
    }
}