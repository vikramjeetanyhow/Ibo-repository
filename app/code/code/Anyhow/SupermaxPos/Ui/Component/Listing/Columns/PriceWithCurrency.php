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

class PriceWithCurrency extends Column
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

        if(isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                if(isset($item['permission_type']) && (($item['permission_type'] == 'mop_offline') ||  ($item['permission_type']== "MOP Offline") || ($item['permission_type'] == 'on_invoice_promotion') ||  ($item['permission_type']== "On Invoice Promotion"))) {
                    $item[$fieldName] = "N/A";
                } else if(isset($item['permission_type']) && (($item['permission_type'] == 'Delivery Charge') || ($item['permission_type'] == 'delivery_charge'))) {
                    if (isset($item[$fieldName])) {
                        if(($fieldName == 'original_price') && ($item['original_price'] == 0)) {
                            $item['original_price'] = "N/A";
                        } else if(($fieldName == 'overrided_price') && ($item['overrided_price'] == 0)) {
                            $item['overrided_price'] = "N/A";
                        } else {
                            $item[$fieldName] = $storeCurrencySymbol . (float)$item[$fieldName];
                        }
                    }
                } else {
                    if (isset($item[$fieldName])) {
                        if(($fieldName == 'overrided_delivery_price') && ($item['overrided_delivery_price'] == 0)) {
                            $item['overrided_delivery_price'] = "N/A";
                        } else if(($fieldName == 'original_delivery_price') && ($item['original_delivery_price'] == 0)) {
                            $item['original_delivery_price'] = "N/A";
                        } else {
                            $item[$fieldName] = $storeCurrencySymbol . (float)$item[$fieldName];
                        }
                    }
                }
            }
        }
        return $dataSource;
    }
}