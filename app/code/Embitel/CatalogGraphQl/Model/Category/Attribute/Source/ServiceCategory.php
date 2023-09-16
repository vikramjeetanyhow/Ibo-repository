<?php

namespace Embitel\Catalog\Model\Category\Attribute\Source;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;

class ServiceCategory extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
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
                ['label' => __('Please Select'), 'value' => ''],
                ['label' => __('LOCAL'), 'value' => 'LOCAL'],
                ['label' => __('REGIONAL'), 'value' => 'REGIONAL'],
                ['label' => __('NATIONAL'), 'value' => 'NATIONAL']
            ];
        }
        return $this->_options;
    }
}
