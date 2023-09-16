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

class TotalDeclared extends Column
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
           
        if(isset($dataSource['data']['items'])) {
            $storeCurrencySymbol = '';
            $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

            if(!empty($storeCurrencyCode)) {
                $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
            }

            $fieldName = $this->getData('name');

            foreach($dataSource['data']['items'] as & $item) {                
                $total = 0.00;
                $posRegisterId = $item['pos_register_id'];
                $getRegisterTotal = $connection->query("SELECT SUM(total) as total_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "'")->fetch();
                $getHeadRegisterTotal = $connection->query("SELECT reconciliation_status, SUM(head_cashier_cash_total + head_cashier_card_total + head_cashier_custom_total + head_cashier_offline_total + head_cashier_wallet_total + head_cashier_emi_total + head_cashier_bank_deposit_total + head_cashier_pay_later_total) as total_total FROM $supermaxRegister WHERE pos_register_id = '" .$posRegisterId. "'")->fetch();
               
                if(!empty($getHeadRegisterTotal)) {
                    if($getHeadRegisterTotal['reconciliation_status'] == 1) { 
                        $total = (float)$getHeadRegisterTotal['total_total'];
                    } else {                
                        $total = (float)$getRegisterTotal['total_total'];
                    }
                } else {
                    $total = (float)$getRegisterTotal['total_total'];
                }
                
                $item[$fieldName] = $storeCurrencySymbol.$total; 
            }
        }
        return $dataSource;
    }
}