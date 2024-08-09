<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml\Template\Edit;

class Form extends \Magento\Backend\Block\Widget\Form\Generic {

    protected $variables;

    public function __construct(
        \Anyhow\SupermaxPos\Model\Config\Source\Variables $variables
    ) {
        $this->_variables = $variables;
    }

    public function afterGetVariables(\Magento\Email\Block\Adminhtml\Template\Edit\Form $test, $variables) {
        
        foreach($this->_variables->toOptionArray() as $optionGroup){
            $variables[] = $optionGroup;
        }
        return $variables;
    }
}
