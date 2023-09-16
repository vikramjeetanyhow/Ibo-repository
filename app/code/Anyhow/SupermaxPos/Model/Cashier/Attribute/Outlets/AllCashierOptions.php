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

class AllCashierOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $repository,
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
        $cashiers = $this->repository->getData();
        foreach ($cashiers as $cashier) {
            if($outletIDS != 0 && in_array($cashier['pos_outlet_id'], $outletIDS)) {
                $cashierId = $cashier['pos_user_id'];
                $cashierName = $cashier['firstname'] . " " . $cashier['lastname'];
                $result[] = ['value' => $cashierId, 'label' => $cashierName];
            } elseif($outletIDS == 0) {
                $cashierId = $cashier['pos_user_id'];
                $cashierName = $cashier['firstname'] . " " . $cashier['lastname'];
                $result[] = ['value' => $cashierId, 'label' => $cashierName];
            }
            
        }
        return $result;
    }
}