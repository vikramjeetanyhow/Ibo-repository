<?php

namespace Ibo\MultiSlider\Block\Adminhtml\System;

/**
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Config extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Ibo_MultiSlider::configurations.phtml');
    }

    protected function _prepareToRender()
    {

        $this->addColumn('width', [
            'label' => 'width',
            'style' => 'width:100px',
            'class' => 'validate-number validate-digits validate-greater-than-zero'
        ]);

        $this->addColumn('height', [
            'label' => 'height',
            'style' => 'width:100px',
            'class' => 'validate-number validate-digits validate-greater-than-zero'
        ]);

        $this->addColumn('imagesize', [
            'label' => 'image size',
            'style' => 'width:100px',
            'class' => 'validate-number validate-digits validate-greater-than-zero'
        ]);

        $this->addColumn('breakpoint', [
            'label' => 'breakpoint',
            'style' => 'width:100px',
            'class' => 'validate-number validate-digits validate-greater-than-zero'
        ]);
    }
}
