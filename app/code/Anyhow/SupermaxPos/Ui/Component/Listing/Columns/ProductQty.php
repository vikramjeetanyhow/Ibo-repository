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

class ProductQty extends Column
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
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $connection = $this->resource->getConnection();
        $salesOrderItemTable = $this->resource->getTableName('sales_order_item');
        if(isset($dataSource['data']['items'])) {           
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                if(isset($item['permission_type']) && (($item['permission_type'] == 'mop_offline') ||  ($item['permission_type']== "MOP Offline") || ($item['permission_type'] == 'Delivery Charge') || ($item['permission_type'] == 'delivery_charge') || ($item['permission_type'] == 'on_invoice_promotion') || ($item['permission_type'] == 'On Invoice Promotion'))) {
                    $item[$fieldName] = "N/A";
                } else {
                    if (isset($item['sku']) && $item['sku']) {                    
                        $quantity = $connection->query("SELECT qty_ordered FROM $salesOrderItemTable WHERE sku = '" .  $item['sku'] ."' AND order_id = '". $item['order_id']. "'")->fetch();                                     
                        $item[$fieldName] = isset($quantity['qty_ordered']) ? (int)$quantity['qty_ordered']: 0;                    
                    } else {
                        $item[$fieldName] = 0;
                    }
                }
            }
        }
        return $dataSource;
    }
}