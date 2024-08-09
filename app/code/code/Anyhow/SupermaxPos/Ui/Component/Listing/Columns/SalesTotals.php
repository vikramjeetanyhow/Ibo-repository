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

class SalesTotals extends Column
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
        array $data = [],
        \Anyhow\SupermaxPos\Helper\Data $helper

    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
        $this->helper = $helper;
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

                $from = date("Y-m-d", strtotime($item['date_start']));
                $to = date("Y-m-d", strtotime($item['date_end']));
                $payment_method = '';

                $assignedOutletId = $this->helper->assignedOutletIds();
                $assignedOutletIds = is_array($assignedOutletId) ? implode(",",$assignedOutletId) : 0;                

                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('ah_supermax_pos_report');
                $sql = "SELECT * FROM $tableName Where type ='sales' ";
                $reportData1 = $connection->query($sql)->fetchAll();
                if(!empty($reportData1)){
                    foreach($reportData1 as $report){                
                        $posOutlet = json_decode($report['pos_outlet_id']);
                    }
                }
                $posOutletId = is_array($posOutlet) ? implode(",",$posOutlet) : '';
                $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
                $orderTable = $this->resource->getTableName('sales_order');
                $sql = "SELECT po.payment_data FROM $posOrderTable as po LEFT JOIN $orderTable as so ON(po.order_id =  so.entity_id) WHERE DATE(so.created_at) >= '$from' And DATE(so.created_at) <= '$to'";
                if($assignedOutletIds) {
                    $sql .= " AND po.pos_outlet_id IN ($assignedOutletIds)";        
                } else if(isset($posOutletId) && $posOutletId != ''){
                    $sql .= " AND po.pos_outlet_id IN ($posOutletId) ";
                }
                $orderData = $connection->query($sql)->fetchAll();
                $storeCurrencySymbol = '';
                $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
                if (!empty($storeCurrencyCode)) {
                    $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
                }
                $cash_amount = $offline_amount = $credit_card = $debit_card = $net_banking = $upi = $wallet = $emi = $card = $bankDeposit = $saraLoan = $bharatPe = 0;
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
                            } else if ($payment['payment_code'] == 'CARD') {
                                $card += $payment['amount'];
                            } else if ($payment['payment_code'] == 'BANK-DEPOSIT') {
                                $bankDeposit += $payment['amount'];
                            }else if ($payment['payment_code'] == 'SARALOAN') {
                                $saraLoan += $payment['amount'];
                            }else if ($payment['payment_code'] == 'BHARATPE') {
                                $bharatPe += $payment['amount'];
                            }
                        }
                    }
                }
            $item[$fieldName] = $storeCurrencySymbol.$item[$fieldName];            
        }
    }
    return $dataSource;
    }
}
