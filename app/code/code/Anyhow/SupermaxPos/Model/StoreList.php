<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model;

class StoreList implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
    \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOldSalesOrders\Collection $repository,
    \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->repository = $repository;
        $this->helper = $helper;
      }

    
      const SARJAPUR_VALUE = 1;

      /**
       * Value which equal Disable for Enabledisable dropdown.
       */
      const OMR_VALUE = 2;

      const RANIGANJ_VALUE = 3;

      /**
       * Value which equal Disable for Enabledisable dropdown.
       */
      const BG_VALUE = 5;
    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::SARJAPUR_VALUE, 'label' => __('Sarjapur Store')],
            ['value' => self::OMR_VALUE, 'label' => __('OMR Chennai')],
            ['value' => self::RANIGANJ_VALUE, 'label' => __('Raniganj Hyderabad')],
            ['value' => self::BG_VALUE, 'label' => __('BG road')],
        ];
    }
}