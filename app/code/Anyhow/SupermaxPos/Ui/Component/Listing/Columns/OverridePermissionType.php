<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class OverridePermissionType extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {      
        $permisions = array(
            'cart_product_price' => __('Cart Product Price'),
            'cart_product_discount' => __('Cart Product Discount'),
            'cart_product_quantity' => __('Cart Product Quantity'),
            'cart_customer' => __('Cart Customer'),
            'cart_discount' => __('Cart Discount'),
            'cart_coupon' => __('Cart Coupon'),
            'dashboard' => __('Dashboard'),
            'register_and_cash_mgmt' => __('Register & Cash Management'),
            'mop_offline' =>  __('MOP Offline'),
            'delivery_charge' => __("Delivery Charge"),
            'on_invoice_promotion' => __("On Invoice Promotion"),
        );  

        if(isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                if (isset($item[$fieldName])  && array_key_exists($item[$fieldName], $permisions)) {
                    $item[$fieldName] = $permisions[$item[$fieldName]];
                } else {
                    $item[$fieldName] = 'N/A';
                }
            }
        }
        return $dataSource;
    }
}