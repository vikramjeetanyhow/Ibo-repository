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

class ReconcileDeclered extends Column
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
        $reportTable = $this->resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetch();

        if (!empty($reportData)) {
            $posRegisterId = $reportData['pos_register_id'];                    
        }
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        if (isset($dataSource['data']['items'])) {
            $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected = $emiExpected = $bankDepositExpected = $payLatertExpected = 0.00;
            $storeCurrencySymbol = '';
            $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
            if (!empty($storeCurrencyCode)) {
                $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
            }
            $fieldName = $this->getData('name');
            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'cash' ");
            foreach ($getRegisterTotal as $RegisterTotal) {
                $cashExpected = (float) $RegisterTotal['total_total'];
            }
            $getCardTotalExpected =  $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'card' ");
            foreach ($getCardTotalExpected as $getCardExpected) {
                $cardExpected = (float) $getCardExpected['total_total'];
            }
            $getCustomTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'upi' ");
            foreach ($getCustomTotalExpected as $getCustomExpected) {
                $customExpected = (float) $getCustomExpected['total_total'];
            }
            $getOfflineTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'offline' ");
            foreach ($getOfflineTotalExpected as $getOfflineExpected) {
                $offlineExpected = (float) $getOfflineExpected['total_total'];
            }
            $getwalletTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'wallet' ");
            foreach ($getwalletTotalExpected as $getwalletExpected) {
                $walletExpected = (float) $getwalletExpected['total_total'];
            }
            $getemiTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'emi' ");
            foreach ($getemiTotalExpected as $getemiExpected) {
                $emiExpected = (float) $getemiExpected['total_total'];
            }
            $getbankDepositTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'bank_deposit' ");
            foreach ($getbankDepositTotalExpected as $getbankDepositExpected) {
                $bankDepositExpected = (float) $getbankDepositExpected['total_total'];
            }
            $getpayLatertTotalExpected = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = 'pay_later' ");
            foreach ($getpayLatertTotalExpected as $getpayLatertExpected) {
                $payLatertExpected = (float) $getpayLatertExpected['total_total'];
            }
        }
        $i = 0;
        // if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if($item['paymentCode'] == 'cash') {
                    $item['pos_register_id'] = $posRegisterId;              
                    $item[$fieldName] = $storeCurrencySymbol . $cashExpected;
                } elseif($item['paymentCode'] == 'offline') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $offlineExpected;
                } elseif($item['paymentCode'] == 'card') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $cardExpected;
                } elseif($item['paymentCode'] == 'upi') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $customExpected;
                } elseif($item['paymentCode'] == 'wallet') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $walletExpected;
                } elseif($item['paymentCode'] == 'emi') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $emiExpected;
                } elseif($item['paymentCode'] == 'bank_deposit') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $bankDepositExpected;
                } elseif($item['paymentCode'] == 'pay_later') {
                    $item['pos_register_id'] = $posRegisterId;
                    $item[$fieldName] = $storeCurrencySymbol . $payLatertExpected;
                }
               $i++;
            }
        
        return $dataSource;
    }
}
