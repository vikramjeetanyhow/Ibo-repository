<?php

namespace Ibo\RegionalPricing\Model\Product\Attribute\Backend;

use Magento\Catalog\Model\Product\Attribute\Backend\Tierprice as CoreTierPrice;

class Tierprice extends CoreTierPrice
{
    /**
     * Add price qty to unique fields
     *
     * @param array $objectArray
     * @return array
     */
    protected function _getAdditionalUniqueFields($objectArray)
    {
        $uniqueFields = parent::_getAdditionalUniqueFields($objectArray);
        $uniqueFields['qty'] = $objectArray['price_qty'] * 1;
        $uniqueFields['customer_zone'] = $objectArray['customer_zone'];
        return $uniqueFields;
    }

    /**
     * Error message when duplicates
     *
     * @return \Magento\Framework\Phrase
     */
    protected function _getDuplicateErrorMessage()
    {
        return __('We found a duplicate website, tier price, customer zone/region, customer group and quantity.');
    }

}
