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

class ReconcileInput extends Column
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
        $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');
        $cashHead = $cardHead = $customHead = $offlineHead = $walletHead = $emiHead = $bankDepositHead = $payLaterHead = 0.00;
        if (isset($dataSource['data']['items'])) {
            $storeCurrencySymbol = '';
            $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
            if (!empty($storeCurrencyCode)) {
                $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
            }
            $fieldName = $this->getData('name');
            $getHeadCashier = $connection->query("SELECT *  FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'");
            foreach ($getHeadCashier as $getHeadCashier) {
                $cashHead = (float) $getHeadCashier['head_cashier_cash_total'];
                $cardHead = (float) $getHeadCashier['head_cashier_card_total'];
                $customHead = (float) $getHeadCashier['head_cashier_custom_total'];
                $offlineHead = (float) $getHeadCashier['head_cashier_offline_total'];
                $walletHead = (float) $getHeadCashier['head_cashier_wallet_total']; 
                $emiHead = (float) $getHeadCashier['head_cashier_emi_total'];
                $bankDepositHead = (float) $getHeadCashier['head_cashier_bank_deposit_total'];
                $payLaterHead = (float) $getHeadCashier['head_cashier_pay_later_total'];
               
            }
        } 
        $i = 0;
        $fieldName = $this->getData('name');
         foreach ($dataSource['data']['items'] as & $item) {
            
            if($item['paymentCode'] == 'cash') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $cashHead;
            } elseif($item['paymentCode'] == 'offline') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $offlineHead;
            } elseif($item['paymentCode'] == 'card') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $cardHead;
            } elseif($item['paymentCode'] == 'upi') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $customHead;
            } elseif($item['paymentCode'] == 'wallet') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $walletHead;
            } elseif($item['paymentCode'] == 'emi') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $emiHead;
            } elseif($item['paymentCode'] == 'bank_deposit') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $bankDepositHead;
            } elseif($item['paymentCode'] == 'pay_later') {
                $item['pos_register_id'] = $posRegisterId;
                $item[$fieldName] = $storeCurrencySymbol . $payLaterHead;
            } 
            $i++;
        }
        return $dataSource;
    }
}
