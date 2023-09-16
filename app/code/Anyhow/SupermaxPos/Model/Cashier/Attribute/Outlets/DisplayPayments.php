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

class DisplayPayments implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {
		$this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [
			[
				'value' => 'display_cash_payment', 
				'label' => __("Enable Cash Payment")
			], 
			[
				'value' => 'display_online_payment', 
				'label' => __('Enable Online Payment')
			],
			[
				'value' => 'display_offline_payment', 
				'label' => __('Enable Offline Payment')
			],
			[
				'value' => 'display_split_payment', 
				'label' => __('Enable Split Payment')
			],
			[
				'value' => 'display_pay_later_payment', 
				'label' => __("Enable Pay Leter Payment")
			], 
			[
				'value' => 'display_bank_deposite_payment', 
				'label' => __('Enable Bank Deposit Payment')
			],
			
		];		

        return $result;
    }
}