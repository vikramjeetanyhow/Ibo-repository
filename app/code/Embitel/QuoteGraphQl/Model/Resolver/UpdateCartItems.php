<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\HTTP\Client\Curl;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\UpdateCartItems as  UpdateCartItemsProvider;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\SalesRule\Helper\Data as SalesRuleData;

/**
 * @inheritdoc
 */
class UpdateCartItems implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var UpdateCartItemsProvider
     */
    private $updateCartItems;

    /**
     * @var ArgumentsProcessorInterface
     */
    private $argsSelection;

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
     * @param CartRepositoryInterface $cartRepository
     * @param UpdateCartItemsProvider $updateCartItems
     * @param ArgumentsProcessorInterface $argsSelection
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CartRepositoryInterface $cartRepository,
        UpdateCartItemsProvider $updateCartItems,
        \Embitel\Quote\Helper\Data $helper,
        Curl $curl,
        \Magento\Framework\App\ResourceConnection $resource,
        ArgumentsProcessorInterface $argsSelection,
        EventManager $eventManager,
        ScopeConfigInterface $scopeConfig,
        SalesRuleData $salesRuledata
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->cartRepository = $cartRepository;
        $this->updateCartItems = $updateCartItems;
        $this->argsSelection = $argsSelection;
        $this->helper = $helper;
        $this->_resource = $resource;
        $this->curl = $curl;
        $this->_eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
        $this->salesRuledata = $salesRuledata;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $this->helper->addLog("===========================", "updatecart-promise.log");
        $this->helper->addLog("Entered UpdateCartItems", "updatecart-promise.log");
        $this->helper->addLog("===========================", "updatecart-promise.log");

        $processedArgs = $this->argsSelection->process($info->fieldName, $args);

        if (empty($processedArgs['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }

        $maskedCartId = $processedArgs['input']['cart_id'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        if (empty($processedArgs['input']['postal_code']) && (empty($processedArgs['input']['cart_items']) || !is_array($processedArgs['input']['cart_items']))
        ) {
            throw new GraphQlInputException(__('Required parameter "cart_items" is missing.'));
        }
        if (isset($processedArgs['input']['postal_code']) && !empty($processedArgs['input']['postal_code'])) {
            $this->helper->addLog("Update postal code to ".$processedArgs['input']['postal_code'], "updatecart-promise.log");
            $cart->setPostalCode($processedArgs['input']['postal_code'])->save();
        }

        if (!empty($processedArgs['input']['cart_items'])
            && is_array($processedArgs['input']['cart_items'])
        ) {
                $cartItems = $processedArgs['input']['cart_items'];
            try {
                $itemSku = "";
                $itemQty = "";
                $isValid = false;
                foreach ($cartItems as $item) {
                    if ($item['quantity'] > 0) {
                        $itemId = $item['cart_item_id'];
                        $cartItem = $cart->getItemById($itemId);
                        if ($cartItem) {
                            $itemSku = $cartItem->getSku();
                            $itemQty = $item['quantity'];
                        }
                    }
                }
                if ($itemSku != "" && $itemQty != "") {
                    //Validate cartitem with promise call and prepare array with valid sku
                    $isValid = $this->promiseValidate($itemSku, $itemQty, $cart);
                }

                $this->salesRuledata->checkIboSpecialCoupon($cart);
                $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
                $this->updateCartItems->processCartItems($cart, $cartItems);
                $this->cartRepository->save($cart);
                if ($this->salesRuledata->isReferralPromotionApplied) {
                    $this->salesRuledata->addLog('Entered the re-check condition');
                    $applySpecialPromo = $this->salesRuledata->isSpecialDiscountApply($cart);
                    // if ($applySpecialPromo) {
                    //     $this->salesRuledata->addLog('Entered the re-check enable condition');
                    //     $this->salesRuledata->setIboSpecialPromo($cart);
                    // }
                }

                $this->_eventManager->dispatch('update_shippingcost_time', ['quote' => $cart]);

            } catch (NoSuchEntityException $e) {
                throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
            } catch (LocalizedException $e) {
                throw new GraphQlInputException(__($e->getMessage()), $e);
            }
        }
        $isRegionalPricingEnable = $this->scopeConfig->getValue("regional_pricing/setting/active");
        if ($isRegionalPricingEnable == 1) {
            $this->_eventManager->dispatch('item_price_by_postcode', ['quote' => $cart]);
        }

        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
    public function promiseValidate($sku, $qty, $cart)
    {
        $this->helper->addLog("Entered promiseValidate", "updatecart-promise.log");
        if ($this->helper->getPromiseStatus()) {
            $payload = "";
            $postcode = "";
            $address = "";
            $fullfilment_triggered = false;
            $itemArr['sku'] = $sku;
            $itemArr['qty'] =  $qty;
            $items = $cart->getAllVisibleItems();
            $returnResult = true;
            if ($cart->getPostalCode()) {
                $postcode = $cart->getPostalCode();
            } else {
                $postcode = $this->helper->getDefaultShippingPostalCode();
            }
            // if($postcode){  //If customer is having the default shippingAddress - LoggedIn
            //     $this->helper->addLog('Got customer postcode '.$postcode, "updatecart-promise.log");
            //     $url = $this->helper->getPromiseApi();//'http://35.200.219.97/promise-engine/v1/promise/';
            //     $payload = $this->helper->RequestJsonData($cart,$postcode,[$itemArr]);
            // }else{  //If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode
                $fullfilment_triggered = true;
                $url = $this->helper->getCartPromiseApi($cart, $postcode, [$itemArr]);//'http://35.200.219.97/promise-engine/v1/fulfillment-options';
                //$getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                //$this->helper->addLog('System will fetch Default system postcode:'.$getDefaultShippingPincode, "updatecart-promise.log");
                //$payload = $this->helper->RequestCartData($cart, $postcode, [$itemArr]);
                $payload = $this->helper->UpdateCartRequestData($cart, $postcode,$sku,$qty);
           // }
            $traceId = $this->helper->getTraceId();
            $client_id = $this->helper->getClientId();
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_POST, true);
            $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
            $this->curl->setHeaders($headers);
            $this->helper->addLog('Curl Initiated for updatecart promise with below payload:', "updatecart-promise.log");
            $this->helper->addLog(json_encode($payload), "updatecart-promise.log");
            $this->curl->post($url, $payload);
            $result = $this->curl->getBody();
            $resultData = json_decode($result, true);
            $this->helper->addLog("Got Promise Response:");
            $this->helper->addLog(json_encode($resultData), "updatecart-promise.log");
            $tmp_arr_data = [];
            if ($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])) {    //Fullment-option API Response (customer doesn't have default shipping address
                $tmpReturn = [];
                foreach ($resultData['promise_lines'] as $data) {
                    // $data['fulfillable_quantity']['quantity_number'] = 20;
                    $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                    if (($data['fulfillable_quantity'] == null) || ($data['fulfillable_quantity']['quantity_number'] != $data['quantity']['quantity_number'])) {
                        $this->helper->addLog("Fullfillment Not satisfied for: ".$data['item']['offer_id'], "updatecart-promise.log");
                        $tmpReturn[] = 0;
                    } else {
                        $tmpReturn[] = 1;
                         $this->helper->addLog("Fullfillment satisfied for : ".$data['item']['offer_id'], "updatecart-promise.log");
                    }
                }
                foreach ($items as $item) {
                    if ($item->getProduct_type() == 'simple') {
                        if (isset($tmp_arr_data) && isset($tmp_arr_data[$item->getSku()])) {
                            $itemData = serialize($tmp_arr_data[$item->getSku()]);
                            $item->setAdditionalData($itemData);
                        }
                    }
                }
                if (in_array(0, $tmpReturn)) {
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
            //     $this->helper->addLog("Query promise options : ".$promiseOptions, "updatecart-promise.log");
            //     $connection->query($sql);
            //     $tmpReturn = [];
            //     foreach($data as $deliveryData){
            //         $delOptionData = $deliveryData['delivery_group_lines'];
            //         foreach($delOptionData as $delData) {
            //             $promise_arr_data[$delData['item']['offer_id']] = $delData['fulfillable_quantity'];
            //             if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {
            //                 $this->helper->addLog("Promise not satisfied for : ".$delData['item']['offer_id'], "updatecart-promise.log");
            //                 $tmpReturn[] = 0;
            //             }else{
            //                 $tmpReturn[] = 1;
            //                 $this->helper->addLog("Promise satisfied for : ".$delData['item']['offer_id'], "updatecart-promise.log");
            //                 // $this->addLog("First item fulfilable satisfied.promise");
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
            $outOfStockData = [];
            if (isset($resultData) && isset($resultData['out_of_stock']) && count($resultData['out_of_stock']) > 0) {
                $this->helper->addLog('Products out of stock.'.json_encode($resultData), "updatecart-promise.log");
                $outOfStockData = array_column($resultData['out_of_stock'], "offer_id");
                foreach ($items as $item) {
                    if ($item->getProduct_type() == 'simple' && in_array($item->getSku(), $outOfStockData)) {
                        $tmp_arr_data[$item->getSku()]["quantity_number"] = 0;
                        $tmp_arr_data[$item->getSku()]["quantity_uom"] = "EA";
                        if (isset($tmp_arr_data) && isset($tmp_arr_data[$item->getSku()])) {
                            $itemData = serialize($tmp_arr_data[$item->getSku()]);
                            $item->setAdditionalData($itemData);
                        }
                    }
                }
                $returnResult = false;
            }
            if (isset($resultData) && isset($resultData['errors'])) {
                $this->helper->addLog('Product that you are trying to add is not available.', "updatecart-promise.log");
                foreach ($items as $item) {
                    if ($item->getProduct_type() == 'simple') {
                        $tmp_arr_data[$item->getSku()]["quantity_number"] = 0;
                        $tmp_arr_data[$item->getSku()]["quantity_uom"] = "EA";
                        if (isset($tmp_arr_data) && isset($tmp_arr_data[$item->getSku()])) {
                            $itemData = serialize($tmp_arr_data[$item->getSku()]);
                            $item->setAdditionalData($itemData);
                        }
                    }
                }
                $returnResult = false;
            }
            return $returnResult;
        } else {
            //Skip promise check
            return true;
        }
    }
}
