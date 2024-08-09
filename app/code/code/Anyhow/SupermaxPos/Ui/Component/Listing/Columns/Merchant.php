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

class Merchant extends Column
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
        $connection = $this->resource->getConnection();
        $ezetapPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $pinelabsPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_pinelabs');

        if(isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                $orderId = $item['ah_pos_order_id'];
                $ezetapPaymentData = $connection->query("SELECT * FROM $ezetapPaymentTable WHERE order_id='" . $orderId . "'")->fetch();
                $pinelabsPaymentData = $connection->query("SELECT * FROM $pinelabsPaymentTable WHERE order_id='" . $orderId . "'")->fetch();
                $merchant = '-';
                if(!empty($ezetapPaymentData)) {
                    $merchant = "Ezetap";
                } else if(!empty($pinelabsPaymentData)) {
                    $merchant = "Pine-Labs";
                }
                if (isset($item[$fieldName]) ) {
                    $item[$fieldName] = $merchant;
                }
            }
        }
        return $dataSource;
    }
}