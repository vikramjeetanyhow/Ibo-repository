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

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote as QuoteEntity;
class ConvertQuote extends \Magento\Quote\Model\QuoteManagement
{
    public function __construct( 
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\DataObject\Copy $objectCopyService,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Quote\Model\SubmitQuoteValidator $submitQuoteValidator,
        \Magento\Quote\Model\CustomerManagement $customerManagement,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        \Magento\Quote\Model\Quote\Address\ToOrder $quoteAddressToOrder,
        \Magento\Quote\Model\Quote\Address\ToOrderAddress $quoteAddressToOrderAddress,
        \Magento\Quote\Model\Quote\Payment\ToOrderPayment $quotePaymentToOrderPayment,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManager        
    ){
        $this->helper = $helper;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->objectCopyService = $objectCopyService;
        $this->orderFactory = $orderFactory;
        $this->submitQuoteValidator = $submitQuoteValidator;
        $this->customerManagement = $customerManagement;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->quoteAddressToOrder = $quoteAddressToOrder;
        $this->quoteAddressToOrderAddress = $quoteAddressToOrderAddress;
        $this->quotePaymentToOrderPayment = $quotePaymentToOrderPayment;
        $this->eventManager = $eventManager;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        //$this->customerSegment = $customerSegment;
    }

    public function afterCreateOrderByQuote(\Anyhow\SupermaxPos\Model\Supermax\CreateOrder $subject, $result, $orderData) {
        if(isset($orderData['quote_id']) && !empty($orderData['quote_id'])) {
            $delivery = array();
            $quoteId = $orderData['quote_id'];
            $quote = $this->quoteFactory->create()->load($quoteId);
            $this->updateQuoteData($orderData, $quote);
            
            if ($quote->getAllItems()) {
                foreach ($quote->getAllItems() as $item) {
                    $delivery[] = array(
                        'delivery_group_lines' => [
                                array(
                                'item' => array("offer_id" => $item->getSku()),
                                'promise_line_id' => $item->getSku() 
                            )
                        ]
                    );
                }
            }

            $promiseOptions = $result['promise_options'];
            if(!empty($orderData['promise_options'])) {
                $orderData['promise_options'][0]['node_id'] = $result['node_id'];
                $promiseOptions = $orderData['promise_options'];
            }

            $orderObj = array(
                'increment_id' => $quote->getReservedOrderId(),
                'order_channel_info' => $result['store_fulfilment_mode'],
                'order_channel' => 'STORE',
                'tax_incl_in_promo' => $result['text_incl_promo'],
                'tax_incl_in_item' => $result['tax_incl_item'],
                'billing_address' => $result['billing_address'],
                'shipping_address' => $result['shipping_address'],
                'promise_id' => $orderData['promise_id'],
                'promise_created_at' => $orderData['promise_created_at'],
                'promise_expires_at' => $orderData['promise_expires_at'],
                'promise_options' => json_encode($promiseOptions),
                'delivery_group' => !empty($orderData['delivery_groups']) ? json_encode($orderData['delivery_groups']) : json_encode($delivery),
                'shipping_charges' => $result['shipping_charges'],
                'base_shipping_charges' => $result['base_shipping_charges'],
                "base_order_grand_total" => $orderData['base_order_grand_total'],
                "order_grand_total" => $orderData['order_grand_total'],
                'order_status' => $result['order_status'],
                'order_state' => $result['order_state']
            );

            $order = $this->submit($quote, $orderObj);
            
            if(!empty($quote->getProfessionalNumber())) {
                $isReferralApplied = $quote->getIsProfessionalReferralApplied() ? "TRUE" : "FALSE";
                $orderData['comment'] .= "<br/><b>Is Professional Referral Applied: </b>" . $isReferralApplied;
                $orderData['comment'] .= "<br/><b>Professional Referral Number: </b>" . $quote->getProfessionalNumber();
            }
            $order->addCommentToStatusHistory($orderData['comment']);
            $products = $result['products'];
            if ($order->getAllItems()) {
                foreach ($order->getAllItems() as $item) {
                    $quoteItem = $quote->getItemById($item->getQuoteItemId());
                    $productId = "product_" . $quoteItem->getSku();
                    $item->setLotInfo($quoteItem->getLotInfo());
                    if(!empty($products)) {
                        foreach($products as $key => $product) {
                            if(isset($product[$productId])) {
                                $fulfillmentMode = $product[$productId]['fulfillment_type'];
                                $item->setOrderFulfilmentType($fulfillmentMode);
                                $free_shipping = (int)$product[$productId]['free_shipping'];
                                $item->setFreeShipping($free_shipping);
                                $itemMrp = $product[$productId]['mrp'];
                                $item->setIboMrp($itemMrp);
                                $itemAdditionalData = !empty($item->getAdditionalData()) ? (array)json_decode($item->getAdditionalData()) : [];
                                if(isset($itemAdditionalData['unit_cost_price']) && $itemAdditionalData['unit_cost_price']) {
                                    $originalPrice = (isset($itemAdditionalData['original_price']) && $itemAdditionalData['original_price']) ? (float)$itemAdditionalData['original_price'] : 0;
                                    $item->setOriginalPrice($originalPrice);
                                    $item->setBaseOriginalPrice($originalPrice);
                                }
                            }
                        }
                    }
                    $item->save();
                }
            }

            $order->save();
            $result['order'] = $order;
            $result['amount'] = $order->getGrandTotal();
            // $order = $this->orderFactory->create()->loadByIncrementId($orderData['reserved_order_id']);
            // $this->objectCopyService->copyFieldsetToTarget('sales_convert_quote', 'to_order', $quote, $order);
            return $result;
        }
        
    }

