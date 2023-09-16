<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\SalesRule\Model\Rule\Condition\Product;

/**
 * Subselect conditions for product.
 */
class SubselectExtended extends \Magento\SalesRule\Model\Rule\Condition\Product\Subselect
{ 
    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption(['qty' => __('total quantity'), 'base_row_total' => __('total amount Excl Tax'), 'row_total_incl_tax' => __('total amount Incl Tax')]);
        return $this;
    }

}
