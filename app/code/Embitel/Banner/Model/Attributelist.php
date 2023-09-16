<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Model;

use Magento\Framework\Option\ArrayInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class Attributelist 
{
    protected $productAttributeCollectionFactory;

    public function __construct(
        CollectionFactory $productAttributeCollectionFactory
    )
    {        
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
    }

    /*  
     * Option getter
     * @return array
     */
    public function toOptionArray($attributeIds)
    {
        
        $ret = [];
        $getAttributes = $this->toArray($attributeIds);
        foreach ($getAttributes as $attribute)
        {
            $ret[] = [
                    'value' => $attribute->getAttributeCode(),
                    'label' => $attribute->getFrontendLabel()
            ];            
        }
        return $ret;
    }

    /*
     * Get options in "key-value" format
     * @return array
     */
    public function toArray($attributeIds)
    { 
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $productAttributes */
        $productAttributes = $this->productAttributeCollectionFactory->create();
        $productAttributes->addFieldToFilter(
            ['is_filterable', 'is_filterable_in_search'],
            [[1, 2], 1]
        );        
        return $productAttributes;
    }
}