    public function submit(QuoteEntity $quote, $orderData = []) {
        if (!$quote->getAllVisibleItems()) {
            $quote->setIsActive(false);
            return null;
        }

        return $this->submitQuote($quote, $orderData);
    }

    protected function submitQuote(QuoteEntity $quote, $orderData = []) {
        $order = $this->orderFactory->create();
        // $this->submitQuoteValidator->validateQuote($quote);
        // if (!$quote->getCustomerIsGuest()) {
        //     if ($quote->getCustomerId()) {
        //         $this->quoteManagement->_prepareCustomerQuote($quote);
        //         $this->customerManagement->validateAddresses($quote);
        //     }
        //     $this->customerManagement->populateCustomerInfo($quote);
        // }
        $addresses = [];
        $quote->reserveOrderId();
        if ($quote->isVirtual()) {
            $this->helper->addDebuggingLogData("---- Before: quote is virtual for quote ID: " . $quote->getId());
            $this->dataObjectHelper->mergeDataObjects(
                \Magento\Sales\Api\Data\OrderInterface::class,
                $order,
                $this->quoteAddressToOrder->convert($quote->getBillingAddress(), $orderData)
            );
            $this->helper->addDebuggingLogData("---- After: quote is virtual for quote ID: " . $quote->getId());
        } else {
            $this->helper->addDebuggingLogData("---- Before: quote is not virtual for quote ID: " . $quote->getId());
            $this->dataObjectHelper->mergeDataObjects(
                \Magento\Sales\Api\Data\OrderInterface::class,
                $order,
                $this->quoteAddressToOrder->convert($quote->getShippingAddress(), $orderData)
            );
            $shippingAddress = $this->quoteAddressToOrderAddress->convert(
                $quote->getShippingAddress(),
                [
                    'address_type' => 'shipping',
                    'email' => $quote->getCustomerEmail()
                ]
            );
            $shippingAddress->setData('quote_address_id', $quote->getShippingAddress()->getId());
            $addresses[] = $shippingAddress;
            $order->setShippingAddress($shippingAddress);
            $order->setShippingMethod($quote->getShippingAddress()->getShippingMethod());
        }
        $billingAddress = $this->quoteAddressToOrderAddress->convert(
            $quote->getBillingAddress(),
            [
                'address_type' => 'billing',
                'email' => $quote->getCustomerEmail(),
            ]
        );
        $billingAddress->setData('quote_address_id', $quote->getBillingAddress()->getId());
        $addresses[] = $billingAddress;
        $order->setBillingAddress($billingAddress);
        $order->setAddresses($addresses);
        $order->setPayment($this->quotePaymentToOrderPayment->convert($quote->getPayment()));
        $order->setItems($this->quoteManagement->resolveItems($quote));
        if ($quote->getCustomer()) {
            $order->setCustomerId($quote->getCustomer()->getId());
        }
        $order->setQuoteId($quote->getId());
        $order->setCustomerEmail($quote->getCustomerEmail());
        $order->setCustomerFirstname($quote->getCustomerFirstname());
        $order->setCustomerMiddlename($quote->getCustomerMiddlename());
        $order->setCustomerLastname($quote->getCustomerLastname());
        $order->setIsProfessionalReferralApplied($quote->getIsProfessionalReferralApplied());
        $order->setProfessionalNumber($quote->getProfessionalNumber());
        $this->submitQuoteValidator->validateOrder($order);

        $this->eventManager->dispatch(
            'sales_model_service_quote_submit_before',
            [
                'order' => $order,
                'quote' => $quote
            ]
        );

        $this->helper->addDebuggingLogData("---- After: quote is not virtual for quote ID: " . $quote->getId());

        try {
            $order->setIncrementId($orderData['increment_id']);
            $order->setOrderChannelInfo($orderData['order_channel_info']);
            $order->setOrderChannel($orderData['order_channel']);
            $order->setTaxInclInPromo($orderData['tax_incl_in_promo']);
            $order->setTaxInclInItem($orderData['tax_incl_in_item']);
            $order->setPromiseId($orderData['promise_id']);
            $order->setPromiseCreatedAt($orderData['promise_created_at']);
            $order->setPromiseExpiresAt($orderData['promise_expires_at']);
            $order->setPromiseOptions($orderData['promise_options']);
            $order->setDeliveryGroup($orderData['delivery_group']);
            // $order->setBaseGrandTotal($orderData['base_order_grand_total']);
            // $order->setGrandTotal($orderData['order_grand_total']);
            $order = $this->orderManagement->place($order);
            $order->setState($orderData['order_state']);
            $order->setStatus($orderData['order_status']);
            $order->save();
            //$this->updateCustomerSegment($quote->getCustomer()->getId());
            $this->eventManager->dispatch(
                'sales_model_service_quote_submit_success',
                [
                    'order' => $order,
                    'quote' => $quote
                ]
            );
        } catch (\Exception $e) {
            $this->helper->addDebuggingLogData("---- Convert quote Debugger Catch Error : " . $e->getMessage());
            throw $e;
        }
        return $order;
    }

