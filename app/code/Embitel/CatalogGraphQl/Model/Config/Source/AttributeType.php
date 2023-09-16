<?php
namespace Embitel\CatalogGraphQl\Model\Config\Source;

class AttributeType implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
                ['value' => '', 'label' => '--Please Select--'],
                ['value' => 'magento-system', 'label' => __('Magento System')],
                ['value' => 'ebo-core', 'label' => __('EBO Core')],                
                ['value' => 'ebo-classification', 'label' => __('EBO Classification')]
        ];
    }
}
