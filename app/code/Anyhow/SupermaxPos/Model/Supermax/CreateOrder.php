<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class CreateOrder implements \Anyhow\SupermaxPos\Api\Supermax\CreateOrderInterface
{

    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Api\OrderPaymentRepositoryInterface $orderPaymentRepository,
        \Magento\Sales\Model\Order\AddressRepository $orderAddressRepository,
        \Magento\Sales\Model\Order\ItemFactory $orderItemFactory,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        // \Magento\InventoryCatalogAdminUi\Model\GetSourceItemsDataBySku $sourceDataBySku,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,

        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,

        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Framework\Locale\CurrencyInterface $currency1,
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockItemRepository,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ){
        $this->productRepository = $productRepository;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->customer = $customer;
        $this->resource = $resourceConnection;
        $this->priceCurrencyInterface = $priceCurrencyInterface;
        $this->stockRegistry = $stockRegistry;
        // $this->sourceDataBySku = $sourceDataBySku;
        $this->supermaxSession = $supermaxSession;
        $this->remoteAddress = $remoteAddress;
        $this->orderSender = $orderSender;

        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;

        $this->_convertOrder = $convertOrder;
        $this->_shipmentNotifier = $shipmentNotifier;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->currency = $currency1;
        $this->setup = $setup;
        $this->stockItemRepository = $stockItemRepository;
        $this->objectManager = $objectManager;
        $this->eventManager = $eventManager;
    }

   /**
     * GET API
     * @api
     * 
     * @return string
     */
    public function createOrder()
    {
        $result = array();
        $error = false;

        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $orderData = array();
                $params = $this->helper->getParams();
                $kotId = null;
                if(isset($params['quote_id']) && !empty($params['quote_id'])) {
                    $quoteOrder = $this->checkQuoteOrder($params['quote_id']);
                    if(!empty($quoteOrder)) {
                            $barcodeImageUrl = $this->_storeManager->getStore()->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'/supermax/barcode/'. $quoteOrder['barcode'] . ".jpg";
                            $result = array(
                                'order_id' => (int)$quoteOrder['entity_id'],
                                'order_increment_id' => $quoteOrder['increment_id'],
                                'barcode' => html_entity_decode($quoteOrder['barcode']),
                                'barcode_url' => html_entity_decode($barcodeImageUrl),
                                'error_info' => array()
                            );
                    } else {
                        if(!empty($params)){
                            if(isset($params['kot_id'])){
                                $kotId = $params['kot_id'];
                            }
                            
                            $orderData = [
                                'comment' => $params['comment'],
                                'edc_request_id' => isset($params['edc_request_id']) ? $params['edc_request_id'] : "",
                                'currency_code' => $params['currency_code'],
                                'customer_id' => $params['customer_id'],
                                'payment_address' => $params['payment_address'],
                                'shipping_address' => $params['shipping_address'],
                                'payment_method' => $params['payment_method'],
                                'shipping_charges' => isset($params['shipping_charges']) ? $params['shipping_charges'] : 0,
                                'overrides' => isset($params['overrides']) ? $params['overrides'] : array(),
                                'quote_id' => isset($params['quote_id']) ? $params['quote_id'] : '',
                                'reserved_order_id' => isset($params['reserved_order_id']) ? $params['reserved_order_id'] : '',
                                "store_fulfilment_mode" => isset($params['store_fulfilment_mode']) ? $params['store_fulfilment_mode'] : '',
                                "customer_token" => isset($params['customer_token']) ? $params['customer_token'] : '',
                                "order_totals" => isset($params['order_totals']) ? $params['order_totals'] : array(),
                                "base_order_grand_total" => isset($params['base_order_grand_total']) ? $params['base_order_grand_total'] : 0,
                                "order_grand_total" => isset($params['order_grand_total']) ? $params['order_grand_total'] : 0,
                                "payment_data" => isset($params['payment_data']) ? $params['payment_data'] : array(),
                                "promise_options" => isset($params['promise_options']) ? $params['promise_options'] : array(),
                                "delivery_groups" => isset($params['delivery_groups']) ? $params['delivery_groups'] : array(),
                                "promise_id" => isset($params['promise_id']) ? $params['promise_id'] : "",
                                "promise_created_at" => isset($params['promise_created_at']) ? $params['promise_created_at'] : "",
                                "promise_expires_at" => isset($params['promise_expires_at']) ? $params['promise_expires_at'] : "",
                                "order_payment_intent_id" => isset($params['order_payment_intent_id']) ? $params['order_payment_intent_id'] : "",
                                "products" => isset($params['products']) ? $params['products'] : array(),
                                "pos_terminal_id" => isset($params['pos_terminal_id']) ? $params['pos_terminal_id'] : 0,
                                "payment_device_mode" => isset($params['payment_device_mode']) ? $params['payment_device_mode'] : 0,
                                // "sales_associate1" => isset($params['sales_associate1']) ? $params['sales_associate1'] : 0,
                                // "sales_associate2" => isset($params['sales_associate2']) ? $params['sales_associate2'] : 0,
                                "sales_associate" => isset($params['sales_associate']) ? $params['sales_associate'] : "",
                                "order_subtype" => $params['order_subtype'] ? $params['order_subtype'] : "POS",
                                "quotation_number" => $params['quotation_number'] ? $params['quotation_number'] : ""
                            ];
                        }
                        
                        $orderResult = $this->createOrderByQuote($orderData);
                        if(!empty($orderResult)){
                            if(!empty($orderResult['order'])){
                                $orderObj = $orderResult['order'];
                                $orderId = (int)$orderObj->getId();
                                $orderIncrementId = $orderObj->getIncrementId();
                                $userId = $orderResult['user_id'];
                                $outletId = $orderResult['outlet_id'];
                                $paymentCode = $orderResult['payment_code'];
                                $paymentMethod = $orderResult['payment_method'];
                                $customerId = $orderData['customer_id'];
                                $customerToken = $orderData['customer_token'];
                                $terminalId = $orderData['pos_terminal_id'];
                                $paymentDeviceMode = $orderData['payment_device_mode'];
                                // $salesAssociate1 = $orderData['sales_associate1'];
                                // $salesAssociate2 = $orderData['sales_associate2'];
                                $orderAdditionalData = array("order_subtype" => $orderData['order_subtype']);
                                if($orderData['quotation_number']) {
                                    $orderAdditionalData['quotation_number'] = $orderData['quotation_number'];
                                }
                                if($orderData['sales_associate']) {
                                    $orderAdditionalData['sales_associate'] = $orderData['sales_associate'];
                                }
                                // if(!empty($kotId)){
                                //     $this->processKotAfterOrder($orderId, $kotId);
                                // }

                                $this->orderSender->send($orderObj, true);
                                $overrides = array();
                                if($orderData['overrides']) {
                                    $overrides = array(
                                        'pos_user_id' => $userId,
                                        'pos_outlet_id' => $outletId,
                                        'customer_id' => $customerId,
                                        'order_id' => $orderId,
                                        'overrides' => $orderData['overrides']
                                    );
                                }
                                
                                if(!empty($customerId) && !empty($customerToken)) {
                                    $this->helper->revokeCustomerToken($customerId, $customerToken);
                                }

                                $barcode = "AHO";
                                $barcodeImageUrl = $this->_storeManager->getStore()->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
                                // if (isset($postData['txn_id']) && isset($postData['barcode']) && $postData['txn_id'] && $postData['barcode']) {
                                //     $barcode = $postData['barcode'];
                                // } else {
                                //     $barcode = "AHO" . (123456789 + (int)$orderId) . "IS";
                                // }
                                // $barcodeSize = 30;
                                // $generator = new \Picqer\Barcode\BarcodeGeneratorJPG();
                                // $mediaPath = BP.'/pub/media/supermax/barcode';

                                // if (!is_dir($mediaPath)) {
                                //     $create_dir = !is_dir(BP.'/pub/media/supermax') ? mkdir(BP.'/pub/media/supermax', 0777) : '';
                                //     mkdir($mediaPath, 0777);
                                //     chmod($mediaPath, 0777);
                                // }

                                // $filename = $barcode . ".jpg";

                                // $barcodeImageUrl = $this->_storeManager->getStore()->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'/supermax/barcode/'.$filename;
                                
                                // // save barcode image
                                // file_put_contents($mediaPath.'/'.$filename, $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 1, $barcodeSize));

                                // save data in supermax order table 
                                $deviceType = $this->helper->orderDevice();
                                $connection = $this->resource->getConnection();
                                $supermaxOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
                                $query = $connection->insert($supermaxOrderTable,
                                    ['order_id' => $orderId, 'pos_user_id'=> $userId, 'pos_outlet_id'=> $outletId, 'pos_terminal_id'=> $terminalId, 'payment_device_mode' => $paymentDeviceMode, 'barcode'=> $barcode, 'payment_code'=> $paymentCode, 'payment_method'=> $paymentMethod, 'payment_intent_id'=> $orderData['order_payment_intent_id'], 'payment_data' => json_encode($orderData['payment_data']), 'device_type' => $deviceType, 'sales_associate_1' => "", 'sales_associate_2' => "", 'additional_data' => json_encode($orderAdditionalData)]);
                                $posOrderId = (int)$connection->lastInsertId();    
                                $supermaxPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_detail');
                                
                                $registerData = array(
                                    'userId' => $userId,
                                    'paymentCode' => $paymentCode,
                                    'description' => ' Order ID: #' . $orderId,
                                    'total' => $orderResult['amount'],
                                    'storeCurrencyCode' => $orderResult['store_currency'],
                                    'storeBaseCurrencyCode' => $orderResult['store_base_currency'],
                                    'storeId' => $orderResult['store_id'], 
                                    'storeCurrencySymbol' => $orderResult['store_currency_symbol'], 
                                    'cashPaid' => isset($params['cash_paid']) ? $params['cash_paid'] : 0,  
                                    'cashReturned' => isset($params['cash_change']) ? $params['cash_change'] : 0,
                                    'orderId' => $orderId,
                                    'paymentIntentId' => $orderData['edc_request_id']
                                );
                                
                                if(!empty($orderData['payment_data'])) {
                                    foreach ($orderData['payment_data'] as $value) {
                                        $registerData['paymentCode'] = $value['payment_code'];
                                        $registerData['total'] = $value['amount'];
                                        $registerData['paymentIntentId'] = $value['payment_intent_id'];
                                        $registerData['cashPaid'] = isset($value['cash_paid']) ? $value['cash_paid'] : 0;
                                        $registerData['cashReturned'] = isset($value['cash_change']) ? $value['cash_change'] : 0;
                                        $payment_method = $value['payment_method'];              
                                        $query2 = $connection->insert($supermaxPaymentTable, 
                                            ['pos_order_id'=> $posOrderId, 'payment_method'=> $payment_method, 'payment_code'=> $registerData['paymentCode'], 'amount'=> $registerData['total'],'payment_intent_id'=> $registerData['paymentIntentId'], 'amount_formatted' => $registerData['total'], 'cash_paid'=> $registerData['cashPaid'], 'cash_change'=> $registerData['cashReturned']]
                                        );
                                        $this->updateRegisterData($registerData);
                                    }
                                } else {
                                    $this->updateRegisterData($registerData);
                                }

                                if(!empty($overrides)) {
                                    $this->updateOverrides($overrides);
                                }
                            } else {
                                $orderId = null;
                                $orderIncrementId = null;
                                $barcode = '';
                                $barcodeImageUrl = '';
                                $error = true; 
                            }
                            
                            $result = array(
                                'order_id' => (int)$orderId,
                                'order_increment_id' => $orderIncrementId,
                                'barcode' => html_entity_decode($barcode),
                                'barcode_url' => html_entity_decode($barcodeImageUrl),
                            );
                                
                        } else {
                            $error= true;
                        }
                    }
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $this->helper->addDebuggingLogData("---- Order Debugger Catch Error : " . $e->getMessage());
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    // To get storeId, outletEmail and outletSourceCode.
    public function joinUserData($connection, $userId) {
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'pos_outlet_id', 'store_view_id']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['email', 'source_code', 'inventory_node']
        )->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    // To get product quantity.
    public function getSourceQuantity($outletSourceCode, $productSku, $productId) {
        $productQty = null;
        $installer = $this->setup;
        // IF MSI
        if($installer->tableExists('inventory_source')){
            // if MSI enabled and source is also created.
            $connection = $this->resource->getConnection();
            $sourceTableName = $this->resource->getTableName("inventory_source");
            $sourceData = $connection->query("SELECT * FROM $sourceTableName")->fetchAll();
            if(!empty($sourceData)){
                $sourceDataBySku = $this->objectManager->create("Magento\InventoryCatalogAdminUi\Model\GetSourceItemsDataBySku");
                $productAllQtys = $sourceDataBySku->execute($productSku);
            
                if(!empty($productAllQtys)) {
                    foreach($productAllQtys as $productAllQty) {
                        if($productAllQty['source_code'] == $outletSourceCode) {
                            $productQty = $productAllQty['quantity'];
                        }
                    }
                }
            } else {
                $catalogInventoryData = $this->stockItemRepository->getStockItem($productId);
                if(!empty($catalogInventoryData)){
                    $productQty = $catalogInventoryData['qty'];
                }
            }
        } else {
            $catalogInventoryData = $this->stockItemRepository->getStockItem($productId);
            if(!empty($catalogInventoryData)){
                $productQty = $catalogInventoryData['qty'];
            }
        }

        return $productQty;
    }  

    // To update product stock by source code
    public function setSourceQuantity($outletSourceCode, $productSku, $updatedStockQty, $status) {
        $connection = $this->resource->getConnection();
        $inventorySourceItemTable = $this->resource->getTableName('inventory_source_item');
        $connection->query("UPDATE $inventorySourceItemTable SET quantity = $updatedStockQty, status = $status WHERE source_code = '$outletSourceCode' AND sku = '$productSku'");
    }  

    // Create invoice for order
    public function createInvoice($orderId) {
        $order = $this->_orderRepository->get($orderId);
        // if($order->canInvoice()) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setTotalPaid($order->getGrandTotal());
            $invoice->setBaseTotalPaid($order->getBaseGrandTotal());
            $invoice->setSubtotal($order->getSubtotal());
            $invoice->setBaseSubtotal($order->getBaseSubtotal());
            $invoice->setGrandTotal($order->getGrandTotal());
            $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->_transaction->addObject(
                $invoice
            )->addObject(
                $order
            );
            $transactionSave->save();
        // }
    }

    // Create shipment for order 
    public function createShipment($orderId, $outletSourceCode) {
        $order = $this->_orderRepository->get($orderId);
        $orderShipment = $this->_convertOrder->toShipment($order);
        $installer = $this->setup;
        foreach($order->getAllItems() AS $orderItem) {
            // Check virtual item and item Quantity
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual() || $orderItem->getProductType() == 'bundle' || !$orderItem->getId()) {
                continue;
            }
            $qty = $orderItem->getQtyToShip();
            if(!empty($orderItem->getProductOptions())){
                $optionData = $orderItem->getProductOptions();
                $sku = isset($optionData['sku']) ? $optionData['sku']: $orderItem->getSku();
            } else {
                $sku = $orderItem->getSku();
            }
            $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qty);
            $orderShipment->addItem($shipmentItem);
            $manageStockFlag = (bool)$this->getProductManageStockStatus($sku); 
            // if manage stock is set only then item qty decreased 
            if($manageStockFlag){
                // IF NOT MSI
                if(!$installer->tableExists('inventory_source')){
                    // To deduct qty for single source
                    $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                    $updateQty = $stockItem->getQty() - $qty;
                    $stockItem->setQty($updateQty);
                    $stockItem->setIsInStock((bool)$updateQty);
                    $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
                }
            }
        }
 
        $orderShipment->register();

        // if manage stock is set only then item qty decreased 
        if($manageStockFlag){
            // IF MSI
            if($installer->tableExists('inventory_source')){
                // set source 
                $orderShipment->getExtensionAttributes()->setSourceCode($outletSourceCode);
            }
        }
        $orderShipment->save();
        // Send Shipment Email
        $this->_shipmentNotifier->notify($orderShipment);
        

    }

    public function saveTaxData($data = array(), $order) {
        $connection = $this->resource->getConnection();
        $orderTaxTable = $this->resource->getTableName('sales_order_tax');
        $connection->insert($orderTaxTable,
            ['order_id'=> $data['order_id'], 'code'=> $data['title'], 'title'=> $data['title'], 'percent'=> $data['percent'],
            'amount'=> $data['amount'], 'priority'=> $data['priority'], 'position'=> $data['position'], 'base_amount'=> $data['base_amount'], 'process'=> 0, 'base_real_amount'=> $data['base_amount']]);  
    }

    public function saveTaxItemData($orderId, $storeCurrencyCode, $storeBaseCurrencyCode) {
        $connection = $this->resource->getConnection();
        $taxItemTable = $this->resource->getTableName('sales_order_tax_item');
        $orderTaxTable = $this->resource->getTableName('sales_order_tax');

        $order = $this->_orderRepository->get($orderId);
        $order_items = $order->getAllItems();
        $order_id = $order->getId();

        $select = $connection->select()->from(
            ['ot' => $orderTaxTable],
            ['*']
        )->where("ot.order_id = $order_id");
        $results = $connection->fetchAll($select);

        foreach($results as $result){
            foreach($order_items as $item){
                $item_real_tax = $item->getQtyOrdered() * $item->getTaxAmount();
                $item_base_real_tax = (float)$this->helper->convert($item_real_tax, $storeCurrencyCode, $storeBaseCurrencyCode);
                $base_amount = (float)$this->helper->convert($item->getTaxAmount(), $storeCurrencyCode, $storeBaseCurrencyCode);

                $connection->insert($taxItemTable,
                    ['tax_id'=> $result['tax_id'], 'item_id'=> $item->getId(), 'tax_percent'=> $result['percent'], 'amount'=> $item->getTaxAmount(), 'base_amount'=> $base_amount, 'real_amount' => $item_real_tax, 'real_base_amount'=> $item_base_real_tax, 'taxable_item_type'=> 'product' ]); 
            }
        }
    }

    public function processKotAfterOrder($orderId, $kotId) {

        $connection = $this->resource->getConnection();
        $kotToKotTable = $this->resource->getTableName('ah_supermax_pos_restro_kot_to_kot');
        $kotTable = $this->resource->getTableName('ah_supermax_pos_restro_kot');

        $parentKotIds = $connection->query("SELECT parent_kot_id FROM $kotToKotTable WHERE pos_kot_id = '" . (int)$kotId . "'")->fetchAll();

		if (!empty($parentKotIds)) {

            foreach($parentKotIds as $parentKotId){
                $getAllRelatedKots = $connection->query("SELECT pos_kot_id FROM $kotToKotTable WHERE parent_kot_id = '" . (int)$parentKotId['parent_kot_id'] . "'")->fetchAll();

                if(!empty($getAllRelatedKots)){
                    foreach($getAllRelatedKots as $key => $kot) {
                        $connection->query("UPDATE $kotTable SET pos_order_id = '" . (int)$orderId . "', date_modified = NOW() WHERE pos_kot_id = '" . (int)$kot['pos_kot_id'] . "'");		
                    }
                }
                
            }
		}

    }

    public function getOrderState($orderStatus) {
        $orderState = 'new';
        if(!empty($orderStatus)){
            $connection = $this->resource->getConnection();
            $orderStateTable = $this->resource->getTableName("sales_order_status_state");
            $orderStates = $connection->query("SELECT * FROM $orderStateTable WHERE status = '$orderStatus'")->fetchAll();
            if(!empty($orderStates)){
                foreach($orderStates as $state){
                    $orderState = $state['state'];
                }
            }
        }
        return $orderState;
    }

    public function getProductManageStockStatus($productSku) {
        $stockItem = $this->stockRegistry->getStockItemBySku($productSku);
        return $stockItem->getManageStock();
    }

    public function getProductOptions($productId, $optionId) {
        $productOptionsResult = array();
        $connection = $this->resource->getConnection();
        $optionQuery = $connection->select()->from(
            ['cpo' => $this->resource->getTableName('catalog_product_option')],
            ['option_id', 'type', 'option_sku'=>'sku']
        )->joinLeft(
            ['cpot' => $this->resource->getTableName('catalog_product_option_title')],
            "cpot.option_id = cpo.option_id",
            ['option_title'=>'title']
        )->where("cpo.product_id = $productId")->where("cpo.option_id = $optionId")->group("cpo.option_id");

        $optionData = $connection->query($optionQuery)->fetch();
        if(!empty($optionData)){
            $productOptionsResult = array(
                "option_id" => (int)$optionId,
                'option_type' => html_entity_decode($optionData['type']),
                'option_sku' => html_entity_decode($optionData['option_sku']),
                'option_title' => html_entity_decode($optionData['option_title']),
                'option_type_data' => $this->getCustomOptionTypes($optionId)
            );
        }
        return $productOptionsResult;
    }

    public function getCustomOptionTypes($optionId) {
        $optionTypeResult = array();
        $connection = $this->resource->getConnection();
        $optionTypeQuery = $connection->select()->from(
            ['cpotv' => $this->resource->getTableName('catalog_product_option_type_value')],
            ['option_type_id','option_type_sku'=>'sku',]
        )->joinLeft(
            ['cpott' => $this->resource->getTableName('catalog_product_option_type_title')],
            "cpott.option_type_id = cpotv.option_type_id",
            ['option_type_title' => 'title']
        )->where("cpotv.option_id = $optionId");

        $optionTypeCollection = $connection->query($optionTypeQuery)->fetchAll();
        if(!empty($optionTypeCollection)){
            foreach($optionTypeCollection as $optionData){
                $optionTypeResult[] = array(
                    'option_type_id' => (int)$optionData['option_type_id'],
                    'option_type_sku' => html_entity_decode($optionData['option_type_sku']),
                    'option_type_title' => html_entity_decode($optionData['option_type_title']),
                );
            }
        }
        return $optionTypeResult;
    }

    private function updateEzetapPaymentInfo($requestId, $orderId) {
        $connection = $this->resource->getConnection();
        $ezetapPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $pinelabsPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_pinelabs');
        $paymentData = $connection->query("SELECT * FROM $ezetapPaymentTable WHERE request_id='" . $requestId . "'")->fetch();
        if(!empty($paymentData)) {
            $connection->query("UPDATE $ezetapPaymentTable SET order_id = '" . (int)$orderId . "', date_modified = NOW() WHERE request_id = '" . $requestId . "'");
        } else {
            $paymentData = $connection->query("SELECT * FROM $pinelabsPaymentTable WHERE request_id='" . $requestId . "'")->fetch();
            if(!empty($paymentData)) {
                $connection->query("UPDATE $pinelabsPaymentTable SET order_id = '" . (int)$orderId . "', date_modified = NOW() WHERE request_id = '" . $requestId . "'");
            }
        }
    }

    public function updateOverrides($data) {
        if (array_search(true, array_column($data['overrides'], 'applied')) !== FALSE) {
            $connection = $this->resource->getConnection();
            $customerTable = $this->resource->getTableName('customer_entity');
            $overrideTable = $this->resource->getTableName('ah_supermax_pos_user_overrides');
            $overrideDetailTable = $this->resource->getTableName('ah_supermax_pos_user_override_details');

            $customerData = $connection->query("SELECT * FROM $customerTable WHERE entity_id = '" . (int)$data['customer_id'] . "'")->fetch();

            $customerGroupId = 0;
            if(!empty($customerData)){
                $customerGroupId = $customerData['group_id'];
            }

            $connection->query("INSERT INTO $overrideTable SET pos_user_id = '" . (int)$data['pos_user_id'] . "', pos_outlet_id = '" . (int)$data['pos_outlet_id'] . "', customer_id = '" . (int)$data['customer_id'] . "', customer_group_id = '" . (int)$customerGroupId . "', order_id = '" . (int)$data['order_id'] . "', date_added = NOW()");

            $overrideId = $connection->lastInsertId();

            foreach($data['overrides'] as $override) {
                if($override['applied']) {
                    $productName = str_replace(["'", '"', "/", "\\", ":", "|", "?", "*", "~"], "", $override['product_name']);
                    $connection->query("INSERT INTO $overrideDetailTable SET parent_pos_user_override_id = '" . (int)$overrideId . "', approver_id = '" . (int)$override['approver_id'] . "', product_id = '" . (int)$override['product_id'] . "', sku = '" . $override['product_sku'] . "', name = '" . $productName . "', pos_price_reduction_id = '" . (int)$override['price_reason_id'] . "', price_reduction_title = '" . $override['price_reason'] . "', original_price = '" . $override['product_price'] . "', overrided_price = '" . $override['product_overrided_price'] . "', discount = '" . $override['discount'] . "', discount_type = '" . $override['discount_type'] . "', permission_type = '" . $override['permission_type']. "', overrided_delivery_price = '" . $override['delivery_overrided_price'] . "', original_delivery_price = '" . $override['delivery_original_price'] ."'");
                }
            }
        }
    }

    private function updateOrderOrigin($orderId, $store_fulfilment_mode) {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');
        $connection->query("UPDATE $orderTable SET order_channel = 'STORE', order_channel_info = 'POS' WHERE entity_id = $orderId");
        $connection->query("UPDATE $orderItemTable SET order_fulfilment_type = '$store_fulfilment_mode' WHERE order_id = $orderId");
    }

    private function checkQuoteOrder($quoteId) {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $orderData = $connection->query("SELECT o.entity_id, o.increment_id, po.barcode FROM $orderTable as o LEFT JOIN $posOrderTable as po ON(o.entity_id = po.order_id)  WHERE quote_id = '" . (int)$quoteId . "'")->fetch();
        $this->helper->deactivateQuote($quoteId);
        return $orderData;
    }

    private function getEzetapPaymentData($orderId) {
        $ezetapPaymentData = array();
        $connection = $this->resource->getConnection();
        $ezetapPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $paymentData = $connection->query("SELECT * FROM $ezetapPaymentTable WHERE order_id='" . $orderId . "'")->fetch();
        if(!empty($paymentData)) {
            $paymentStausData = (array)json_decode($paymentData['status_check_info']);
            if(isset($paymentStausData['paymentMode']) && !empty($paymentStausData['paymentMode'])) {
                switch($paymentStausData['paymentMode']) {
                    case 'CARD':
                        $ezetapPaymentData['payment_code'] = ($paymentStausData['paymentCardType'] == "DEBIT") ? "DEBIT-CARD" : "CREDIT-CARD"; 
                        $ezetapPaymentData['payment_method'] = ($paymentStausData['paymentCardType'] == "DEBIT") ? "DEBIT-CARD" : "CREDIT-CARD"; 
                        break;
                    case 'UPI':
                        $ezetapPaymentData['payment_code'] = "UPI"; 
                        $ezetapPaymentData['payment_method'] = "UPI"; 
                        break;
                    default:
                        $ezetapPaymentData['payment_code'] = "NET-BANKING"; 
                        $ezetapPaymentData['payment_method'] = "NET-BANKING"; 
                }
            }
        }

        return $ezetapPaymentData;
    }

    private function getQuoteData($quoteId) {
        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('quote');
        $quoteData = $connection->query("SELECT * FROM $quoteTable WHERE entity_id='" . $quoteId . "'")->fetch();
        return $quoteData;
    }

    private function getQuoteItemData($quoteId, $productId) {
        $connection = $this->resource->getConnection();
        $quoteItemTable = $this->resource->getTableName('quote_item');
        $quoteItemData = $connection->query("SELECT * FROM $quoteItemTable WHERE quote_id='" . $quoteId . "' AND product_id='" . $productId . "'")->fetch();
        return $quoteItemData;
    }

    private function updateRegisterData($data) {
        $userId = $data['userId']; 
        $paymentCode = $data['paymentCode'];
        $description = $data['description']; 
        $total = $data['total']; 
        $storeCurrencyCode = $data['storeCurrencyCode']; 
        $storeBaseCurrencyCode = $data['storeBaseCurrencyCode'];
        $storeId = $data['storeId']; 
        $storeCurrencySymbol = $data['storeCurrencySymbol']; 
        $cashPaid = $data['cashPaid'];  
        $cashReturned = $data['cashReturned'];
        $orderId = $data['orderId'];
        $paymentIntentId = $data['paymentIntentId'];

        switch ($paymentCode) {
            case 'CASH':
                $this->helper->addRegisterTransaction($userId, 'cash', 'Cash Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                break;
            case 'OFFLINE':
                $this->helper->addRegisterTransaction($userId, 'offline', 'Offline Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                break;
            case 'CREDIT-CARD':
                $this->helper->addRegisterTransaction($userId, 'card', 'Card Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'DEBIT-CARD':
                $this->helper->addRegisterTransaction($userId, 'card', 'Card Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'CARD':
                $this->helper->addRegisterTransaction($userId, 'card', 'Card Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'UPI':
                $this->helper->addRegisterTransaction($userId, 'upi', 'UPI Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'PINELABS-UPI':
                $this->helper->addRegisterTransaction($userId, 'upi', 'UPI Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'EMI':
                $this->helper->addRegisterTransaction($userId, 'emi', 'EMI Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'EZETAP-EMI':
                $this->helper->addRegisterTransaction($userId, 'emi', 'EMI Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'NET-BANKING':
                $this->helper->addRegisterTransaction($userId, 'net_banking', 'Net Banking Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                $this->updateEzetapPaymentInfo($paymentIntentId, $orderId);
            break;
            case 'PAY-ON-DELIVERY':
                $this->helper->addRegisterTransaction($userId, 'pod', 'Pay on Delivery Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
            break;
            case 'WALLET':
                $this->helper->addRegisterTransaction($userId, 'wallet', 'Wallet Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
            break;
            case 'BANK-DEPOSIT':
                $this->helper->addRegisterTransaction($userId, 'bank_deposit', 'Bank Deposit Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                break;
            case 'SARALOAN':
                $this->helper->addRegisterTransaction($userId, 'pay_later', 'Pay Later Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                break;
            case 'BHARATPE':
                $this->helper->addRegisterTransaction($userId, 'pay_later', 'Pay Later Payment', $description, $total, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned);
                break;    
        }
    }

    public function createOrderByQuote($orderData) {
        // $delivery = array();
        // $ip = $this->remoteAddress->getRemoteAddress();
        $store_fulfilment_mode = $orderData['store_fulfilment_mode'];
        $taxInclInItem = $this->helper->getConfig('tax/calculation/price_includes_tax');
        $taxInclInPromo = $this->helper->getConfig('tax/calculation/discount_tax');
        // $comment = $orderData['comment'];
        $storeId = $outletEmail = $outletId = $outletSourceCode = $nodeId = '';
        $connection= $this->resource->getConnection();
        $userId = $this->supermaxSession->getPosUserId();
        $userData = $this->joinUserData($connection, $userId);
        $user = $connection->query($userData)->fetch();

        if(!empty($user)) {
            $storeId = $user['store_view_id'];
            $outletId = $user['pos_outlet_id'];
            $outletEmail = $user['email'];
            $outletSourceCode = $user['source_code'];
            $nodeId = $user['inventory_node'];
        }
         
        $promiseOptions = array(
            "0" => array(
                "node_id" => $nodeId
            )
        );
        $currency = $orderData['currency_code'];
        $storeBaseCurrencyCode  = $this->_storeManager->getStore()->getBaseCurrencyCode(); 
        $storeCurrencyCode = $currency;
        if(!empty($storeCurrencyCode)) {
            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
        }

        // $order = $this->orderFactory->create()->setStoreId($storeId);
        // $order->setRemoteIp($ip);
        // $order->setOrderChannelInfo($store_fulfilment_mode);
        // $order->setOrderChannel('STORE');
        // $order->setTaxInclInPromo($taxInclInPromo);
        // $order->setTaxInclInItem($taxInclInItem);
        // $order->addCommentToStatusHistory($comment);
        
        // $order->setGlobalCurrencyCode($currency)
        //     ->setBaseCurrencyCode($storeBaseCurrencyCode)
        //     ->setStoreCurrencyCode($currency)
        //     ->setOrderCurrencyCode($currency)
        //     ->setPromiseOptions(json_encode($promiseOptions));

        $shippingCharges = (float)$orderData['shipping_charges'];
        $baseShippingCharges = (float)$this->helper->convert($shippingCharges, $storeCurrencyCode, $storeBaseCurrencyCode);
        // $order->setBaseShippingAmount($baseShippingCharges);
        // $order->setShippingAmount($shippingCharges);
        // $order->setShippingInclTax($shippingCharges);
        // $order->setBaseShippingInclTax($shippingCharges);

        // $orderPayment = $this->orderPaymentRepository->create();
        $paymentMethod = $orderData['payment_method']['code'];
        // if($paymentMethod == 'PAY-ON-DELIVERY') {
        //     $orderPayment->setMethod('cashondelivery');
        // } else {
        //     $orderPayment->setMethod('prepaid');
        // }
        // // $orderPayment->setMethod('pospayment');
        // $order->setPayment($orderPayment);

        if($paymentMethod == 'CASH') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_cash_payment_order_status', $storeId);
        } elseif($paymentMethod == 'PAY-ON-DELIVERY') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_pod_payment_order_status', $storeId);                   
        } elseif($paymentMethod == 'OFFLINE') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_offline_payment_order_status', $storeId);                   
        } elseif($paymentMethod == 'SPLIT') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_split_payment_order_status', $storeId);                   
        } elseif($paymentMethod == 'BANK-DEPOSIT') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_bank_deposite_payment_order_status', $storeId);                   
        } elseif($paymentMethod == 'PAY-LATER') {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_pay_later_payment_order_status', $storeId);                   
        } else {
            $paymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_online_payment_order_status', $storeId);    
        }

        $orderState = $this->getOrderState($paymentOrderStatus);
        // $order->setStatus($paymentOrderStatus); 
        // $order->setState($orderState);
        
        $billingAddress = $orderData['payment_address'];
        $orderBillingAddress = $this->orderAddressRepository->create();
        $orderBillingAddress->setStoreId($storeId)
                ->setAddressType(\Magento\Sales\Model\Order\Address::TYPE_BILLING)
                ->setFirstname($billingAddress['firstname'])
                ->setLastname($billingAddress['lastname'])
                ->setCompany($billingAddress['company'])
                ->setStreet($billingAddress['street'])
                ->setCity($billingAddress['city'])
                ->setPostcode($billingAddress['postcode'])
                ->setTelephone($billingAddress['telephone'])
                ->setCountryId($billingAddress['country_id'])
                ->setRegionId($billingAddress['region_id']);
        // $order->setBillingAddress($orderBillingAddress);

        $shippingAddress = $orderData['shipping_address'];
        $orderShippingAddress = $this->orderAddressRepository->create();
        $orderShippingAddress->setStoreId($storeId)
                ->setAddressType(\Magento\Sales\Model\Order\Address::TYPE_SHIPPING)
                ->setFirstname($shippingAddress['firstname'])
                ->setLastname($shippingAddress['lastname'])
                ->setCompany($shippingAddress['company'])
                ->setStreet($shippingAddress['street'])
                ->setCity($shippingAddress['city'])
                ->setPostcode($shippingAddress['postcode'])
                ->setTelephone($shippingAddress['telephone'])
                ->setCountryId($shippingAddress['country_id'])
                ->setRegionId($shippingAddress['region_id']);
        // $order->setShippingAddress($orderShippingAddress);

        // $customerId = $orderData['customer_id'];
        // $customerToken = $orderData['customer_token'];
        // if($customerId <= 0){
        //     $order->setCustomerEmail($outletEmail)
        //         ->setCustomerGroupId(0)
        //         ->setCustomerFirstname('Guest')
        //         ->setCustomerLastname('User')
        //         ->setCustomerIsGuest(1);
        // } else {
        //     $customerObj = $this->customer->load($customerId);
        //     $customerEmail = filter_var($customerObj->getEmail(), FILTER_VALIDATE_EMAIL) ? $customerObj->getEmail() : $this->helper->getCustomerCustomEmail($customerObj->getId());
        //     $order->setCustomerId($customerObj->getId())
        //         ->setCustomerGroupId($customerObj->getGroupId())
        //         ->setCustomerEmail($customerEmail)
        //         ->setCustomerFirstname($customerObj->getFirstname())
        //         ->setCustomerLastname($customerObj->getLastname())
        //         ->setCustomerTaxvat($customerObj->getTaxvat())
        //         ->setCustomerIsGuest(0);
        // }
        // $order->setShippingMethod('flatrate_flatrate')->setShippingDescription('Flat Rate - Fixed');
        // $quoteId = $orderData['quote_id'];
        // $quoteItems = $this->getQuoteItems($quoteId);
        // $subtotalInclTax = $baseSubtotalInclTax = $discountTaxCompensationAmount = $baseDiscountTaxCompensationAmount = 0;
        // if(!empty($quoteItems)) { 
        //     foreach($quoteItems as $quoteItem) {
        //         $product = $this->productRepository->getById($quoteItem['product_id']);
        //         $orderItem = $this->orderItemFactory->create();
        //         $quoteAdditionalData = (array)json_decode($quoteItem['additional_data']);
        //         $rowSubTotal = ($quoteItem['qty'] * $quoteItem['price']);
        //         $baseRowSubTotal = ($quoteItem['qty'] * $quoteItem['base_price']);
        //         $subtotalInclTax += ($quoteItem['qty'] * $quoteItem['price_incl_tax']);
        //         $baseSubtotalInclTax += ($quoteItem['qty'] * $quoteItem['base_price_incl_tax']);
        //         $discountTaxCompensationAmount += ($quoteItem['discount_tax_compensation_amount']);
        //         $baseDiscountTaxCompensationAmount += ($quoteItem['discount_tax_compensation_amount']);
        //         $orderItem->setStoreId($storeId)
        //             ->setQuoteItemId($quoteItem['item_id'])
        //             ->setProductId($product->getId())
        //             ->setProductType($product->getTypeId())
        //             ->setName($product->getName())
        //             ->setSku($product->getSku())
        //             ->setCost($product->getCost())
        //             ->setQuoteParentItemId($quoteItem['parent_item_id'])
        //             ->setQtyBackordered(NULL)
        //             ->setTotalQtyOrdered($quoteItem['qty'])
        //             ->setQtyOrdered($quoteItem['qty'])
        //             ->setPrice($quoteItem['price'])
        //             ->setBasePrice($quoteItem['base_price'])
        //             ->setPriceInclTax($quoteItem['price_incl_tax'])
        //             ->setBasePriceInclTax($quoteItem['base_price_incl_tax'])
        //             ->setTaxAmount($quoteItem['tax_amount'])
        //             ->setBaseTaxAmount($quoteItem['base_tax_amount'])
        //             ->setTaxPercent($quoteItem['tax_percent'])
        //             ->setRowTotal($rowSubTotal)
        //             ->setBaseRowTotal($baseRowSubTotal)
        //             ->setDiscountAmount($quoteItem['discount_amount'])
        //             ->setBaseDiscountAmount($quoteItem['base_discount_amount'])
        //             ->setBaseDiscountPercent($quoteItem['discount_percent'])
        //             ->setRowTotalInclTax($quoteItem['row_total_incl_tax'])
        //             ->setBaseRowTotalInclTax($quoteItem['base_row_total_incl_tax'])
        //             ->setOrderFulfilmentType($store_fulfilment_mode)
        //             ->setAppliedRuleIds($quoteItem['applied_rule_ids']);

        //             if(!empty($quoteAdditionalData)) {
        //                 $orderItem->setOriginalPrice($quoteAdditionalData['original_price']);
        //                 $orderItem->setBaseOriginalPrice($quoteAdditionalData['base_original_price']);
        //             } else {
        //                 $orderItem->setOriginalPrice($quoteItem['price_incl_tax']);
        //                 $orderItem->setBaseOriginalPrice($quoteItem['base_price_incl_tax']);
        //             }

        //             $delivery[] = array(
        //                 'delivery_group_lines' => [
        //                         array(
        //                         'item' => array("offer_id" => $product->getSku()),
        //                         'promise_line_id' => $product->getSku() 
        //                     )
        //                 ]
        //             );
        //         $order->addItem($orderItem);
        //     }
        // }

        // $quoteData = $this->getQuoteData($quoteId);
        // $orderTotals = $orderData['order_totals'];

        // if(!empty($quoteData) && !empty($orderTotals)) {
        //     $coupon = $this->getCoupons($quoteData['applied_rule_ids']);
        //     $order->setBaseGrandTotal($orderData['base_order_grand_total']);
        //     $order->setGrandTotal($orderData['order_grand_total']);
        //     $order->setBaseSubtotal($quoteData['base_subtotal']);
        //     $order->setSubtotal($quoteData['subtotal']);
        //     $order->setBaseSubtotalInclTax($baseSubtotalInclTax);
        //     $order->setSubtotalInclTax($subtotalInclTax);
        //     $order->setDiscountTaxCompensationAmount($discountTaxCompensationAmount);
        //     $order->setBaseDiscountTaxCompensationAmount($baseDiscountTaxCompensationAmount);
        //     $order->setBaseTaxAmount($orderTotals['order_total_tax']);
        //     $order->setTaxAmount($orderTotals['order_total_tax']);
        //     $order->setBaseDiscountAmount(-$orderTotals['order_total_discount']);
        //     $order->setDiscountAmount(-$orderTotals['order_total_discount']);
        //     $order->setDeliveryGroup(json_encode($delivery));
        //     $order->setQuoteId($quoteId);
        //     $order->setIncrementId($quoteData['reserved_order_id']);
        //     $order->setAppliedRuleIds($quoteData['applied_rule_ids']);
        //     $order->setDiscountDescription($coupon);
        //     $order->setCouponCode($coupon);
        // }

        // $order->save();
        // $this->orderSender->send($order, true);
        // $orderId = $order->getId();
        // $this->eventManager->dispatch('ah_supermax_place_order_after', ['order' => $order->getData()]);
        
        // // if(isset($orderData['tax']) && $orderData['tax']) {
        // //     foreach($orderData['tax'] as $order_tax) {
        // //         $tax_data = array(
        // //             'order_id' => $orderId,
        // //             'title' => $order_tax['db_title'],
        // //             'percent' => $order_tax['percent'],
        // //             'amount' => $order_tax['value'],
        // //             'base_amount' => (float)$this->helper->convert($order_tax['value'], $storeCurrencyCode, $storeBaseCurrencyCode),
        // //             'priority' => $order_tax['priority'],
        // //             'position' => $order_tax['position']
        // //         );
        // //         $this->saveTaxData($tax_data, $order);
        // //     }
        // //     $this->saveTaxItemData($orderId, $storeCurrencyCode, $storeBaseCurrencyCode);
        // // }

        // if($order->getStatus() == 'complete'){
        //     $orderId = $order->getId();
        //     $this->createInvoice($orderId);
        //     $this->createShipment($orderId, $outletSourceCode);
        //     // $this->helper->connectionUpdateForOrderItems($orderId);
        // }

        // if($paymentMethod == 'ezetap') {
        //     $this->updateEzetapPaymentInfo($orderData['edc_request_id'], $orderId);
        // }

        // if($orderData['overrides']) {
        //     $overrides = array(
        //         'pos_user_id' => $userId,
        //         'pos_outlet_id' => $outletId,
        //         'customer_id' => $customerId,
        //         'order_id' => $orderId,
        //         'overrides' => $orderData['overrides']
        //     );
        //     $this->updateOverrides($overrides);
        // }
        
        // if(!empty($customerId) && !empty($customerToken)) {
        //     $this->helper->revokeCustomerToken($customerId, $customerToken);
        // }

        $orderResult = array(
            // 'order' => $order,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'tax_incl_item' => $taxInclInItem,
            'text_incl_promo' => $taxInclInPromo,
            'store_fulfilment_mode' => $store_fulfilment_mode,
            'node_id' => $nodeId,
            // 'amount' => $order->getGrandTotal(),
            'payment_code' => $orderData['payment_method']['code'],
            'payment_method' => $orderData['payment_method']['title'],
            'store_id' => $storeId,
            'store_currency_symbol' => $storeCurrencySymbol,
            'store_base_currency' => $storeBaseCurrencyCode,
            'store_currency' => $storeCurrencyCode,
            'billing_address' => $orderBillingAddress,
            'shipping_address' => $orderShippingAddress,
            'promise_options' => $promiseOptions,
            'shipping_charges' => $shippingCharges,
            'base_shipping_charges' => $baseShippingCharges,
            'order_status' => $paymentOrderStatus,
            'order_state' => $orderState,
            "products" => $orderData['products']

        );
        return $orderResult;
    }

    private function getQuoteItems($quoteId) {
        $connection = $this->resource->getConnection();
        $quoteItemsTable = $this->resource->getTableName('quote_item');
        $quoteData = $connection->query("SELECT * FROM $quoteItemsTable WHERE quote_id='" . $quoteId . "'")->fetchAll();
        return $quoteData;
    }

    private function getCoupons($rule_ids) {
        $coupon = "";
        $salesRuleCouponTable = $this->resource->getTableName('salesrule_coupon');
        $connection = $this->resource->getConnection();
        if(!empty($rule_ids)) {
            $appliedRuleIds = explode("," , $rule_ids);
            if(!empty($appliedRuleIds)) {   
                foreach ($appliedRuleIds as $appliedRuleId) {     
                    $couponData = $connection->query("SELECT * FROM $salesRuleCouponTable WHERE rule_id='" . (int)$appliedRuleId . "'")->fetch();
                    if(!empty($couponData)) {
                        $coupon = $couponData['code'];
                    }
                }
            }
        }
        return $coupon;
    }
}