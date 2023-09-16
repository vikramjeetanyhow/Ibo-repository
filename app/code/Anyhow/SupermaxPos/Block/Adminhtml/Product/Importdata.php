<?php 
/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */
namespace Anyhow\SupermaxPos\Block\Adminhtml\Product;

class Importdata extends \Magento\Backend\Block\Widget\Form\Container
{
    protected $_coreRegistry = null;

    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_blockGroup = 'Anyhow_SupermaxPos';
        $this->_controller = 'adminhtml_product';
        parent::_construct();
        $this->buttonList->remove('back');
        $this->buttonList->update('save', 'label', __('Import'));
        $this->buttonList->remove('reset');

        $this->addButton(
            'backhome',
            [
                'label' => __('Back'),
                'on_click' => sprintf("location.href = '%s';", $this->getUrl('supermax/product/index')),
                'class' => 'back',
                'level' => -2
            ]
        );
    }

    public function getHeaderText()
    {
        return __('Import Barcode Data');
    }

    public function getFormActionUrl()
    {
        if ($this->hasFormActionUrl()) {
            return $this->getData('form_action_url');
        }
        return $this->getUrl('supermax/product/save');
    }
}