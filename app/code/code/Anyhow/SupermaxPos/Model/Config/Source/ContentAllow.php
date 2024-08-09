<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

/**
 * All product unique code options for system.xml file
 */
namespace Anyhow\SupermaxPos\Model\Config\Source;
class ContentAllow implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeFactory
    ) {
        $this->_attributeFactory = $attributeFactory;
    }
    
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $attributeInfo = $this->_attributeFactory->create();
        foreach ($attributeInfo as $items) {
            if($items->getAttributeCode() == 'name' || $items->getAttributeCode() == 'price' || $items->getAttributeCode() == 'category_ids' || $items->getAttributeCode() =='media_gallery' || $items->getAttributeCode() =='gallery'){
                continue;
            }
            
            $result[] = array('value' => $items->getAttributeCode(),
            'label' => $items->getFrontendLabel());
        }
        return $result;
    }
}