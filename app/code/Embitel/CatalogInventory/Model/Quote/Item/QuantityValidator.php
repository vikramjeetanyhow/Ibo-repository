<?php
/**
 * Product inventory data validator
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\CatalogInventory\Model\Quote\Item;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Helper\Data;
use Magento\CatalogInventory\Model\Quote\Item\QuantityValidator\Initializer\Option;
use Magento\CatalogInventory\Model\Quote\Item\QuantityValidator\Initializer\StockItem;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Framework\HTTP\Client\Curl;
/**
 * Quote item quantity validator.
 *
 * @api
 * @since 100.0.2
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @deprecated 100.3.0 Replaced with Multi Source Inventory
 * @link https://devdocs.magento.com/guides/v2.4/inventory/index.html
 * @link https://devdocs.magento.com/guides/v2.4/inventory/inventory-api-reference.html
 */


class QuantityValidator extends \Magento\CatalogInventory\Model\Quote\Item\QuantityValidator
{
    /**
     * @var QuantityValidator\Initializer\Option
     */
    protected $optionInitializer;

    /**
     * @var QuantityValidator\Initializer\StockItem
     */
    protected $stockItemInitializer;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
        protected $stockState;

        protected $curl;
        protected $accountManagement;
        protected $helper;
        protected $_resource;


    /**
     * @param Option $optionInitializer
     * @param StockItem $stockItemInitializer
     * @param StockRegistryInterface $stockRegistry
     * @param StockStateInterface $stockState
     * @return void
     */
    public function __construct(
        Option $optionInitializer,
        StockItem $stockItemInitializer,
        StockRegistryInterface $stockRegistry,
        StockStateInterface $stockState
    ) {
        $this->optionInitializer = $optionInitializer;
        $this->stockItemInitializer = $stockItemInitializer;
        $this->stockRegistry = $stockRegistry;
        $this->stockState = $stockState;
        parent::__construct($optionInitializer, $stockItemInitializer, $stockRegistry, $stockState);
    }

    /**
     * Add error information to Quote Item
     *
     * @param \Magento\Framework\DataObject $result
     * @param Item $quoteItem
     * @return void
     */
    private function addErrorInfoToQuote($result, $quoteItem)
    {
        $quoteItem->addErrorInfo(
            'cataloginventory',
            Data::ERROR_QTY,
            $result->getMessage()
        );

        $quoteItem->getQuote()->addErrorInfo(
            $result->getQuoteMessageIndex(),
            'cataloginventory',
            Data::ERROR_QTY,
            $result->getQuoteMessage()
        );
    }

