<?php
namespace Embitel\CatalogGraphQl\Plugin\Block\Adminhtml\Product\Attribute\Edit\Tab;

class Front
{

    /**
     * @var Yesno
     */
    protected $_yesNo;

    protected $_attributeType;

    protected $_attributeCategoryList;
    
    protected $_coreRegistry;

    /**
     * @param Magento\Config\Model\Config\Source\Yesno $yesNo
     */
    public function __construct(
        \Magento\Config\Model\Config\Source\Yesno $yesNo,
        \Embitel\CatalogGraphQl\Model\Config\Source\AttributeType $attributeType,
        \Embitel\CatalogGraphQl\Model\Config\Source\AttributeCategoryList $attributeCategoryList,
        \Magento\Framework\Registry $_coreRegistry
    ) {
        $this->_yesNo = $yesNo;
        $this->_attributeType = $attributeType;
        $this->_attributeCategoryList = $attributeCategoryList;
        $this->_coreRegistry = $_coreRegistry;
    }

    /**
     * Get form HTML
     *
     * @return string
     */
    public function aroundGetFormHtml(
        \Magento\Catalog\Block\Adminhtml\Product\Attribute\Edit\Tab\Front $subject,
        \Closure $proceed
    )
    {
        $attributeObject = $this->getAttributeObject();
        $yesnoSource = $this->_yesNo->toOptionArray();
        $attributeTypeSource = $this->_attributeType->toOptionArray();
        $attributeCategorySource = $this->_attributeCategoryList->toOptionArray();
        $form = $subject->getForm();
        $fieldset = $form->getElement('front_fieldset');
        $fieldset->addField(
            'is_required_for_publish',
            'select',
            [
                'name' => 'is_required_for_publish',
                'label' => __('Values Required for Publish'),
                'title' => __('Values Required for Publish'),
                'value' => $attributeObject->getData('is_required_for_publish'),
                'values' => $yesnoSource,
            ]
        );
        $fieldset->addField(
            'used_in_product_specs',
            'select',
            [
                'name' => 'used_in_product_specs',
                'label' => __('Used in Product Specifications'),
                'title' => __('Used in Product Specifications'),
                'note' => __('Depends on design theme.'),
                'value' => $attributeObject->getData('used_in_product_specs'),
                'values' => $yesnoSource,
            ]
        );
        $fieldset->addField(
            'product_specs_position',
            'text',
            [
                'name' => 'product_specs_position',
                'label' => __('Product Specifications Position'),
                'title' => __('Position in Product Specifications'),
                'note' => __('Position of attribute in product specifications tab.'),
                'value' => $attributeObject->getData('product_specs_position'),
                'class' => 'validate-digits'
            ]
        );
        $fieldset->addField(
            'attribute_type',
            'select',
            [
                'name' => 'attribute_type',
                'label' => __('Attribute Type'),
                'title' => __('Attribute Type'),                
                'value' => $attributeObject->getData('attribute_type'),
                'values' => $attributeTypeSource,
            ]
        );
        $fieldset->addField(
            'attribute_category_ids',
            'multiselect',
            [
                'name' => 'attribute_category_ids',
                'label' => __('Attribute Filter Category'),
                'title' => __('Attribute Filter Category'),
                'value' => json_decode($attributeObject->getData('attribute_category_ids')),
                'values' => $attributeCategorySource,
            ]
        );
        return $proceed();
    }

    private function getAttributeObject()
    {
        return $this->_coreRegistry->registry('entity_attribute');
    }
}
