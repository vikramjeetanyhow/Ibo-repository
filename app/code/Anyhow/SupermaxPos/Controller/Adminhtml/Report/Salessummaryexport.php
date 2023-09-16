<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Report;

use Magento\Framework\App\Filesystem\DirectoryList;

class Salessummaryexport extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesSummary\Collection $registerData,
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
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        if (!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/sales-summary-report-import-' . $name . '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();

        $columns = ['Payment Method', 'Total (' . $storeCurrencyCode . ')'];

        foreach ($columns as $column) {
            $header[] = $column;
        }
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('ah_supermax_pos_report');
        $reportData = $connection->query("SELECT * FROM $tableName Where type ='detail' ")->fetch();

        if (!empty($reportData)) {
            $to = date("Y-m-d", strtotime($reportData['to']));
            $from = date("Y-m-d", strtotime($reportData['from']));
        }
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $orderTable = $this->resource->getTableName('sales_order');
        $orderData = $connection->query("SELECT po.payment_data FROM $posOrderTable as po LEFT JOIN $orderTable as so ON(po.order_id =  so.entity_id) WHERE DATE(so.created_at) >= '$from' And DATE(so.created_at) <= '$to'")->fetchAll();

        $cash_amount = $offline_amount = $credit_card = $debit_card = $net_banking = $upi = $wallet = $emi = $card = $bankDeposit = $saraLoan = $bharatPe = $pinelabsUpi = $ezetapEmi = 0;
        if (!empty($orderData)) {
            foreach ($orderData as $order) {
                $paymentdata = (array) json_decode($order['payment_data']);
                foreach ($paymentdata as $payment) {
                    $payment = (array) $payment;
                    if ($payment['payment_code'] == 'CASH') {
                        $cash_amount += $payment['amount'];
                    } else if ($payment['payment_code'] == 'OFFLINE') {
                        $offline_amount += $payment['amount'];
                    } else if ($payment['payment_code'] == 'CREDIT-CARD') {
                        $credit_card += $payment['amount'];
                    } else if ($payment['payment_code'] == 'DEBIT-CARD') {
                        $debit_card += $payment['amount'];
                    } else if ($payment['payment_code'] == 'UPI') {
                        $upi += $payment['amount'];
                    } else if ($payment['payment_code'] == 'WALLET') {
                        $wallet += $payment['amount'];
                    } else if ($payment['payment_code'] == 'EMI') {
                        $emi += $payment['amount'];
                    } else if ($payment['payment_code'] == 'EZETAP-EMI') {
                        $ezetapEmi += $payment['amount'];
                    } else if ($payment['payment_code'] == 'CARD') {
                        $card += $payment['amount'];
                    } else if ($payment['payment_code'] == 'PINELABS-UPI') {
                        $pinelabsUpi += $payment['amount'];
                    } else if ($payment['payment_code'] == 'BANK-DEPOSIT') {
                        $bankDeposit += $payment['amount'];
                    } else if ($payment['payment_code'] == 'SARALOAN') {
                        $saraLoan += $payment['amount'];
                    } else if ($payment['payment_code'] == 'BHARATPE') {
                        $bharatPe += $payment['amount'];
                    } 
                }
            }
        }

        $stream->writeCsv($header);
        $register = $this->_registerData;
        $register_collection = $register->getData();
        $i = 0;
        foreach ($register_collection as $item) {
            $itemData = [];
            if($item['methods'] == 'CASH') {
                $itemData[] = 'Cash Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'OFFLINE') {
                $itemData[] = 'Offline Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'CREDIT-CARD') {
                $itemData[] = 'Credit Card Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'DEBIT-CARD') {
                $itemData[] = 'Debit Card Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'UPI') {
                $itemData[] = 'Ezetap UPI/QR Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'WALLET') {
                $itemData[] = 'Wallet Payment';
                $itemData[] =  $item['amount_details'];
            } elseif($item['methods'] == 'EMI') {
                $itemData[] = 'Pinelab EMI Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($payment['payment_code'] == 'EZETAP-EMI') {
                $itemData[] = 'Ezetap EMI Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'CARD') {
                $itemData[] = 'Pinelab Card (CC+DC) Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'PINELABS-UPI') {
                $itemData[] = 'Pinelab UPI/QR Payment';
                $itemData[] =  $item['amount_details'];
            } else if ($item['methods'] == 'BANK-DEPOSIT') {
                $itemData[] = 'Bank Deposit Payment';
                $itemData[] = $bankDeposit;
            } else if ($item['methods'] == 'BHARATPE') {
                $itemData[] = 'BharatPe Payment';
                $itemData[] = $bharatPe;
            } else if ($item['methods'] == 'SARALOAN') {
                $itemData[] = 'Saraloant Payment';
                $itemData[] = $saraLoan;
            }
             
        $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'sales-summary-report-import-' . $name . '.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}
