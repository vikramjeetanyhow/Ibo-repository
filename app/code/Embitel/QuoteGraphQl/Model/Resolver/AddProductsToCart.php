<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Embitel\Quote\Model\Cart\AddProductsToCart as AddProductsToCartService;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\Framework\HTTP\Client\Curl;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\Processor\ItemDataProcessorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\App\Config\ScopeConfigInterface;


/**
 * Resolver for addProductsToCart mutation
 *
 * @inheritdoc
 */
class AddProductsToCart implements ResolverInterface
{
    private $prodctError;
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var AddProductsToCartService
     */
    private $addProductsToCartService;

    /**
     * @var ItemDataProcessorInterface
     */
    private $itemDataProcessor;

    /**
     * @var EventManager
     */
    private $_eventManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param GetCartForUser $getCartForUser
     * @param AddProductsToCartService $addProductsToCart
     * @param  ItemDataProcessorInterface $itemDataProcessor
     */
    public function __construct(
        AddProductsToCartService $addProductsToCart,
        \Embitel\Quote\Helper\Data $helper,
        Curl $curl,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\App\ResourceConnection $resource,
        ItemDataProcessorInterface $itemDataProcessor,
        ScopeConfigInterface $scopeConfig,
        TimezoneInterface $timezoneInterface,
        EventManager $eventManager
    ) {
        $this->addProductsToCartService = $addProductsToCart;
        $this->itemDataProcessor = $itemDataProcessor;
        $this->helper = $helper;
        $this->_resource = $resource;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
        $this->prodctError = 0;
        $this->availableQty = [];
        $this->timezoneInterface = $timezoneInterface;
        $this->_eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $this->helper->addLog("===========================", "addtocart-promise.log");
        $this->helper->addLog("Entered AddProductsToCart", "addtocart-promise.log");
        $this->helper->addLog("===========================", "addtocart-promise.log");
        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }
        if (empty($args['cartItems']) || !is_array($args['cartItems'])
        ) {
            throw new GraphQlInputException(__('Required parameter "cartItems" is missing'));
        }

        $maskedCartId = $args['cartId'];
        $postalCode = (isset($args) && isset($args['postal_code']))?$args['postal_code']:"";
        $cartItemsData = $args['cartItems'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        $connection = $this->_resource->getConnection();
        $tableName = $this->_resource->getTableName('quote_id_mask');
        $sql = "select quote_id from ".$tableName." where masked_id ='".$maskedCartId."'";
        $quoteId = $connection->fetchOne($sql);
        $cart = $this->quoteRepository->get($quoteId);

        $tempItems = [];
        $finalItems = [];
        if(isset($cartItemsData) && count($cartItemsData) == 1){
            if (!$this->itemIsAllowedToCart($cartItemsData, $context)) {
                return [
                    'cart' => [
                        'model' => $cart,
                    ],
                    'user_errors' => [
                         [
                            'code' => "NOT_SALABLE",
                            'message' => "Product is not available."
                         ],
                 ]
                ];
            } else {
                $sku = (count($cartItemsData)>0)?$cartItemsData[0]['sku']:"";
                $qty = (count($cartItemsData)>0)?$cartItemsData[0]['quantity']:"";
                $this->helper->addLog("Promise need to validate for SKU:".$sku." and Qty:".$qty, "addtocart-promise.log");
                if($sku && $qty) {
                    $isValid = $this->promiseValidate($sku,$qty,$cart,$postalCode); //Validate cartitem with promise call and prepare array with valid sku
                    if($isValid){
                        $tempItems['sku'] = $sku;
                        $tempItems['quantity'] = $qty;
                        $finalItems[] = (new CartItemFactory())->create($tempItems);
                    }
                }else {
                    $this->helper->addLog("No SKU/QTY found in request", "addtocart-promise.log");
                }
            }
        }
        if(count($finalItems)>0){
            /** @var AddProductsToCartOutput $addProductsToCartOutput */

            $addProductsToCartOutput = $this->addProductsToCartService->execute($cart, $finalItems, $postalCode);

            $isRegionalPricingEnable = $this->scopeConfig->getValue("regional_pricing/setting/active");
            if($isRegionalPricingEnable == 1) {
                $this->_eventManager->dispatch('item_price_by_postcode', ['quote' => $cart]);
            }

            $this->_eventManager->dispatch('update_shippingcost_time', ['quote' => $cart]);
            return [
                'cart' => [
                    'model' => $cart,
                ],
                'user_errors' => array_map(
                    function (Error $error) {
                        return [
                            'code' => $error->getCode(),
                            'message' => $error->getMessage(),
                            'path' => [$error->getCartItemPosition()]
                        ];
                    },
                    $addProductsToCartOutput->getErrors()
                )
            ];
        }else{
            if($this->prodctError){
                return [
                    'cart' => [
                        'model' => $cart,
                    ],
                    'user_errors' => [
                         [
                            'code' => "NOT_SALABLE",
                            'message' => "Product is not available."
                         ],
                 ]
                ];
            }else{
                return [
                    'cart' => [
                        'model' => $cart,
                    ],
                    'user_errors' => [
                         [
                            'code' => "INSUFFICIENT_STOCK",
                            'message' => "This product is out of stock.",
                             "promise_response"=> [
                                "fulfillable_quantity" => $this->availableQty['fulfillable_quantity'] ?? '',
                                "quantity_message" => $this->availableQty['quantity_message'] ?? '',
                                "fulfill_error" => true
                             ]
                         ],
                    ]
                ];
            }
        }
    }

