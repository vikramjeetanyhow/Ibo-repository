<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;

use Magento\Framework\App\Filesystem\DirectoryList;

class Exportdata extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister\Collection $registerData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection
    ) {
        parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->_registerData = $registerData;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
        $this->supermaxUserCollection = $supermaxUserCollection;
    }

    public function execute()
    {
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/register-import-' . $name . '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();
        // for store base currency symbol
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        $columns = ['ID', 'Opened At', 'Closed At', 'Reconciliation Status', 'Register Name', 'Store Name', 'Cashier Name', 'Status', 'Cashier Clouser Note', 'Float Amount (' . $storeCurrencyCode . ')', 'Total Expected (' . $storeCurrencyCode . ')', 'Total Declared (' . $storeCurrencyCode . ')', 'Total Difference(' . $storeCurrencyCode . ')', 'Cash Expected (' . $storeCurrencyCode . ')', 'Cash Cashier Declared (' . $storeCurrencyCode . ')', 'Cash Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Cash Input (' . $storeCurrencyCode . ')', 'Head-Cashier Cash Difference (' . $storeCurrencyCode . ')', 'Card Expected (' . $storeCurrencyCode . ')', 'Card Cashier Declared (' . $storeCurrencyCode . ')', 'Card Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Card Input (' . $storeCurrencyCode . ')', 'Head-Cashier Card Difference (' . $storeCurrencyCode . ')', 'UPI Expected (' . $storeCurrencyCode . ')', 'UPI Cashier Declared (' . $storeCurrencyCode . ')', 'UPI Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier UPI Input (' . $storeCurrencyCode . ')', 'Head-Cashier UPI Difference (' . $storeCurrencyCode . ')', 'Offline Expected (' . $storeCurrencyCode . ')', 'Offline Cashier Declared (' . $storeCurrencyCode . ')', 'Offline Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Offline Input (' . $storeCurrencyCode . ')', 'Head-Cashier Offline Difference (' . $storeCurrencyCode . ')', 'Wallet Expected (' . $storeCurrencyCode . ')', 'Wallet Cashier Declared (' . $storeCurrencyCode . ')', 'Wallet Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Wallet Input (' . $storeCurrencyCode . ')', 'Head-Cashier Wallet Difference (' . $storeCurrencyCode . ')', 'EMI Expected (' . $storeCurrencyCode . ')', 'EMI Cashier Declared (' . $storeCurrencyCode . ')', 'EMI Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier EMI Input (' . $storeCurrencyCode . ')', 'Head-Cashier EMI Difference (' . $storeCurrencyCode . ')',  'Bank Deposit Expected (' . $storeCurrencyCode . ')', 'Bank Deposit Cashier Declared (' . $storeCurrencyCode . ')', 'Bank Deposit Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Bank Deposit Input (' . $storeCurrencyCode . ')', 'Head-Cashier Bank Deposit Difference (' . $storeCurrencyCode . ')', 'Pay Later Expected (' . $storeCurrencyCode . ')', 'Pay Later Cashier Declared (' . $storeCurrencyCode . ')', 'Pay Later Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Pay Later Input (' . $storeCurrencyCode . ')', 'Head-Cashier Pay Later Difference (' . $storeCurrencyCode . ')', 'Pay On Invoice Expected (' . $storeCurrencyCode . ')', 'Pay On Invoice Cashier Declared (' . $storeCurrencyCode . ')', 'Pay On Invoice Cashier Difference (' . $storeCurrencyCode . ')', 'Head-Cashier Pay On Invoice Input (' . $storeCurrencyCode . ')', 'Head-Cashier Pay On Invoice Difference (' . $storeCurrencyCode . ')', 'Head Cashier Clouser Note'];

        foreach ($columns as $column) {
            $header[] = $column;
        }

        $stream->writeCsv($header);

        $connection = $this->resource->getConnection();
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $register = $this->_registerData;
        $register_collection = $register->getData();

        if (!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }

        foreach ($register_collection as $item) {
            
            $storeName = '';
            // for store name
            $storeId = $item['store_view_id'];
            $storeData = $this->_storeManager->getStore($storeId);
            if (!empty($storeData)) {
                $storeName = $storeData->getName();
            }
            // for cashier name
            $cashierName = '';
            $cashierId = $item['pos_user_id'];
            $connection = $this->resource->getConnection();
            $supermaxUserTable = $this->resource->getTableName('ah_supermax_pos_user');
            $userData = $connection->query("SELECT * FROM $supermaxUserTable WHERE pos_user_id = '" . (int) $cashierId . "'")->fetch();
            if (!empty($userData)) {
                $cashierName = $userData['firstname'] . ' ' . $userData['lastname'];
            }
            $title = "Opening Float";
            $totalExpected = $totalDeclared = $totalDifference = 0.00;
            $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected =  $emiExpected = $bankDepositExpected = $payLaterExpected = $partialBallanceExpected = 0.00;
            
            $cashTotal = $cardTotal = $customTotal = $offlineTotal = $walletTotal = $emiTotal = $bankDepositTotal = $payLaterTotal = $partialBallanceTotal = 0.00;
            
            $cashDifference = $cardDifference = $customDifference = $offlineDifference = $walletDifference = $emiDifference = $bankDepositDifference = $payLaterDifference = $partialBallanceDifference = 0.00;
            
            $posRegisterId = $item['pos_register_id'];
            //float amount
            $getFloatAmount = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "'And title = '" . $title . "' ");
            foreach ($getFloatAmount as $FloatAmount) {
                $floatamount = (float) $FloatAmount['expected_total'];
            }
            //total expected
            $getTotalTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' And title != '" . $title . "' ");
            foreach ($getTotalTotalExpected as $getTotalExpected) {
                $totalExpected = (float) $getTotalExpected['expected_total'];
            }
            //cash expected
            $getCashTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' And title != '" . $title . "' ");
            foreach ($getCashTotalExpected as $getCashExpected) {
                $cashExpected = (float) $getCashExpected['expected_total'];
            }
            //card expected
            $getCardTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card' ");
            foreach ($getCardTotalExpected as $getCardExpected) {
                $cardExpected = (float) $getCardExpected['expected_total'];
            }
            //custom expected
            $getCustomTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi' ");
            foreach ($getCustomTotalExpected as $getCustomExpected) {
                $customExpected = (float) $getCustomExpected['expected_total'];
            }
            //offline expected
            $getOfflineTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
            foreach ($getOfflineTotalExpected as $getOfflineExpected) {
                $offlineExpected = (float) $getOfflineExpected['expected_total'];
            }

            //wallet expected
            $getCashTotalExpected = $connection->query("SELECT SUM(amount) as total_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
            foreach ($getCashTotalExpected as $getCashExpected) {
                $walletExpected = (float) $getCashExpected['total_total'];
            }
             //emi expected
             $getemiTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi' ");
             foreach ($getemiTotalExpected as $getemiExpected) {
                 $emiExpected = (float) $getemiExpected['expected_total'];
             }

             //bank-deposit expected
             $getbankDepositTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit' ");
             foreach ($getbankDepositTotalExpected as $getbankDepositExpected) {
                 $bankDepositExpected = (float) $getbankDepositExpected['expected_total'];
             }

            //pay_later expected
            $getpayLaterTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'pay_later' ");
            foreach ($getpayLaterTotalExpected as $getpayLaterExpected) {
                $payLaterExpected = (float) $getpayLaterExpected['expected_total'];
            }
            
            //partial ballance expected
            $getPartialTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'partial_payment_balance' ");
            foreach ($getPartialTotalExpected as $getPartialExpected) {
                $partialBallanceExpected = (float) $getPartialExpected['expected_total'];
            } 

            // total total
            $getTotalRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "'");
            foreach ($getTotalRegisterTotal as $getTotalTotal) {
                $totalDeclared = (float) $getTotalTotal['total_total'];
            }
            // cash total
            $getCashRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' ");
            foreach ($getCashRegisterTotal as $getCashTotal) {
                $cashTotal = (float) $getCashTotal['total_total'];
            }
            // card total
            $getCardRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card' ");
            foreach ($getCardRegisterTotal as $getCardTotal) {
                $cardTotal = (float) $getCardTotal['total_total'];
            }
            // custom total
            $getCustomRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi' ");
            foreach ($getCustomRegisterTotal as $getCustomTotal) {
                $customTotal = (float) $getCustomTotal['total_total'];
            }

            // offline total
            $getOfflineRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
            foreach ($getOfflineRegisterTotal as $getOfflineTotal) {
                $offlineTotal = (float) $getOfflineTotal['total_total'];
            }

            // wallet total
            $getWalletRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
            foreach ($getWalletRegisterTotal as $getWalletTotal) {
                $walletTotal = (float) $getWalletTotal['total_total'];
            }
            // emi total
            $getEmiRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi' ");
            foreach ($getEmiRegisterTotal as $getEmiTotal) {
                $emiTotal = (float) $getEmiTotal['total_total'];
            }

            // bank-deposit total
            $getbankDepositRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit' ");
            foreach ($getbankDepositRegisterTotal as $getbankDepositTotal) {
                $bankDepositTotal = (float) $getbankDepositTotal['total_total'];
            }

            // payLater total
            $getpayLaterRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'pay_later' ");
            foreach ($getpayLaterRegisterTotal as $getpayLaterTotal) {
                 $payLaterTotal = (float) $getpayLaterTotal['total_total'];
            }

            // partial total
            $getPartialTotals = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'partial_payment_balance' ");
            foreach ($getPartialTotals as $getPartialTotal) {
                $partialBallanceTotal = (float) $getPartialTotal['total_total'];
            }

            // total difference
            $getTotalRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "'");
            foreach ($getTotalRegisterDifference as $getTotalDifference) {
                $totalDifference = (float) $getTotalDifference['difference_total'];
            }

            // cash difference
            $getCashRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' ");
            foreach ($getCashRegisterDifference as $getCashDifference) {
                $cashDifference = (float) $getCashDifference['difference_total'];
            }
            // card difference
            $getCardRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card' ");
            foreach ($getCardRegisterDifference as $getCardDifference) {
                $cardDifference = (float) $getCardDifference['difference_total'];
            }
            // custom difference
            $getCustomRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi' ");
            foreach ($getCustomRegisterDifference as $getCustomDifference) {
                $customDifference = (float) $getCustomDifference['difference_total'];
            }

            // offline difference
            $getOfflineRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
            foreach ($getOfflineRegisterDifference as $getOfflineDifference) {
                $offlineDifference = (float) $getOfflineDifference['difference_total'];
            }
            // Wallet difference
            $getOfflineRegisterDifference = $connection->query("SELECT SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
            foreach ($getOfflineRegisterDifference as $getOfflineDifference) {
                $offlineDifference = (float) $getOfflineDifference['difference_total'];
            }
            
            // Emi difference
            $emiDifference = (float) ($emiExpected - $emiTotal);

            // bank Deposit difference
            $bankDepositDifference = (float) ($bankDepositExpected - $bankDepositTotal);
            
            //pay later differnces
            $payLaterDifference = (float) ($payLaterExpected - $payLaterTotal);

            //partial differnces
            $partialBallanceDifference = (float) ($partialBallanceExpected - $partialBallanceTotal);

            $itemData = [];
            $itemData[] = $item['pos_register_id'];
            $itemData[] = $item['date_open'];
            $itemData[] = $item['date_close'];
            $itemData[] = $item['reconciliation_status'] > 0 ? 'Done' : 'Pending';
            $itemData[] = $item['name'];
            $itemData[] = $item['outlet_name'];
            $itemData[] = $cashierName;
            $itemData[] = $item['status'] == 0 ? 'Disabled' : 'Enabled';
            $itemData[] = $item['close_note'];
            $itemData[] = $floatamount;
            $itemData[] = round($totalExpected, 2);
            $itemData[] = round($totalDeclared, 2);
            $itemData[] = round($totalDifference, 2);
            $itemData[] = round($cashExpected, 2);
            $itemData[] = round($cashTotal, 2);
            $itemData[] = round($cashDifference, 2);
            $itemData[] = round($item['head_cashier_cash_total'], 2);
            $itemData[] = round(($cashExpected - $item['head_cashier_cash_total']), 2);
            $itemData[] = round($cardExpected, 2);
            $itemData[] = round($cardTotal, 2);
            $itemData[] = round($cardDifference, 2);
            $itemData[] = round($item['head_cashier_card_total'], 2);
            $itemData[] = round(($cardExpected - $item['head_cashier_card_total']), 2);
            $itemData[] = round($customExpected, 2);
            $itemData[] = round($customTotal, 2);
            $itemData[] = round($customDifference, 2);
            $itemData[] = round($item['head_cashier_custom_total'], 2);
            $itemData[] = round(($customExpected - $item['head_cashier_custom_total']), 2);
            $itemData[] = round($offlineExpected, 2);
            $itemData[] = round($offlineTotal, 2);
            $itemData[] = round($offlineDifference, 2);
            $itemData[] = round($item['head_cashier_offline_total'], 2);
            $itemData[] = round(($offlineExpected - $item['head_cashier_offline_total']), 2);
            $itemData[] = round($walletExpected, 2);
            $itemData[] = round($walletTotal, 2);
            $itemData[] = round($walletDifference, 2);
            $itemData[] = round($item['head_cashier_wallet_total'], 2);
            $itemData[] = round(($walletExpected - $item['head_cashier_wallet_total']), 2);
            $itemData[] = round($emiExpected, 2);
            $itemData[] = round($emiTotal, 2);
            $itemData[] = round($emiDifference, 2);
            $itemData[] = round($item['head_cashier_emi_total'], 2);
            $itemData[] = round(($emiExpected - $item['head_cashier_emi_total']), 2);
            $itemData[] = round($bankDepositExpected, 2);
            $itemData[] = round($bankDepositTotal, 2);
            $itemData[] = round($bankDepositDifference, 2);
            $itemData[] = round($item['head_cashier_bank_deposit_total'], 2);
            $itemData[] = round(($bankDepositExpected - $item['head_cashier_bank_deposit_total']), 2);
            $itemData[] = round($payLaterExpected, 2);
            $itemData[] = round($payLaterTotal, 2);
            $itemData[] = round($payLaterDifference, 2);
            $itemData[] = round($item['head_cashier_pay_later_total'], 2);
            $itemData[] = round(($payLaterExpected - $item['head_cashier_pay_later_total']), 2);
            $itemData[] = round($partialBallanceExpected, 2);
            $itemData[] = round($partialBallanceTotal, 2);
            $itemData[] = round($partialBallanceDifference, 2);
            $itemData[] = round($item['head_cashier_partial_balance_total'], 2);
            $itemData[] = round(($partialBallanceExpected - $item['head_cashier_partial_balance_total']), 2);
            $itemData[] = $item['head_cashier_close_note'];

            $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'register-import-' . $name . '.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}
