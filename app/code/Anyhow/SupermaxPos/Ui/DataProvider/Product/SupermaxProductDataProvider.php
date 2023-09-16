<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Ui\DataProvider\Product;

class SupermaxProductDataProvider implements \Magento\Ui\DataProvider\AddFieldToCollectionInterface 
{
    public function addField(
        \Magento\Framework\Data\Collection $collection,
        $field,
        $alias = null
    ) 
    { 
        $collection->joinField(
             'barcode', 
             'custom_table', 
             'barcode', 
             'id=entity_id',
             null, 
             'left' 
        );
    }
}
