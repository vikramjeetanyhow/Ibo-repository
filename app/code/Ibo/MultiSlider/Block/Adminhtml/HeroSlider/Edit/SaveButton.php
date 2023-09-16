<?php
namespace Ibo\MultiSlider\Block\Adminhtml\HeroSlider\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save Slider'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'on_click' => '',
            'sort_order' => 90,
        ];
    }

     /**
      * @return string
      */
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save', ['id' => $this->getId()]);
    }
}
