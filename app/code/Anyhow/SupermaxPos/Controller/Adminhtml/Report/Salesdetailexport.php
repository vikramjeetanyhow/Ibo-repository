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


class Salesdetailexport extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory; 

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesDetails\Collection $registerData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone

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
        $this->timezone = $timezone;

    }

    public function execute()
    {   
        $paymentMethods = '';
        $separater ='';
        $storeCurrencySymbol = '';
        $Total = 0;
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/sales-detail-report-import-' .$name. '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();

        $columns = ['Order ID','Date', 'Store', 'Cashier', 'Customer', 'Total ('.$storeCurrencyCode.')', 'Merchant', 'Payment Method ('.$storeCurrencyCode.') ', 'On Invoice Promotion Discount', 'Order Status','Order Comment'];
        
        $connection = $this->resource->getConnection();
        $ezetapPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $pinelabsPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_pinelabs');
        $orderStatusHistoryTable = $this->resource->getTableName('sales_order_status_history');
        $supermaxUserTable = $this->resource->getTableName('ah_supermax_pos_user');
        foreach ($columns as $column) 
        {
            $header[] = $column;
        }

        $stream->writeCsv($header);
        $register = $this->_registerData;
        $register_collection = $register->getData();
        foreach($register_collection as $item){
           // for cashier name
            $cashierName = '';
            $cashierId = $item['cashier'];
            
            $entity_id = $item['entity_id'];
            $orderComments = $connection->query("SELECT * FROM $orderStatusHistoryTable WHERE parent_id = $entity_id")->fetchAll();
            $userData = $connection->query("SELECT * FROM $supermaxUserTable WHERE pos_user_id = '" . (int)$cashierId. "'")->fetch();
            if(!empty($userData)) {
                $cashierName = $userData['firstname'].' '.$userData['lastname'];
            }
            $Total = 0;
            $paymentMethods ='';
            $paymentData = (array)json_decode($item['payment_data']);
            $separater = " | ";            
            $pos_id_order = $item['pos_id_order'];
            $paymentDetail = $this->resource->getTableName('ah_supermax_pos_payment_detail');
            $sql = "SELECT pos_order_id,payment_code ,amount FROM $paymentDetail  Where pos_order_id = $pos_id_order" ;
            $paymentData = $connection->query($sql)->fetchAll();                
            if(!empty($paymentData)){
                foreach($paymentData as $paymentData){ 
                    if($paymentMethods != "") {
                        $paymentMethods .=  $separater;
                    }
                    $pos_order_id = $paymentData['pos_order_id'];
                    $payment_code = $paymentData['payment_code'];
                    $amount = $paymentData['amount'];
                    if($payment_code == 'CASH' || $payment_code == 'OFFLINE') {
                        foreach($orderComments as $comment) {
                            if($comment['parent_id'] ==  $entity_id) {
                                $comment = $comment['comment'];
                            }
                        }
                    } else {
                        $comment = '';
                    }               
                    
                    if($payment_code == 'CARD'){
                        $paymentMethods .= 'Pinelab Card (CC+DC) Payment' . " (Amount: " .  $amount . ")" ;
                    } else{                        
                        $paymentMethods .= $payment_code . " (Amount: " . $amount. ")";
                    }
                    $Total +=  $amount;

                }
            }            
            $orderId = $item['ah_pos_order_id'];
            $ezetapPaymentData = $connection->query("SELECT * FROM $ezetapPaymentTable WHERE order_id='" . $orderId . "'")->fetch();
            $pinelabsPaymentData = $connection->query("SELECT * FROM $pinelabsPaymentTable WHERE order_id='" . $orderId . "'")->fetch();
            $merchant = '-';
            if(!empty($ezetapPaymentData)) {
                $merchant = "Ezetap";
            } else if(!empty($pinelabsPaymentData)) {
                $merchant = "Pine-Labs";
            }
            $onInvoiceDiscount = "false";
            $additionalDetailsJson = $item['additional_data'];    
            if(!empty($additionalDetailsJson)) {
                $orderAdditionalData = (array)json_decode($additionalDetailsJson);
                if(($orderAdditionalData['on_invoice_promotion']) && $orderAdditionalData['on_invoice_promotion']) {
                    $onInvoiceDiscount = "true";
                }
            }  

            $itemData = [];
            $itemData[] = $item['increment_id'];
            $itemData[] =  $this->timezone->date(new \DateTime(
                $item['created_at']))->format('Y-m-d h:i:s A');
            $itemData[] = $item['store'];
            $itemData[] = $cashierName;
            $itemData[] = $item['customer_name'];
            $itemData[] = $Total;
            $itemData[] = $merchant;
            $itemData[] = $paymentMethods;
            $itemData[] = $onInvoiceDiscount;
            $itemData[] = $item['status'];
            $itemData[] =  $comment;

            $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'sales-detail-report-import-'.$name.'.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }
}