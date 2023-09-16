<?php

namespace Embitel\Catalog\Model\Category\Attribute\Source;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;

class AttributeSet extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @var CollectionFactory
     */
    protected $attributeSetCollection;

    /**
     * @param CollectionFactory $attributeSetCollection
     */
    public function __construct(
        CollectionFactory $attributeSetCollection
    ) {
        $this->attributeSetCollection = $attributeSetCollection;        
    }

    /**
     * @inheritdoc
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['label' => __('Please Select'), 'value' => '']
            ];

            $attributeSetCollection = $this->attributeSetCollection->create()
                ->addFieldToFilter('entity_type_id', 4)
                ->setOrder('attribute_set_id', 'ASC');
            $attributeSets = $attributeSetCollection->getItems();
            foreach ($attributeSets as $attributeSet) {
                if (!in_array(strtolower($attributeSet->getAttributeSetName()), ['default', 'ebo_default'])) {
                    $this->_options[] = 
                        [
                            'label' => $attributeSet->getAttributeSetName(),
                            'value' => $attributeSet->getAttributeSetId()
                        ];
                }
            }
        }
        return $this->_options;
    }
}
