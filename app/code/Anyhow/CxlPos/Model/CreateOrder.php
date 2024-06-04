<?php

/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento CXL Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

 namespace Anyhow\CxlPos\Model;

class CreateOrder implements \Anyhow\CxlPos\Api\CreateOrderInterface
{

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\CxlPos\Helper\Data $helper,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\Order $convertOrder,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction
    ){
        $this->quoteFactory = $quoteFactory;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->order = $order;
        $this->_orderRepository = $orderRepository;
        $this->_convertOrder = $convertOrder;
        $this->_shipmentNotifier = $shipmentNotifier;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
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
        $errormsg = "";

        try {

                $orderData = array();
                $params = $this->helper->getParams();
                if(isset($params['quote_id']) && !empty($params['quote_id'])) {
                    if(!empty($params)){
                        $orderData = [
                            'quote_id' => isset($params['quote_id']) ? $params['quote_id'] : '',
                            // 'billing_address' => $params['billing_address'],
                            // 'shipping_address' => $params['shipping_address'],
                            'payment_method' => $params['payment_method'],
                            'shipping_method' => isset($params['shipping_method']) ? $params['shipping_method'] : ''
                        ];
                    }
                   
                    $result = $this->createOrderByQuote($orderData);
                    $result = $result->getData();

                    //create Shipment
                    $this->createShipment($result['entity_id'],$params['source_code']);

                    //create Invoice
                    $this->createInvoice($result['entity_id']);

                }else{
                    $error = true;   
                }     
        } catch (\Exception $e) {
            $this->helper->addDebuggingLogData("---- Order Debugger Catch Error : " . $e->getMessage());
            $errormsg = $e->getMessage();
            $error = true;
        }
        if($error){
            $data = array('error' => $error, 'result' => $result, 'msgCode'=> $errormsg);   
        }else{
            $data = array('error' => $error, 'result' => $result, 'msgCode'=> $errormsg); 
        }

        return json_encode($data);
    }

    public function createOrderByQuote($orderData){
        $store=$this->_storeManager->getStore();
        $quote = $this->quoteFactory->create()->load($orderData['quote_id']);
        $customerEmail = $quote->getCustomerEmail();
        $CustomerId = $quote->getCustomerId();
        $quote->setStore($store);
        $quote->setCurrency();

        //Set Address to quote
        // if($orderData['shipping_address']){
        //     $quote->getShippingAddress()->addData($orderData['shipping_address']);
        // }
        // if($orderData['billing_address']){
        //     $quote->getBillingAddress()->addData($orderData['billing_address']);
        // }
        // Collect Rates and Set Shipping & Payment Method
        $shippingAddress=$quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod($orderData['shipping_method']); //shipping method
        $quote->getPayment()->setMethod($orderData['payment_method']);
        $quote->setInventoryProcessed(false); //not effetc inventory
        $quote->save(); //Now Save quote and your quote is ready

        // Collect Totals & Save Quote
       $quote->collectTotals()->save();
        // Create Order From Quote
        $quote = $this->cartRepositoryInterface->get($quote->getId());
        $orderId = $this->cartManagementInterface->placeOrder($quote->getId());
        $order = $this->order->load($orderId);

        $order->setEmailSent(1);

        return $order;
    }

    private function createShipment($orderId,$sourceCode){
      
        $order = $this->_orderRepository->get($orderId);
        // to check order can ship or not
        if (!$order->canShip()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("You can't create the Shipment of this order.")
            );
        }
        $orderShipment = $this->_convertOrder->toShipment($order);
        foreach ($order->getAllItems() as $orderItem) {
            // Check virtual item and item Quantity
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            $qty = $orderItem->getQtyToShip();
            $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qty);
            $orderShipment->addItem($shipmentItem);
        }
        $orderShipment->register();
        $orderShipment->getOrder()->setIsInProcess(true);
        try {
            // Save created Order Shipment
            $orderShipment->getExtensionAttributes()->setSourceCode($sourceCode);
            $orderShipment->save();
            $orderShipment->getOrder()->save();
            // Send Shipment Email
           // $this->_shipmentNotifier->notify($orderShipment);
            $orderShipment->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }
    
    private function createInvoice($orderId)
    {
        $order = $this->_orderRepository->get($orderId);
        if($order->canInvoice()) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->_transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $this->invoiceSender->send($invoice);
            //send notification code
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
        }
    }

}