    /**
     * Check if the item can be added to cart
     *
     * @param array $cartItemData
     * @param ContextInterface $context
     * @return bool
     */
    private function itemIsAllowedToCart(array $cartItemData, ContextInterface $context): bool
    {
        $cartItemData = $this->itemDataProcessor->process($cartItemData, $context);
        if (isset($cartItemData['grant_checkout']) && $cartItemData['grant_checkout'] === false) {
            return false;
        }

        return true;
    }
    public function promiseValidate($sku,$qty,$cart,$postalCode) {
        $this->helper->addLog("promiseValidate start", "addtocart-promise.log");
        if($this->helper->getPromiseStatus()){
            $this->helper->addLog("promise check enabled", "addtocart-promise.log");
            $payload = "";
            $postcode = "";
            $address = "";
            $fullfilment_triggered = false;
            $itemArr['sku'] = $sku;
            $itemArr['qty'] =  $qty;
            $items = $cart->getAllVisibleItems();
            $returnResult = true;
            if($postalCode){
                $postcode = $postalCode;
            } else {
                $postcode = $this->helper->getDefaultShippingPostalCode();
            }
            // if($postcode){  //If customer is having the default shippingAddress - LoggedIn
            //     $this->helper->addLog('Got customer postcode '.$postcode, "addtocart-promise.log");
            //     $url = $this->helper->getPromiseApi();//'http://35.200.219.97/promise-engine/v1/promise/';
            //     $payload = $this->helper->RequestJsonData($cart,$postcode,[$itemArr],1);
            // }else{  //If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode
                $fullfilment_triggered = true;
                $url = $this->helper->getCartPromiseApi($cart,$postcode,[$itemArr]);//'http://35.200.219.97/promise-engine/v1/fulfillment-options';
                //$getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                //$this->helper->addLog('System will fetch Default system postcode:'.$getDefaultShippingPincode, "addtocart-promise.log");
                $payload = $this->helper->RequestCartData($cart,$postcode,[$itemArr]);
            //}
            $traceId = $this->helper->getTraceId();
            $client_id = $this->helper->getClientId();
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_POST, true);
            $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
            $this->curl->setHeaders($headers);
            $this->helper->addLog('Promise Curl Initiated for addtocart with below payload:', "addtocart-promise.log");
            $this->helper->addLog(json_encode($payload), "addtocart-promise.log");

            $startTime = microtime(true);
            $this->helper->addLog("Promise call Start time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$startTime, "addtocart-promise.log");

            $this->curl->post($url, $payload);
            $result = $this->curl->getBody();
            $resultData = json_decode($result,true);

            $endTime = microtime(true);
            $this->helper->addLog("Promise call End time: ".date("Y-m-d H:i:s").
                " Micro sec: ".$endTime. ", Difference in milliseconds:
                ".number_format($endTime - $startTime, 5)/1000,
                "addtocart-promise.log");
            
            $this->helper->addLog("Got Promise Response:");
            $this->helper->addLog(json_encode($resultData), "addtocart-promise.log");
            $tmp_arr_data = array();
            if($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])){    //Fullment-option API Response (customer doesn't have default shipping address
                $tmpReturn = [];
                if(isset($resultData['out_of_stock']) && count($resultData['out_of_stock']) > 0) {
                    $this->availableQty = [
                        'fulfillable_quantity' => 0,
                        'quantity_message' => "Out Of Stock"
                    ];
                }
                foreach($resultData['promise_lines'] as $data){
                    $this->availableQty = [
                        'fulfillable_quantity' => $data['fulfillable_quantity']['quantity_number'],
                        'quantity_message' => $data['fulfillable_quantity']['quantity_number']." is available"
                    ];
                    $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                    $tmp_arr_data[$data['item']['offer_id']]['qty'] = $qty;
                    if(($data['fulfillable_quantity'] == null) || ($data['fulfillable_quantity']['quantity_number'] != $data['quantity']['quantity_number'])) {
                        $this->helper->addLog("Fullfillment Not satisfied for: ".$data['item']['offer_id'], "addtocart-promise.log");
                        $tmpReturn[] = 0;
                        $tmp_arr_data[$data['item']['offer_id']]['error'] = true;
                    }else{
                        $tmpReturn[] = 1;
                        $tmp_arr_data[$data['item']['offer_id']]['error'] = false;
                         $this->helper->addLog("Fullfillment satisfied for : ".$data['item']['offer_id'], "addtocart-promise.log");
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
                if(in_array(0, $tmpReturn)){
                    $returnResult = false;
                }
            }
            // if(!$fullfilment_triggered && isset($resultData) && isset($resultData['promise_id'])){  //Promise API Response if customer having shipping address
            //     $data = $resultData['delivery_groups'];
            //     $arr = [];
            //     $promise_arr_data = [];
            //     $promiseOptions="";
            //     $deliveryGroup="";
            //     $promiseCreatedAt = "";
            //     $promiseExpiresAt = "";
            //     if(count($data)>0){ //Key hardcoded as will get only one options
            //         $promiseOptions = (isset($data[0]['promise_options']))?json_encode($data[0]['promise_options']):null;
            //         $deliveryGroup = (isset($data))?json_encode($data):null;
            //         $promiseCreatedAt = (isset($resultData['created_at']))?$resultData['created_at']:null;
            //         $promiseExpiresAt = (isset($resultData['expires_at']))?$resultData['expires_at']:null;
            //     }
            //     $connection = $this->_resource->getConnection();
            //     $tableName = $this->_resource->getTableName('quote');
            //     $sql = "update ".$tableName." set promise_options='".$promiseOptions."', delivery_group='".$deliveryGroup."', promise_id ='".$resultData['promise_id']."', promise_created_at ='".$promiseCreatedAt."', promise_expires_at ='".$promiseExpiresAt."' where entity_id = ".$cart->getId();
            //     $this->helper->addLog("Query promise options : ".$promiseOptions, "addtocart-promise.log");
            //     $connection->query($sql);
            //     $tmpReturn = [];
            //     foreach($data as $deliveryData){
            //         $delOptionData = $deliveryData['delivery_group_lines'];
            //         foreach($delOptionData as $delData) {
            //             $promise_arr_data[$delData['item']['offer_id']] = $delData['fulfillable_quantity'];
            //             $promise_arr_data[$delData['item']['offer_id']]['qty'] = $qty;
            //             if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {
            //                 $this->helper->addLog("Promise not satisfied for : ".$delData['item']['offer_id'], "addtocart-promise.log");
            //                 $tmpReturn[] = 0;
            //                 $promise_arr_data[$delData['item']['offer_id']]['error'] = true;
            //             }else{
            //                 $this->helper->addLog("Promise satisfied for : ".$delData['item']['offer_id'], "addtocart-promise.log");
            //                 $tmpReturn[] = 1;
            //                 $promise_arr_data[$delData['item']['offer_id']]['error'] = false;
            //             }
            //         }
            //     }

            //     foreach ($items as $item) {
            //         if($item->getProduct_type() == 'simple') {
            //             if(is_array($promise_arr_data) && array_key_exists($item->getSku(), $promise_arr_data)){
            //                 $itemData = serialize($promise_arr_data[$item->getSku()]);
            //                 $item->setAdditionalData($itemData);
            //             }
            //         }
            //     }
            //     if(in_array(0, $tmpReturn)){
            //         $returnResult = false;
            //     }
            // }
            if(isset($resultData) && isset($resultData['out_of_stock']) && count($resultData['out_of_stock']) > 0){
                $this->helper->addLog('Products out of stock.'.json_encode($resultData), "addtocart-promise.log");
                $returnResult = false;
            }
            if(isset($resultData) && isset($resultData['errors'])){
                $this->helper->addLog('Product that you are trying to add is not available.'.json_encode($resultData), "addtocart-promise.log");
                $returnResult = false;
                $this->prodctError = 1;
            }

            return $returnResult;
        }else{
            $this->helper->addLog("Skip: promise check disabled", "addtocart-promise.log");
            //Skip promise check
            return true;

        }
    }
}
