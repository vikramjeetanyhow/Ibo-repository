<?php

namespace Ibo\RegionalPricing\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Regions extends AbstractFieldArray
{
    /**
    * Prepare rendering the new field by adding all the needed columns
    */
    protected function _prepareToRender()
    {
        $this->addColumn('ibo_zone', ['label' => __('Region/Zone'), 'class' => 'required-entry']);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}
