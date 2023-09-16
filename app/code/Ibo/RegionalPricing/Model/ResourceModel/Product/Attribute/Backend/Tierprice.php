<?php

namespace Ibo\RegionalPricing\Model\ResourceModel\Product\Attribute\Backend;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice as CoreTierPrice;

class Tierprice extends CoreTierPrice
{
    /**
     * Add qty column
     *
     * @param array $columns
     * @return array
     */
    protected function _loadPriceDataColumns($columns)
    {
        $columns = parent::_loadPriceDataColumns($columns);
        $columns['price_qty'] = 'qty';
        $columns['customer_zone'] = 'customer_zone';
        return $columns;
    }

}
