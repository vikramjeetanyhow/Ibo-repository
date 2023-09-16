<?php

namespace Ibo\RegionalPricing\Model\Product\Attribute\Backend\TierPrice;

use Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\SaveHandler;

class SavePriceHandler extends SaveHandler
{

   /**
    * Get additional tier price fields.
    *
    * @param array $objectArray
    * @return array
    */
   public function getAdditionalFields(array $objectArray): array
   {
       $percentageValue = $this->getPercentage($objectArray);

       return [
           'value' => $percentageValue ? null : $objectArray['price'],
           'percentage_value' => $percentageValue ?: null,
           'customer_zone' => $this->getCustomerZone($objectArray),
       ];
   }

   /**
    * @param array $priceRow
    * @return mixed|null
    */
   public function getCustomerZone(array  $priceRow)
   {
       return isset($priceRow['customer_zone']) && !empty($priceRow['customer_zone'])
           ? $priceRow['customer_zone']
           : null;
   }
}