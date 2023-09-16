<?php

namespace Embitel\ProductImport\Model\Product\Attribute\Source;

class Status extends \Magento\Catalog\Model\Product\Attribute\Source\Status
{
    /**
     * Retrieve option array with empty value
     *
     * @return string[]
     */
    public function getAllOptions()
    {
        $result = [];
        
        $result[] = ['value' => '', 'label' => 'No'];
        foreach (self::getOptionArray() as $index => $value) {
            $result[] = ['value' => $index, 'label' => $value];
        }
        
        return $result;
    }
}
