<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Cashier\Attribute\Permissions;

class AccessPermissions implements \Magento\Framework\Option\ArrayInterface
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
			// [
			// 	'value' => 'custom_product', 
			// 	'label' => __("Custom product")
			// ], 
			[
				'value' => 'cart_product_price', 
				'label' => __('Cart Product Price')
			],
			[
				'value' => 'cart_product_discount', 
				'label' => __('Cart Product Discount')
			],
			[
				'value' => 'cart_product_quantity', 
				'label' => __('Cart Product Quantity')
			],
			[
				'value' => 'cart_customer', 
				'label' => __("Cart Customer")
			], 
			[
				'value' => 'cart_discount', 
				'label' => __('Cart Discount')
			],
			[
				'value' => 'cart_coupon', 
				'label' => __("Cart Coupon")
			], 
			[
				'value' => 'dashboard', 
				'label' => __("Dashboard")
			],
			[
				'value' => 'quotation', 
				'label' => __("Quotation")
			],
			// [
			// 	'value' => 'cart_voucher', 
			// 	'label' => __('Cart voucher')
			// ],
			// [
			// 	'value' => 'cart_tax', 
			// 	'label' => __("Cart tax")
			// ],
			// [
			// 	'value' => 'shipping_charge', 
			// 	'label' => __('Cart shipping charge')
			// ],
			[
				'value' => 'mop_offline', 
				'label' => __("MOP Offline")
			],
			[
				'value' => 'cash_mgmt', 
				'label' => __("Cash Management")
			],
			[
				'value' => 'register_and_cash_mgmt', 
				'label' => __("Register Management")
			],
			[
				'value' => 'delivery_charge', 
				'label' => __("Delivery Charge")
			],
			[
				'value' => 'on_invoice_promotion', 
				'label' => __("On Invoice Promotion")
			],
			[
				'value' => 'no_access', 
				'label' => __("No Access")
			]

		];        

		// if($this->scopeConfig->getValue("ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_status", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 0)){
		// 	$result[] = [
		// 		'value' => 'restro_kotid_exist_cart_edit', 
		// 		'label' => __("Payment KOT Id exist cart product edit")
		// 	];
		// }

        return $result;
    }
}