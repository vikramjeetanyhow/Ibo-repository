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

class PaymentDeclared extends Column
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
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');
           
        // if(isset($dataSource['data']['items'])) {
        //     $storeCurrencySymbol = '';
        //     $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        //     if(!empty($storeCurrencyCode)) {
        //         $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        //     }

        //     $fieldName = $this->getData('name');

        //     foreach($dataSource['data']['items'] as & $item) {                
        //         $total = 0.00;
        //         $posRegisterId = $item['pos_register_id'];
        //         $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "'")->fetch();
        //         $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, SUM(head_cashier_cash_total + head_cashier_card_total + head_cashier_custom_total + head_cashier_offline_total + head_cashier_wallet_total + head_cashier_emi_total ) as total_total FROM $supermaxRegister WHERE pos_register_id = '" .$posRegisterId. "'")->fetch();
               
        //         if(!empty($getHeadRegisterTotal)) {
        //             if($getHeadRegisterTotal['reconciliation_status'] == 1) { 
        //                 $total = (float)$getHeadRegisterTotal['total_total'];
        //             } else {                
        //                 $total = (float)$getRegisterTotal['total_total'];
        //             }
        //         } else {
        //             $total = (float)$getRegisterTotal['total_total'];
        //         }
                
        //         $item[$fieldName] = $storeCurrencySymbol.$total; 
        //     }
        // }
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('ah_supermax_pos_report');
                $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
                $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');

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
                $cashTotal = $cardTotal = $customTotal = $offlineTotal = $floatamount = $walletTotal = $total = $emiTotal = $bankDepositTotal = $payLaterTotal = 0;
                if (isset($dataSource['data']['items'])) {
                    $fieldName = $this->getData('name');
                    foreach ($dataSource['data']['items'] as &$item) {
                        $posRegisterId = $item['pos_register_id'];
                        if ($payment_method == 'cash') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_cash_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $cashTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $cashTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $cashTotal = (float) $getRegisterTotal['total_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $cashTotal;
                        } elseif ($payment_method == 'card') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_card_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $cardTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $cardTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $cardTotal = (float) $getRegisterTotal['total_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $cardTotal;
                        } elseif ($payment_method == 'upi') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_custom_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $customTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $customTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $customTotal = (float) $getRegisterTotal['total_total'];
                            }
                           $item[$fieldName] = $storeCurrencySymbol . $customTotal;
                        } elseif ($payment_method == 'offline') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_offline_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $offlineTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $offlineTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $offlineTotal = (float) $getRegisterTotal['total_total'];
                            }
                           $item[$fieldName] = $storeCurrencySymbol . $offlineTotal;
                        } elseif ($payment_method == 'wallet') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ")->fetch();
                            
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_wallet_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $walletTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $walletTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $walletTotal = (float) $getRegisterTotal['total_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $walletTotal;
                        } elseif ($payment_method == 'emi') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_emi_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $emiTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $emiTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $emiTotal = (float) $getRegisterTotal['total_total'];
                            }
                           $item[$fieldName] = $storeCurrencySymbol . $emiTotal;
                        } elseif ($payment_method == 'bank_deposit') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_bank_deposit_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $bankDepositTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $bankDepositTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $bankDepositTotal = (float) $getRegisterTotal['total_total'];
                            }
                           $item[$fieldName] = $storeCurrencySymbol . $bankDepositTotal;
                        } elseif ($payment_method == 'pay_later') {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'pay_later' ")->fetch();
                            $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, head_cashier_pay_later_total as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();
                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $payLaterTotal = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $payLaterTotal = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $payLaterTotal = (float) $getRegisterTotal['total_total'];
                            }
                           $item[$fieldName] = $storeCurrencySymbol . $payLaterTotal;
                        } else {
                            $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();                          $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, SUM(head_cashier_cash_total + head_cashier_card_total + head_cashier_custom_total + head_cashier_offline_total + head_cashier_wallet_total + head_cashier_emi_total + head_cashier_bank_deposit_total + head_cashier_pay_later_total) as total_total FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'")->fetch();

                            if (!empty($getHeadRegisterTotal)) {
                                if ($getHeadRegisterTotal['reconciliation_status'] == 1) {
                                    $total = (float) $getHeadRegisterTotal['total_total'];
                                } else {
                                    $total = (float) $getRegisterTotal['total_total'];
                                }
                            } else {
                                $total = (float) $getRegisterTotal['total_total'];
                            }
                            $item[$fieldName] = $storeCurrencySymbol . $total;
                        }

                    }
                }

            }

        }
        return $dataSource;

    }
}