    private function updateQuoteData($orderData, $quote) {
        $this->helper->addDebuggingLogData("---- Before: update quote address quote ID: " . $quote->getId());
        // Update quote payment method.
        $quotePaymentMethod = ($orderData['payment_method']['code'] == 'BANK-DEPOSIT') ? 'offlinepayment' : 'prepaid';
        $quote->setPaymentMethod($quotePaymentMethod);
        $quote->getPayment()->importData(['method' => $quotePaymentMethod]);
        $quote->setInventoryProcessed(false);
        $this->helper->addDebuggingLogData("---- Before: update quote billing address quote ID: " . $quote->getId());
        // Update Quote Billing Address.
        $quote->getBillingAddress()->setFirstname($orderData['payment_address']['firstname']);
        $quote->getBillingAddress()->setLastname($orderData['payment_address']['lastname']);
        $quote->getBillingAddress()->setCompany($orderData['payment_address']['company']);
        $quote->getBillingAddress()->setStreet($orderData['payment_address']['street']);
        $quote->getBillingAddress()->setCity($orderData['payment_address']['city']);
        $quote->getBillingAddress()->setPostcode($orderData['payment_address']['postcode']);
        $quote->getBillingAddress()->setTelephone($orderData['payment_address']['telephone']);
        $quote->getBillingAddress()->setCountryId($orderData['payment_address']['country_id']);
        $quote->getBillingAddress()->setRegionId($orderData['payment_address']['region_id']);
        $this->helper->addDebuggingLogData("---- Before: update quote shipping address quote ID: " . $quote->getId());
        // Update Quote Shipping Address.
        $quote->getShippingAddress()->setFirstname($orderData['shipping_address']['firstname']);
        $quote->getShippingAddress()->setLastname($orderData['shipping_address']['lastname']);
        $quote->getShippingAddress()->setCompany($orderData['shipping_address']['company']);
        $quote->getShippingAddress()->setStreet($orderData['shipping_address']['street']);
        $quote->getShippingAddress()->setCity($orderData['shipping_address']['city']);
        $quote->getShippingAddress()->setPostcode($orderData['shipping_address']['postcode']);
        $quote->getShippingAddress()->setTelephone($orderData['shipping_address']['telephone']);
        $quote->getShippingAddress()->setCountryId($orderData['shipping_address']['country_id']);
        $quote->getShippingAddress()->setRegionId($orderData['shipping_address']['region_id']);
        $quote->save();

        $this->helper->addDebuggingLogData("---- After: update quote shipping address quote ID: " . $quote->getId());

        // Update Shipping charges in quote.
        $grandTotal = $orderData['order_grand_total'];
        // $quote->getGrandTotal() + $orderData['shipping_charges'];
        $baseGrandTotal = $orderData['base_order_grand_total'];
        // $quote->getBaseGrandTotal() + $orderData['shipping_charges'];
        $quote->setGrandTotal($grandTotal);
        $quote->setBaseGrandTotal($baseGrandTotal);
        $quote->getShippingAddress()->setGrandTotal($grandTotal);
        $quote->getShippingAddress()->setBaseGrandTotal($baseGrandTotal);
        $quote->getShippingAddress()->setShippingAmount($orderData['shipping_charges']);
        $quote->getShippingAddress()->setBaseShippingAmount($orderData['shipping_charges']);
        $quote->getShippingAddress()->setShippingInclTax($orderData['shipping_charges']);
        $quote->getShippingAddress()->setBaseShippingInclTax($orderData['shipping_charges']);
        $quote->save();
    }


    // private function updateCustomerSegment($customerId) {
    //     if($customerId) {
    //         $this->customerSegment->processEvent(
    //             "sales_order_save_commit_after",
    //             $customerId,
    //             $this->storeManager->getStore()->getWebsite()
    //         );
    //     }
    // }
}
