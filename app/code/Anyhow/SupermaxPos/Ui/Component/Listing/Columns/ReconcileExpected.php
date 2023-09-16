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

class ReconcileExpected extends Column
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
        $posRegisterId = '';
        $connection = $this->resource->getConnection();
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $reportTable = $this->resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile'")->fetch();

        if (!empty($reportData)) {
            $posRegisterId = $reportData['pos_register_id'];
        }
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        if (!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $title = "Opening Float";
        $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected = $emiExpected = $bankDepositExpected = $payLaterExpected = 0;
        $getCustomTotalExpected = $connection->query("SELECT * FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND title != '" . $title . "' ")->fetchAll();
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            if (!empty($getCustomTotalExpected)) {
                foreach ($getCustomTotalExpected as $getCustomExpected) {
                    if ($getCustomExpected['code'] == 'upi') {
                        $customExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'cash') {
                        $cashExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'card') {
                        $cardExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'offline') {
                        $offlineExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'wallet') {
                        $walletExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'emi') {
                        $emiExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'bank_deposit') {
                        $bankDepositExpected += (float) $getCustomExpected['amount'];
                    } elseif ($getCustomExpected['code'] == 'pay_later') {
                        $payLaterExpected += (float) $getCustomExpected['amount'];
                    }
                }
            }
        }
        $i = 0;
        foreach ($dataSource['data']['items'] as &$item) {
            if($item['paymentCode'] == 'cash') {
                $item[$fieldName] = $storeCurrencySymbol . $cashExpected;
            } elseif($item['paymentCode'] == 'offline') {
                $item[$fieldName] = $storeCurrencySymbol . $offlineExpected;
            } elseif($item['paymentCode'] == 'card') {
                $item[$fieldName] = $storeCurrencySymbol . $cardExpected;
            } elseif($item['paymentCode'] == 'upi') {
                $item[$fieldName] = $storeCurrencySymbol . $customExpected;
            } elseif($item['paymentCode'] == 'wallet') {
                $item[$fieldName] = $storeCurrencySymbol . $walletExpected;
            } elseif($item['paymentCode'] == 'emi') {
                $item[$fieldName] = $storeCurrencySymbol . $emiExpected;
            } elseif($item['paymentCode'] == 'bank_deposit') {
                $item[$fieldName] = $storeCurrencySymbol . $bankDepositExpected;
            }  elseif($item['paymentCode'] == 'pay_later') {
                $item[$fieldName] = $storeCurrencySymbol . $payLaterExpected;
            }
            $i++;
        }
        return $dataSource;
    }
}
