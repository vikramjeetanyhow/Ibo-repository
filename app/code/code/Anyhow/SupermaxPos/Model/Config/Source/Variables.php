<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Config\Source;

class Variables implements \Magento\Framework\Option\ArrayInterface {

    public function __construct(
        \Magento\Config\Model\Config\Structure\SearchInterface $configStructure,
        array $configPaths = []
    ) {
        $this->configStructure = $configStructure;
        $this->configPaths = $configPaths;
    }

    public function toOptionArray() {

        $optionArray = [
            "0" => [
                'label' => 'Supermax POS Common',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%ip" ip=$data.ip}}',
                        'label' => 'IP'
                    ],
                    '1' => [
                        'value' => '{{trans "%date_time" date_time=$data.date_time}}',
                        'label' => 'Date & Time'
                    ],
                    '2' => [
                        'value' => '{{trans "%browser" browser=$data.browser}}',
                        'label' => 'Browser'
                    ],
                    '3' => [
                        'value' => '{{trans "%system_platform" system_platform=$data.system_platform}}',
                        'label' => 'System Platform'
                    ],
                ]
            ],
            "1" => [
                'label' => 'Supermax POS Customer',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%customer_firstname" customer_firstname=$data.customer_firstname}}',
                        'label' => 'Customer Firstname'
                    ],
                    '1' => [
                        'value' => '{{trans "%customer_lastname" customer_lastname=$data.customer_lastname}}',
                        'label' => 'Customer Lastname'
                    ],
                    '2' => [
                        'value' => '{{trans "%customer_email" customer_email=$data.customer_email}}',
                        'label' => 'Customer Email'
                    ],
                    '3' => [
                        'value' => '{{trans "%customer_telephone" customer_telephone=$data.customer_telephone}}',
                        'label' => 'Customer Telephone'
                    ],
                ]
            ],
            "2" => [
                'label' => 'Supermax POS Cashier',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%cashier_firstname" cashier_firstname=$data.cashier_firstname}}',
                        'label' => 'Cashier Firstname'
                    ],
                    '1' => [
                        'value' => '{{trans "%cashier_lastname" cashier_lastname=$data.cashier_lastname}}',
                        'label' => 'Cashier Lastname'
                    ],
                    '2' => [
                        'value' => '{{trans "%cashier_email" cashier_email=$data.cashier_email}}',
                        'label' => 'Cashier Email'
                    ],
                    '3' => [
                        'value' => '{{trans "%cashier_telephone" cashier_telephone=$data.cashier_telephone}}',
                        'label' => 'Cashier Telephone'
                    ],
                ]
            ],
            "3" => [
                'label' => 'Supermax POS Product',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%product_name" product_name=$data.product_name}}',
                        'label' => 'Product Name'
                    ],
                    '1' => [
                        'value' => '{{trans "%product_sku" product_sku=$data.product_sku}}',
                        'label' => 'Product Sku'
                    ],
                    '2' => [
                        'value' => '{{trans "%product_price" product_price=$data.product_price}}',
                        'label' => 'Product Price'
                    ],
                    '3' => [
                        'value' => '{{trans "%product_tax_class" product_tax_class=$data.product_tax_class}}',
                        'label' => 'Product Tax Class'
                    ],
                    '4' => [
                        'value' => '{{trans "%product_quantity" product_quantity=$data.product_quantity}}',
                        'label' => 'Product Quantity'
                    ],
                    '5' => [
                        'value' => '{{trans "%product_return_reason" product_return_reason=$data.product_return_reason}}',
                        'label' => 'Product Return Reason'
                    ],
                    '6' => [
                        'value' => '{{trans "%product_return_resolution" product_return_resolution=$data.product_return_resolution}}',
                        'label' => 'Product Return Resolution'
                    ],
                    '7' => [
                        'value' => '{{trans "%product_return_item_condition" product_return_item_condition=$data.product_return_item_condition}}',
                        'label' => 'Return Item Condition'
                    ],
                ]
            ],
            "4" => [
                'label' => 'Supermax POS Order Return',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%return_status" return_status=$data.return_status}}',
                        'label' => 'Return Status'
                    ],
                    '1' => [
                        'value' => '{{trans "%return_custom_fields" return_custom_fields=$data.return_custom_fields}}',
                        'label' => 'Return Custom Fields'
                    ]
                ]
            ],
            "5" => [
                'label' => 'Supermax POS Order',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%order_id" order_id=$data.order_id}}',
                        'label' => 'Order Id'
                    ],
                    '1' => [
                        'value' => '{{trans "%order_date" order_date=$data.order_date}}',
                        'label' => 'Order Date'
                    ],
                    '2' => [
                        'value' => '{{trans "%payment_method" payment_method=$data.payment_method}}',
                        'label' => 'Payment Method'
                    ],
                ]
            ],
            "6" => [
                'label' => 'Supermax POS Hold Cart',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%hold_cart_note" hold_cart_note=$data.hold_cart_note}}',
                        'label' => 'Hold Cart Note'
                    ],
                ]
            ],

            "7" => [
                'label' => 'Supermax POS Cart Details',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%cart_subtotal" cart_subtotal=$data.cart_subtotal}}',
                        'label' => 'Cart Subtotal'
                    ],
                    '1' => [
                        'value' => '{{trans "%cart_tax_title" cart_tax_title=$data.cart_tax_title}}',
                        'label' => 'Cart Tax Title'
                    ],
                    '2' => [
                        'value' => '{{trans "%cart_tax_amount" cart_tax_amount=$data.cart_tax_amount}}',
                        'label' => 'Cart Tax Amount'
                    ],
                    '3' => [
                        'value' => '{{trans "%cart_grand_total" cart_grand_total=$data.cart_grand_total}}',
                        'label' => 'Cart Grand Total'
                    ],
                ]
            ],
            "8" => [
                'label' => 'Supermax POS Store / Outlet',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%outlet_name" outlet_name=$data.outlet_name}}',
                        'label' => 'Store Name'
                    ],
                    '1' => [
                        'value' => '{{trans "%outlet_email" outlet_email=$data.outlet_email}}',
                        'label' => 'Store Email'
                    ],
                    '2' => [
                        'value' => '{{trans "%store_url" store_url=$data.store_url}}',
                        'label' => 'Store Url'
                    ],
                    '3' => [
                        'value' => '{{trans "%outlet_telephone" outlet_telephone=$data.outlet_telephone}}',
                        'label' => 'Store Telephone'
                    ]
                ]
            ],
            "9" => [
                'label' => 'Supermax POS Custoer Feedback',
                'value' => [
                    '0' => [
                        'value' => '{{trans "%customer_feedback" customer_feedback=$data.customer_feedback}}',
                        'label' => 'Customer Feedback'
                    ],
                ]
            ],
        ];

        return $optionArray;
    }
}
