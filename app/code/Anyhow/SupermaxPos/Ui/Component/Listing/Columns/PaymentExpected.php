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

class PaymentExpected extends Column
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
        
        // if(isset($dataSource['data']['items'])) {
        //     $title = "Opening Float";
        //     $storeCurrencySymbol = '';
        //     $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        //     if(!empty($storeCurrencyCode)) {
        //         $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        //     }

        //     $fieldName = $this->getData('name');
           
        //     foreach($dataSource['data']['items'] as & $item) {
        //         $expected = 0.00;                
        //         $posRegisterId = $item['pos_register_id'];
        //         $getRegistertotalExpected = $connection->query("SELECT SUM(amount) as total_expected FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" .$posRegisterId. "' And title != '" .$title. "' ");

        //         foreach($getRegistertotalExpected as $getExpectedTotal) {
        //             $expected = (float)$getExpectedTotal['total_expected'];
        //         }

        //         $item[$fieldName] = $storeCurrencySymbol.$expected;
        //     }
        // }
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('ah_supermax_pos_report');
                $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
                $sql = "SELECT * FROM $tableName Where type ='reconcile' ";
                $reportData = $connection->query($sql)->fetchAll();
                if (!empty($reportData)) {
                    foreach ($reportData as $report) {
                        $payment_method = $report['payment_method'];
                    }
                }
                $storeCurrencySymbol = '';
                $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
                if (!empty($storeCurrencyCode)) {
                    $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
                }
                $title = "Opening Float";
                $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected = $emiExpected = $bankDepositExpected = 0;
                if (isset($dataSource['data']['items'])) {
                    $fieldName = $this->getData('name');
                    foreach ($dataSource['data']['items'] as &$item) {
                        $posRegisterId = $item['pos_register_id'];
                        if ($payment_method == 'cash') {
                            $getCaseTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' AND title != '" . $title . "' ")->fetchAll();
                            foreach ($getCaseTotalExpected as $getExpectedTotal) {
                                $cashExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $cashExpected;
                        } elseif ($payment_method == 'card') {
                            $getCaseTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card'")->fetchAll();
                            foreach ($getCaseTotalExpected as $getExpectedTotal) {
                                $cardExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $cardExpected;
                        } elseif ($payment_method == 'upi') {
                            $getCaseTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi'")->fetchAll();
                            foreach ($getCaseTotalExpected as $getExpectedTotal) {
                                $customExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $customExpected;
                        } elseif ($payment_method == 'offline') {
                            $getCaseTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline'")->fetchAll();
                            foreach ($getCaseTotalExpected as $getExpectedTotal) {
                                $offlineExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $offlineExpected;
                        } elseif ($payment_method == 'wallet') {
                            $getCaseTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet'")->fetchAll();
                            foreach ($getCaseTotalExpected as $getExpectedTotal) {
                                $walletExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $walletExpected;
                        } elseif ($payment_method == 'emi') {
                            $getemiTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi'")->fetchAll();
                            foreach ($getemiTotalExpected as $getExpectedTotal) {
                                $emiExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $emiExpected;
                        } elseif ($payment_method == 'bank_deposit') {
                            $getbank_depositTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit'")->fetchAll();
                            foreach ($getbank_depositTotalExpected as $getExpectedTotal) {
                                $bankDepositExpected = (float) $getExpectedTotal['expected_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $bankDepositExpected;
                        } else {
                            $getRegistertotalExpected = $connection->query("SELECT SUM(amount) as total_expected FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" .$posRegisterId. "' And title != '" .$title. "' ");

                            foreach($getRegistertotalExpected as $getExpectedTotal) {
                                $expected = (float)$getExpectedTotal['total_expected'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol.$expected;
                        }

                    }
                }

            }

        }
        return $dataSource;

    }
}
