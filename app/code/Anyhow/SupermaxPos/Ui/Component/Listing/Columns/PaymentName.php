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

class PaymentName extends Column
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
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_storeManager = $storeManager;
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
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $ReportDate = date($item['created_at']);
                if($item['method'] == 'CASH') {
                    $item[$fieldName] = 'Cash Payment';
                } else if ($item['method'] == 'OFFLINE') {
                    $item[$fieldName] = 'Offline Payment';
                } else if ($item['method'] == 'CREDIT-CARD') {
                    $item[$fieldName] = 'Credit Card Payment';
                } else if ($item['method'] == 'DEBIT-CARD') {
                    $item[$fieldName] = 'Debit Card Payment';
                } else if ($item['method'] == 'UPI') {
                    $item[$fieldName] = 'Ezetap UPI/QR Payment';
                } else if ($item['method'] == 'WALLET') {
                    $item[$fieldName] = 'Wallet Payment';
                } elseif($item['method'] == 'EMI') {
                    $item[$fieldName] = 'Pinelabs EMI Payment';
                } else if ($item['method'] == 'EZETAP-EMI') {
                    $item[$fieldName] = 'Ezetap EMI Payment';
                } else if ($item['method'] == 'CARD') {
                    $item[$fieldName] = 'Pinelabs Card (CC+DC) Payment';
                } else if ($item['method'] == 'PINELABS-UPI') {
                    $item[$fieldName] = 'Pinelabs UPI/QR Payment';
                } else if ($item['method'] == 'BANK-DEPOSIT') {
                    $item[$fieldName] = 'BANK-DEPOSIT Payment';
                }
            }
        } 
        return $dataSource;
    }
}
