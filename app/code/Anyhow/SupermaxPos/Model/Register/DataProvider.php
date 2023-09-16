<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Register;

use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;

class DataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    protected $collection;
    protected $dataPersistor;
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $registerCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $registerCollectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->meta = $this->prepareMeta($this->meta);

    }
    public function prepareMeta(array $meta)
    {
        return $meta;
    }
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        // $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');
        $supermaxRegisterTransTable = $resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $supermaxUserCollection = $resource->getTableName('ah_supermax_pos_user');
        $supermaxoutlet = $resource->getTableName('ah_supermax_pos_outlet');

        $totalExpected = $totalDeclared = $totalDifference = 0.00;
        $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected =  $emiExpected = $bankDepositExpected = $payLaterExpected = 0.00;
        // $podExpected = 0.00;
        $cashTotal = $cardTotal = $customTotal = $offlineTotal = $walletTotal = $emiTotal = $bankDepositTotal = $payLaterTotal = 0.00;
         // $podTotal = 0.00;
        $cashDifference = $cardDifference = $customDifference = $offlineDifference = $walletDifference = $emiDifference = $bankDepositDifference = $payLaterDifference = 0.00 ;
        $title = "Opening Float";
        foreach ($items as $registerdata) {

            $posRegisterId = $registerdata['pos_register_id'];

            $getTotalTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "'");
            foreach ($getTotalTotalExpected as $getTotalExpected) {
                $totalExpected = (float) $getTotalExpected['expected_total'];
            }
            //float amount
            $getFloatAmount = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND title = '" . $title . "' ");
            foreach ($getFloatAmount as $FloatAmount) {
                $floatamount = (float) $FloatAmount['expected_total'];
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

            //wallet expected
            $getCashTotalExpected = $connection->query("SELECT SUM(amount) as total_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
            foreach ($getCashTotalExpected as $getCashExpected) {
                $walletExpected = (float) $getCashExpected['total_total'];
            }

            //offline expected
            $getOfflineTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
            foreach ($getOfflineTotalExpected as $getOfflineExpected) {
                $offlineExpected = (float) $getOfflineExpected['expected_total'];
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

            // wallet total
            $getWalletRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
            foreach ($getWalletRegisterTotal as $getWalletTotal) {
                $walletTotal = (float) $getWalletTotal['total_total'];
            }
            // offline total
            $getOfflineRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
            foreach ($getOfflineRegisterTotal as $getOfflineTotal) {
                $offlineTotal = (float) $getOfflineTotal['total_total'];
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

            $totalDifference = (float) ($totalExpected - $totalDeclared);
            $cashDifference = (float) ($cashExpected - $cashTotal);
            $cardDifference = (float) ($cardExpected - $cardTotal);
            $customDifference = (float) ($customExpected - $customTotal);
            $walletDifference = (float) ($walletExpected - $walletTotal);
            $offlineDifference = (float) ($offlineExpected - $offlineTotal);
            $emiDifference = (float) ($emiExpected - $emiTotal);
             // bank -deposit difference
            $bankDepositDifference = (float) ($bankDepositExpected - $bankDepositTotal);
            $payLaterDifference = (float) ($payLaterExpected - $payLaterTotal);

            $cashierName = '';
            $cashierId = $registerdata['pos_user_id'];
            $userDataCollection = $connection->query("SELECT firstname , lastname FROM $supermaxUserCollection WHERE pos_user_id = '" . $cashierId . "'");
            foreach ($userDataCollection as $userData) {
                $cashierName = $userData['firstname'] . ' ' . $userData['lastname'];
            }

            $this->loadedData[$registerdata->getId()] = $registerdata->getData();
            $this->loadedData[$registerdata->getId()]['floatamount'] = $floatamount;
            $this->loadedData[$registerdata->getId()]['cashexpected'] = $cashExpected;
            $this->loadedData[$registerdata->getId()]['cashtotal'] = $cashTotal;
            $this->loadedData[$registerdata->getId()]['cashdifference'] = $cashDifference;
            $this->loadedData[$registerdata->getId()]['cardexpected'] = $cardExpected;
            $this->loadedData[$registerdata->getId()]['cardtotal'] = $cardTotal;
            $this->loadedData[$registerdata->getId()]['carddifference'] = $cardDifference;
            $this->loadedData[$registerdata->getId()]['customexpected'] = $customExpected;
            $this->loadedData[$registerdata->getId()]['customtotal'] = $customTotal;
            $this->loadedData[$registerdata->getId()]['customdifference'] = $customDifference;
            $this->loadedData[$registerdata->getId()]['walletexpected'] = $walletExpected;
            $this->loadedData[$registerdata->getId()]['wallettotal'] = $walletTotal;
            $this->loadedData[$registerdata->getId()]['walletdifference'] = $walletDifference;
            $this->loadedData[$registerdata->getId()]['offlineexpected'] = $offlineExpected;
            $this->loadedData[$registerdata->getId()]['offlinetotal'] = $offlineTotal;
            $this->loadedData[$registerdata->getId()]['offlinedifference'] = $offlineDifference;
            $this->loadedData[$registerdata->getId()]['emiexpected'] = $emiExpected;
            $this->loadedData[$registerdata->getId()]['emitotal'] = $emiTotal;
            $this->loadedData[$registerdata->getId()]['emidifference'] = $emiDifference;
            $this->loadedData[$registerdata->getId()]['bankdepositexpected'] = $bankDepositExpected;
            $this->loadedData[$registerdata->getId()]['bankdeposittotal'] = $bankDepositTotal;
            $this->loadedData[$registerdata->getId()]['bankdepositdifference'] = $bankDepositDifference;
            $this->loadedData[$registerdata->getId()]['paylaterexpected'] = $payLaterExpected;
            $this->loadedData[$registerdata->getId()]['paylatertotal'] = $payLaterTotal;
            $this->loadedData[$registerdata->getId()]['paylaterdifference'] = $payLaterDifference;
            $this->loadedData[$registerdata->getId()]['cashierName'] = $cashierName;
        }

        $data = $this->dataPersistor->get('registerdata');
        return $this->loadedData;

    }
}
