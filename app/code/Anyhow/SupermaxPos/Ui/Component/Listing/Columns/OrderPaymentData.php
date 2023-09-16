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

class OrderPaymentData extends Column
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
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $paymentDetail = $resource->getTableName('ah_supermax_pos_payment_detail');        
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        if(isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                $paymentMethods = "";                
                $separater = " | ";    
                $orderId = $item['pos_id_order'];
                $sql = "SELECT pos_order_id, payment_code, amount FROM $paymentDetail WHERE pos_order_id = $orderId" ;
                $paymentData = $connection->query($sql)->fetchAll();
                                            
                if(!empty($paymentData)){
                    foreach($paymentData as $paymentData) {
                        if($paymentMethods != "") {
                            $paymentMethods .=  $separater;
                        }
                        $pos_order_id = $paymentData['pos_order_id'];
                        $payment_code = $paymentData['payment_code'];
                        $amount = $paymentData['amount'];
                        if($payment_code == 'CARD'){
                            $paymentMethods .= 'Pinelab Card (CC+DC) Payment' . " (Amount: " . $storeCurrencySymbol . $amount . ")" ;
                        } else if($payment_code == 'PINELABS-UPI'){
                            $paymentMethods .= 'Pinelabs UPI/QR Payment' . " (Amount: " . $storeCurrencySymbol . $amount . ")" ;
                        } else if($payment_code == 'UPI'){
                            $paymentMethods .= 'Ezetap UPI/QR Payment' . " (Amount: " . $storeCurrencySymbol . $amount . ")" ;
                        } else {
                            $paymentMethods .= $payment_code . " (Amount: " . $storeCurrencySymbol . $amount. ")";
                        } 
                    }
                }
                $item[$fieldName] = $paymentMethods;
            }
        }
        return $dataSource;
    }
}