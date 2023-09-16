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

class PaymentMethods extends Column
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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $reportTable = $resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetchAll();        
        $i = 0;
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if($item['paymentCode'] == 'cash') {
                    $item[$fieldName] = 'Cash Payment';
                } elseif($item['paymentCode'] == 'offline') {
                    $item[$fieldName] = 'Offline Payment';
                } elseif($item['paymentCode'] == 'card') {
                    $item[$fieldName] = 'Card Payment';
                } elseif($item['paymentCode'] == 'upi') {
                    $item[$fieldName] = 'UPI/QR Payment';
                } elseif($item['paymentCode'] == 'wallet') {
                    $item[$fieldName] = 'Wallet Payment';
                } elseif($item['paymentCode'] == 'emi') {
                    $item[$fieldName] = 'EMI Payment';
                } elseif($item['paymentCode'] == 'bank_deposit') {
                    $item[$fieldName] = 'Bank-Deposit Payment';
                } elseif($item['paymentCode'] == 'pay_later') {
                    $item[$fieldName] = 'Pay Later Payment';
                }
                $i++;
            }
        }
        return $dataSource;
    }
}
