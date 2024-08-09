<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Cashier\Attribute\Outlets;

class AllOutletOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
    \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\Collection $repository,
    \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->repository = $repository;
        $this->helper = $helper;
      }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $outletIDS = $this->helper->assignedOutletIds();
        $result = [];
        $outlets = $this->repository->getData();
        foreach ($outlets as $outlet) {
            if($outletIDS != 0 && in_array($outlet['pos_outlet_id'], $outletIDS)) {
                $outletId = $outlet['pos_outlet_id'];
                $outletName = $outlet['outlet_name'];
                $result[] = ['value' => $outletId, 'label' => $outletName];
            } elseif($outletIDS == 0) {
                $outletId = $outlet['pos_outlet_id'];
                $outletName = $outlet['outlet_name'];
                $result[] = ['value' => $outletId, 'label' => $outletName];
            }
        }
        return $result;
    }
}