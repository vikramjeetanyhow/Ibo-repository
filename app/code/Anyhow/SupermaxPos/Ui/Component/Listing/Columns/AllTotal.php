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

class AllTotal extends Column
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
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        if (!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }

        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $expected_cash = $expected_card = $expected_offline = $expected_upi = $expected_wallet = 0.00;
                $title = "Opening Float";
                $posRegisterId = $item['pos_register_id'];
                $code = $item['code'];
                if ($code == 'cash') {
                    $expected_cash += (float) $item['amount'];
                } else if ($code == 'card') {
                    $expected += (float) $item['amount'];
                } else if ($code == 'upi') {
                    $expected_card += (float) $item['amount'];
                } else if ($code == 'offline') {
                    $expected_offline += (float) $item['amount'];
                } else if ($code == 'wallet') {
                    $expected_upi += (float) $item['amount'];
                }
            }
        }
        return $dataSource;
    }
}
