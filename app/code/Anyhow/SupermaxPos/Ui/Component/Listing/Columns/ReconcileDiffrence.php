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

class ReconcileDiffrence extends Column
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
        $connection = $this->resource->getConnection();
        $reportTable = $this->resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetch();

        if (!empty($reportData)) {
            $posRegisterId = $reportData['pos_register_id'];
        }
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');
        $title = "Opening Float";
        if (isset($dataSource['data']['items'])) {
            $storeCurrencySymbol = '';
            $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
            $cashDifference = $cardDifference = $customDifference = $offlineDifference = $walletDifference = $emiDifference = $bankDepositDifference = $payLaterDifference = 0.00;
            if (!empty($storeCurrencyCode)) {
                $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
            }
            $fieldName = $this->getData('name');
            $getRegisterData = $connection->query("SELECT head_cashier_cash_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' And title != '" . $title . "' ) AS cash_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterData)) {
                $cashDifference = $getRegisterData['cash_total'] - $getRegisterData['head_cashier_cash_total'];
            }
            $getRegisterDataCard = $connection->query("SELECT head_cashier_card_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card') AS card_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataCard)) {
                $cardDifference = $getRegisterDataCard['card_total'] - $getRegisterDataCard['head_cashier_card_total'];
            }
            $getRegisterDataUpi = $connection->query("SELECT head_cashier_custom_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi') AS upi_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataUpi)) {
                $customDifference = $getRegisterDataUpi['upi_total'] - $getRegisterDataUpi['head_cashier_custom_total'];
            }
            $getRegisterDataOffline = $connection->query("SELECT head_cashier_offline_total, (SELECT SUM(amount) FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline') AS offline_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataOffline)) {
                $offlineDifference = $getRegisterDataOffline['offline_total'] - $getRegisterDataOffline['head_cashier_offline_total'];
            }

            $getRegisterDataWallet = $connection->query("SELECT head_cashier_wallet_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet') AS wallet_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataWallet)) {
                $walletDifference = $getRegisterDataWallet['wallet_total'] - $getRegisterDataWallet['head_cashier_wallet_total'];
            }

            $getRegisterDataEmi = $connection->query("SELECT head_cashier_emi_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi') AS emi_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataEmi)) {
                $emiDifference = $getRegisterDataEmi['emi_total'] - $getRegisterDataEmi['head_cashier_emi_total'];
            }

            $getRegisterDatabankDeposit = $connection->query("SELECT head_cashier_bank_deposit_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit') AS bank_deposit_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDatabankDeposit)) {
                $bankDepositDifference = $getRegisterDatabankDeposit['bank_deposit_total'] - $getRegisterDatabankDeposit['head_cashier_bank_deposit_total'];
            }

            $getRegisterDataPayLater = $connection->query("SELECT head_cashier_pay_later_total, (SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'pay_later') AS pay_later_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

            if (!empty($getRegisterDataPayLater)) {
                $payLaterDifference = $getRegisterDataPayLater['pay_later_total'] - $getRegisterDataPayLater['head_cashier_pay_later_total'];
            }
        }
        $i = 0;
        foreach ($dataSource['data']['items'] as &$item) {
            if($item['paymentCode'] == 'cash') {
                $item[$fieldName] = $storeCurrencySymbol . $cashDifference;
            } elseif($item['paymentCode'] == 'offline') {
                $item[$fieldName] = $storeCurrencySymbol . $offlineDifference;
            } elseif($item['paymentCode'] == 'card') {
                $item[$fieldName] = $storeCurrencySymbol . $cardDifference;
            } elseif($item['paymentCode'] == 'upi') {
                $item[$fieldName] = $storeCurrencySymbol . $customDifference;
            } elseif($item['paymentCode'] == 'wallet') {
                $item[$fieldName] = $storeCurrencySymbol . $walletDifference;
            } elseif($item['paymentCode'] == 'emi') {
                $item[$fieldName] = $storeCurrencySymbol . $emiDifference;
            } elseif($item['paymentCode'] == 'bank_deposit') {
                $item[$fieldName] = $storeCurrencySymbol . $bankDepositDifference;
            } elseif($item['paymentCode'] == 'pay_later') {
                $item[$fieldName] = $storeCurrencySymbol . $payLaterDifference;
            }
            $i++;
        }

        return $dataSource;
    }
}