    /**
     * Check product inventory data when quote item quantity declaring
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function validate(Observer $observer)
    {
        return;
		$this->addLog("--------------------- Validate start -------------------------- ");
        $this->addLog("Validate Entered");
        /* @var $quoteItem Item */
        $quoteItem = $observer->getEvent()->getItem();
        if (!$quoteItem ||
            !$quoteItem->getProductId() ||
            !$quoteItem->getQuote()
        ) {
            return;
        }
        $product = $quoteItem->getProduct();
        $qty = $quoteItem->getQty(); 
        $cart = $quoteItem->getQuote();
        if($this->helper->getPromiseStatus()){
            $this->addLog("Validate Entered");
            // if($quoteItem->getQuote()->getPromiseId() == "temp_promotions"){ //Skip Promtions for addtocart 
            //     $this->addLog('Got promise id as temp promotions');
            //     return;
            // } 
            $this->addLog("Validate Entered 2");
            $tmp_arr = array();
            $qty_sku_arr = array(); 
            $items = $cart->getAllVisibleItems();
			$totalItems=count($cart->getAllItems()); 
            if($totalItems==0){
                $this->addLog('NO Curl Initiated');
                $payload = "";
                $postcode = "";
                $address = "";
                $fullfilment_triggered = false;
                if($cart->getShippingAddress()){ //1st priority shipping address
                    $postcode = $cart->getShippingAddress()->getPostcode();
                } 

                if($postcode){  //If customer is having the default shippingAddress - LoggedIn
                    $this->addLog('Got customer postcode '.$postcode);
                    $url = $this->helper->getPromiseApi();//'http://35.200.219.97/promise-engine/v1/promise/'; 
                    $payload = $this->helper->CreateJsonRequestData($cart,$postcode);
                }else{  //If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode 
                    $this->addLog('System will fetch Default system postcode');
                    $fullfilment_triggered = true;
                    $url = $this->helper->getCartPromiseApi();//'http://35.200.219.97/promise-engine/v1/fulfillment-options'; 
                    $getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                    $payload = $this->helper->CreateCartRequestData($cart,$getDefaultShippingPincode);
                } 
                $traceId = $this->helper->getTraceId();
                $client_id = $this->helper->getClientId();
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true); 
                $this->curl->setOption(CURLOPT_POST, true); 
                $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
                $this->curl->setHeaders($headers);
                $this->addLog('First item addtocart Curl Initiated');
                $this->addLog(json_encode($payload));
                $this->curl->post($url, $payload);
                $result = $this->curl->getBody();
                $resultData = json_decode($result,true);
                $this->addLog(json_encode($resultData));
                $tmp_arr_data = array();
                if($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])){    //Fullment-option API Response (customer doesn't have default shipping address
                    foreach($resultData['promise_lines'] as $data){
                        // $data['fulfillable_quantity']['quantity_number'] = 20;
                        $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                        if(($data['fulfillable_quantity'] == null) || ($data['fulfillable_quantity']['quantity_number'] != $data['quantity']['quantity_number'])) {
                            $this->addLog("First item fulfilable not satisfied. for fullfillment");
                                // $quoteItem->addErrorInfo(
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('This product is out of stock.')
                                // );
                                // $quoteItem->getQuote()->addErrorInfo(
                                // 'stock',
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('Some of the products are out of stock.')
                                // );
                        }else{
                            $this->addLog("First item fulfilable satisfied.Fullfillment");
                            $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                        }
                    } 
                    foreach ($items as $item) {
                        if($item->getProduct_type() == 'simple') { 
                            if(isset($tmp_arr_data) && isset($tmp_arr_data[$item->getSku()])){ 
                                $itemData = serialize($tmp_arr_data[$item->getSku()]); 
                                $item->setAdditionalData($itemData);
                            }
                        } 
                    }   
                }
                if(!$fullfilment_triggered && isset($resultData) && isset($resultData['promise_id'])){  //Promise API Response if customer having shipping address
                    $data = $resultData['delivery_groups']; 
                    $arr = [];
                    $promise_arr_data = []; 
                    $promiseOptions="";
                    $deliveryGroup="";
                    if(count($data)>0){ //Key hardcoded as will get only one options
                        $promiseOptions = (isset($data[0]['promise_options']))?json_encode($data[0]['promise_options']):null; 
                        $deliveryGroup = (isset($data))?json_encode($data):null;       
                    }
                    

                    $connection = $this->_resource->getConnection();
                    $tableName = $this->_resource->getTableName('quote');
                    $sql = "update ".$tableName." set promise_options='".$promiseOptions."', delivery_group='".$deliveryGroup."', promise_id ='".$resultData['promise_id']."' where entity_id = ".$cart->getId();
                    $this->addLog("Query promise options: ".$promiseOptions);
                    $connection->query($sql);

                    foreach($data as $deliveryData){
                        $delOptionData = $deliveryData['delivery_group_lines'];
                        foreach($delOptionData as $delData) { 
                            $promise_arr_data[$delData['item']['offer_id']] = $delData['fulfillable_quantity'];
                            if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {
                                $this->addLog("First item fulfilable not satisfied. for promise");
                                // $quoteItem->addErrorInfo(
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('This product is out of stock.')
                                // );
                                // $quoteItem->getQuote()->addErrorInfo(
                                // 'stock',
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('Some of the products are out of stock.')
                                // ); 
                            }else{
                                $this->addLog("First item fulfilable satisfied.promise");
                                $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                            } 
                        }
                    } 
                    foreach ($items as $item) {
                        if($item->getProduct_type() == 'simple') {
                            if(is_array($promise_arr_data) && array_key_exists($item->getSku(), $promise_arr_data)){ 
                                $itemData = serialize($promise_arr_data[$item->getSku()]); 
                                $item->setAdditionalData($itemData); 
                                // $item->save();
                            }
                        } 
                    } 
                } 
                if(isset($resultData) && isset($resultData['errors'])){ 
                    throw new LocalizedException(__('Product that you are trying to add is not available.'));
                }
            }
            $this->addLog(" ========= Fetch cart API Started ============");
            /* Fetch cart Items by array */
            foreach($cart->getAllVisibleItems() as $item){
                $tmp_arr[]=$item->getId();
                $qty_sku_arr[$item->getSku()] = (int)$item->getQty();
            }
            ksort($tmp_arr);
			
			/* Promise Engine call - will trigger only for the first item from the quote */
            if(count($tmp_arr) >0 && isset($tmp_arr[0]) && $tmp_arr[0] == $quoteItem->getId()){ 
                $payload = "";
                $postcode="";
                $address = "";
                $fullfilment_triggered = false; 
                if($cart->getShippingAddress()){ //1st priority shipping address
                    $postcode = $cart->getShippingAddress()->getPostcode();
                } 
                
                if($postcode){  //If customer is having the default shippingAddress - LoggedIn 
                    $url = $this->helper->getPromiseApi();//'http://35.200.219.97/promise-engine/v1/promise/'; 
                    $payload = $this->helper->CreateJsonRequestData($cart,$postcode); 
                }else{  //If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode
                    $fullfilment_triggered = true;
                    $url = $this->helper->getCartPromiseApi();//'http://35.200.219.97/promise-engine/v1/fulfillment-options'; 
                    $getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                    $payload = $this->helper->CreateCartRequestData($cart,$getDefaultShippingPincode);
                } 
                $traceId = $this->helper->getTraceId();
                $client_id = $this->helper->getClientId();
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true); 
                $this->curl->setOption(CURLOPT_POST, true); 
                $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
                $this->curl->setHeaders($headers);
                $this->addLog('Fetch Cart Curl Initiated');
                $this->addLog(json_encode($payload));
                $this->curl->post($url, $payload);
                $result = $this->curl->getBody();
                $resultData = json_decode($result,true);
                $tmp_arr_data = array();
                $this->addLog(json_encode($resultData));
                if($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])){    //Fullment-option API Response (customer doesn't have default shipping address
                    foreach($resultData['promise_lines'] as $data){
                        // $data['fulfillable_quantity']['quantity_number'] = 20;
                        $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                        if($qty_sku_arr[$data['item']['offer_id']] > $data['fulfillable_quantity']['quantity_number']){
                            $this->addLog("Fetch cart API started: Not satisfied qty:fullfillment");
                                // $quoteItem->addErrorInfo(
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('This product is out of stock.')
                                // );
                                // $quoteItem->getQuote()->addErrorInfo(
                                // 'stock',
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('Some of the products are out of stock.')
                                // );
                        }else{
                            $this->addLog("Fetch cart API started: satisfied qty:fullfillment");
                            $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                        }
                    }
                    foreach ($items as $item) {
                        if($item->getProduct_type() == 'simple') {
                            if(isset($tmp_arr_data) && isset($tmp_arr_data[$item->getSku()])){ 
                                $itemData = serialize($tmp_arr_data[$item->getSku()]); 
                                $item->setAdditionalData($itemData); 
                                // $item->save();
                            }
                        } 
                    } 
                }
                if(!$fullfilment_triggered && isset($resultData) && isset($resultData['promise_id'])){  //Promise API Response if customer having shipping address
                    $data = $resultData['delivery_groups']; 
                    $arr = [];
                    $promise_arr_data = []; 
                    $promiseOptions="";
                    $deliveryGroup="";
                    if(count($data)>0){ //Key hardcoded as will get only one options
                        $promiseOptions = (isset($data[0]['promise_options']))?json_encode($data[0]['promise_options']):null; 
                        $deliveryGroup = (isset($data))?json_encode($data):null;     
                    }
                    $connection = $this->_resource->getConnection();
                    $tableName = $this->_resource->getTableName('quote');
                    $sql = "update ".$tableName." set promise_options='".$promiseOptions."', delivery_group='".$deliveryGroup."', promise_id ='".$resultData['promise_id']."' where entity_id = ".$cart->getId();
                    $this->addLog("Query promise options:".$promiseOptions);
                    $connection->query($sql);

                    foreach($data as $deliveryData){
                        $delOptionData = $deliveryData['delivery_group_lines'];
                        foreach($delOptionData as $delData) { 
                            $promise_arr_data[$delData['item']['offer_id']] = $delData['fulfillable_quantity'];
                            if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {
                                $this->addLog("Fetch cart API started: Not satisfied qty:promise");
                                // $quoteItem->addErrorInfo(
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('This product is out of stock.')
                                // );
                                // $quoteItem->getQuote()->addErrorInfo(
                                // 'stock',
                                // 'cataloginventory',
                                // Data::ERROR_QTY,
                                // __('Some of the products are out of stock.')
                                // );

                            }else{
                                $this->addLog("Fetch cart API started: Not satisfied qty:promise");
                                $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                            } 
                        }
                    }
                    foreach ($items as $item) {
                        if($item->getProduct_type() == 'simple') {
                            if(is_array($promise_arr_data) && array_key_exists($item->getSku(), $promise_arr_data)){ 
                                $itemData = serialize($promise_arr_data[$item->getSku()]); 
                                $item->setAdditionalData($itemData);
                            // $item->save();
                            }
                        } 
                    } 

                }
                if(isset($resultData) && isset($resultData['errors'])){ 
                    throw new LocalizedException(__('Product that you are trying to add is not available.'));
                }                
            }        
        }

    }

    /**
     * Verifies product options quantity increments.
     *
     * @param Item $quoteItem
     * @param array $options
     * @return void
     */
    private function checkOptionsQtyIncrements(Item $quoteItem, array $options): void
    {
        $removeErrors = true;
        foreach ($options as $option) {
            $optionValue = $option->getValue();
            $optionQty = $quoteItem->getData('qty') * $optionValue;
            $result = $this->stockState->checkQtyIncrements(
                $option->getProduct()->getId(),
                $optionQty,
                $option->getProduct()->getStore()->getWebsiteId()
            );
            if ($result->getHasError()) {
                $quoteItem->getQuote()->addErrorInfo(
                    $result->getQuoteMessageIndex(),
                    'cataloginventory',
                    Data::ERROR_QTY_INCREMENTS,
                    $result->getQuoteMessage()
                );

                $removeErrors = false;
            }
        }

        if ($removeErrors) {
            // Delete error from item and its quote, if it was set due to qty problems
            $this->_removeErrorsFromQuoteAndItem(
                $quoteItem,
                Data::ERROR_QTY_INCREMENTS
            );
        }
    }

    /**
     * Removes error statuses from quote and item, set by this observer
     *
     * @param Item $item
     * @param int $code
     * @return void
     */
    protected function _removeErrorsFromQuoteAndItem($item, $code)
    {
        if ($item->getHasError()) {
            $params = ['origin' => 'cataloginventory', 'code' => $code];
            $item->removeErrorInfosByParams($params);
        }

        $quote = $item->getQuote();
        if ($quote->getHasError()) {
            $quoteItems = $quote->getItemsCollection();
            $canRemoveErrorFromQuote = true;
            foreach ($quoteItems as $quoteItem) {
                if ($quoteItem->getItemId() == $item->getItemId()) {
                    continue;
                }

                $errorInfos = $quoteItem->getErrorInfos();
                foreach ($errorInfos as $errorInfo) {
                    if ($errorInfo['code'] == $code) {
                        $canRemoveErrorFromQuote = false;
                        break;
                    }
                }

                if (!$canRemoveErrorFromQuote) {
                    break;
                }
            }

            if ($canRemoveErrorFromQuote) {
                $params = ['origin' => 'cataloginventory', 'code' => $code];
                $quote->removeErrorInfosByParams(null, $params);
            }
        }
    }
    private function getCustomerPostCode($customerId)
    {
        $result = '';
        $connection = $this->_resource->getConnection(); 
        if($customerId){ 
            $tableName = $this->_resource->getTableName('customer_entity');
            $sql = $connection->select()->from($tableName)
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['default_shipping'])
            ->where('entity_id =?', $customerId);
            $result = $connection->fetchOne($sql);       
        }
        if($result){
            $tableName = $this->_resource->getTableName('customer_address_entity');
            $sql = $connection->select()->from($tableName)
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['postcode'])
            ->where('entity_id =?', $result);
            $result = $connection->fetchOne($sql);       

        }
        return $result;
    }
  public function addLog($logData){
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        $date = date("y_m_d");
        $filename = "pm_inventory_check_".$date.".log";
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    }    
}