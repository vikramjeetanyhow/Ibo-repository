<?php

namespace Embitel\ProductExport\Block\Adminhtml\Export\Edit\Tab;

use Embitel\ProductExport\Model\ProductExportHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;

class Product extends Generic implements TabInterface
{
    private ProductExportHelper $productExportHelper;
    private AttributeSetCollectionFactory $collectionFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param ProductExportHelper $productExportHelper
     * @param AttributeSetCollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(Context $context, Registry $registry, FormFactory $formFactory, ProductExportHelper $productExportHelper,
                                AttributeSetCollectionFactory $collectionFactory, array $data = [])
    {
        parent::__construct($context, $registry, $formFactory, $data);
        $this->productExportHelper = $productExportHelper;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return __('Product Export');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Prepare form
     *
     * @return $this
     */
    protected function _prepareForm()
    {
        $form = $this->_formFactory->create();
        $attributeSets = $this->collectionFactory->create();
        $attributeSets->addFieldToFilter("entity_type_id", 4)->load();
        $attributeSetOptions = [];

        if ($this->productExportHelper->getConfigs()->getFullExport()) {
            $attributeSetOptions['all'] = "Full Catalog";
            $attributeSetOptions['all_attribute_set'] = "Full Attribute Set";
        }

        if ($this->productExportHelper->getConfigs()->getAttrExport()) {
            foreach ($attributeSets as $attributeSet) {
                $attributeSetOptions[$attributeSet->getAttributeSetId()] = $attributeSet->getAttributeSetName();
            }
        }

        if ($this->productExportHelper->getConfigs()->getAttrExport() || $this->productExportHelper->getConfigs()->getFullExport()) {

            $fieldsetexport = $form->addFieldset('export_fieldset', ['legend' => __('Product Export'), 'class' => 'fieldset-wide']);

            $fieldsetexport->addField('attribute_set_id', 'select', ['name' => 'attribute_set_id', 'label' => __('Attribute Set'), 'text' => __('Attribute Set'), 'values' => $attributeSetOptions]);

            $fieldsetexport->addField('export_all', 'submit', ['name' => 'export_all', 'text' => __('Export'), 'class' => 'action-default scalable save primary', 'value' => __('Export'), 'style' => 'width:175px']);
            $this->setForm($form);
        }
        return parent::_prepareForm();
    }
}
