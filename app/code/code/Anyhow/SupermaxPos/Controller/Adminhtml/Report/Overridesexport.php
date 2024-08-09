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


class Overridesexport extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;

    protected $_locationFactory; 

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOverrides\Collection $registerData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
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
        $this->timezone = $timezone;
    }

    public function execute()
    {   
        $storeCurrencySymbol = '';
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();

        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/overrides-report-import-' .$name. '.csv';
        $this->directory->create('export');

        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();

        $columns = ['Date','Order Id', 'Store', 'Cashier', 'Approver', 'Customer', 'Customer Group', 'Offer Id', 'Product Name', 'Override Type', 'Price Change Reason', 'Product Original Price ('.$storeCurrencyCode.')', 'Discount', 'Discount Type', 'Product Overrided/Discounted Price ('.$storeCurrencyCode.')', 'Quantity', 'Overrided Row Total ('.$storeCurrencyCode.')', 'Delivery Original Price', 'Delivery Overrided Price', 'Payment Method', 'Order Status'];

        foreach ($columns as $column) 
        {
            $header[] = $column;
        }

        $stream->writeCsv($header);
        $register = $this->_registerData;
        $register_collection = $register->getData();
        $permisions = array(
            'cart_product_price' => __('Cart Product Price'),
            'cart_product_discount' => __('Cart Product Discount'),
            // 'cart_product_quantity' => __('Cart Product Quantity'),
            // 'cart_customer' => __('Cart Customer'),
            'cart_discount' => __('Cart Discount'),
            'cart_coupon' => __('Cart Coupon'),
            // 'dashboard' => __('Dashboard'),
            // 'register_and_cash_mgmt' => __('Register & Cash Management'),
            'mop_offline' => __('MOP Offline'),
            'delivery_charge' => __("Delivery Charge"),
            'on_invoice_promotion' => __("On Invoice Promotion")
        ); 

        $discountType = array(
            'fixed' => __('Fixed'),
            'percent' => __('Percent')
        ); 
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $salesOrderItemTable = $resource->getTableName('sales_order_item');
        $paymentDetail = $resource->getTableName('ah_supermax_pos_payment_detail');
        foreach($register_collection as $item) {
            $quantity = 0;
            $overridedRowTotal = $item['product_overrided_price'] * $quantity;
            if(isset($item['permission_type']) && (($item['permission_type'] == 'mop_offline') ||  ($item['permission_type'] == "MOP Offline") || ($item['permission_type'] == 'Delivery Charge') || ($item['permission_type'] == 'delivery_charge') || ($item['permission_type'] == 'On Invoice Promotion') || ($item['permission_type'] == 'on_invoice_promotion'))) {
                $quantity = "N/A";
                $item['overrided_price'] = "N/A";
                $overridedRowTotal = "N/A";
                $item['original_price'] = "N/A";
            } elseif(isset($item['sku']) && $item['sku']) {                    
                $quantityData = $connection->query("SELECT qty_ordered FROM $salesOrderItemTable WHERE sku = '" .  $item['sku'] ."' AND order_id = '". $item['order_id'] . "'")->fetch();                                     
                $quantity = isset($quantityData['qty_ordered']) ? (int)$quantityData['qty_ordered'] : "0"; 
                $overridedRowTotal = $item['product_overrided_price'] * $quantity;                
            }
            
            $itemData = [];
            $itemData[] = $this->timezone->date(new \DateTime($item['date_added']))->format('Y-m-d h:i:s A');
            $itemData[] = "#" . $item['increment_id'];
            $itemData[] = $item['outlet_name'];
            $itemData[] = $item['user_name'];
            $itemData[] = $item['approver_name'];
            $itemData[] = $item['customer_name'];
            $itemData[] = $item['customer_group_code'];
            $itemData[] = "#" . $item['sku'];
            // $itemData[] = $item['sku'];
            $itemData[] = $item['name'];
            $itemData[] = array_key_exists($item['permission_type'], $permisions) ? $permisions[$item['permission_type']]: 'N/A';
            $itemData[] = !empty($item['price_reduction_title']) ? $item['price_reduction_title'] : 'N/A';
            $itemData[] = html_entity_decode($item['original_price'], ENT_HTML5, 'utf-8');
            $itemData[] = ($item['discount'] != 0) ? $item['discount'] : 'N/A';
            $itemData[] = array_key_exists($item['discount_type'], $discountType) ? $discountType[$item['discount_type']]: 'N/A';
            $itemData[] = html_entity_decode($item['overrided_price'], ENT_HTML5, 'utf-8');
            $itemData[] = $quantity;
            $itemData[] = html_entity_decode($overridedRowTotal, ENT_HTML5, 'utf-8');
            $itemData[] = ($item['original_delivery_price'] != 0) ? $item['original_delivery_price'] : 'N/A';
            $itemData[] = ($item['overrided_delivery_price'] != 0) ? $item['overrided_delivery_price'] : 'N/A';
            $itemData[] = $this->getPaymentData($connection, $paymentDetail, $item['pos_id_order']);
            $itemData[] = $item['order_status'];
            $stream->writeCsv($itemData);
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;

        $csvfilename = 'overrides-report-import-'.$name.'.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }

    private function getPaymentData($connection, $paymentDetail, $orderId) {
        $paymentMethods = "";                
        $separater = " | ";    
        $orderPaymentData = $connection->query("SELECT pos_order_id, payment_code, amount FROM $paymentDetail WHERE pos_order_id = $orderId")->fetchAll();
                                    
        if(!empty($orderPaymentData)){
            foreach($orderPaymentData as $paymentData) {
                if($paymentMethods != "") {
                    $paymentMethods .=  $separater;
                }
                $payment_code = $paymentData['payment_code'];
                $amount = $paymentData['amount'];
                if($payment_code == 'CARD'){
                    $paymentMethods .= 'Pinelab Card (CC+DC) Payment' . " (Amount: " . $amount . ")" ;
                } else if($payment_code == 'PINELABS-UPI'){
                    $paymentMethods .= 'Pinelabs UPI/QR Payment' . " (Amount: " . $amount . ")" ;
                } else if($payment_code == 'UPI'){
                    $paymentMethods .= 'Ezetap UPI/QR Payment' . " (Amount: " . $amount . ")" ;
                } else {
                    $paymentMethods .= $payment_code . " (Amount: " . $amount. ")";
                } 
            }
        }
        return $paymentMethods;
    }
}