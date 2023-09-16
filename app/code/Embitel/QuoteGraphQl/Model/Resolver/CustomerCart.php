<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Cart\CustomerCartResolver;
use Magento\CatalogInventory\Helper\Data;
use Embitel\SalesRule\Helper\Data as SalesRuleData;

/**
 * Get cart for the customer
 */
class CustomerCart implements ResolverInterface
{
    /**
     * @var CustomerCartResolver
     */
    private $customerCartResolver;

    /**
     * CustomerCart constructor.
     *
     * @param CustomerCartResolver $customerCartResolver
     */
    public function __construct(
        CustomerCartResolver $customerCartResolver,
        \Embitel\Quote\Helper\Data $helper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\ResourceConnection $resource,
        SalesRuleData $salesRuledata,
        CartRepositoryInterface $cartRepository
    ) {
        $this->customerCartResolver = $customerCartResolver;
        $this->helper = $helper;
        $this->request = $request;
        $this->_resource = $resource;
        $this->curl = $curl;
        $this->salesRuledata = $salesRuledata;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $currentUserId = $context->getUserId();
        /**
         * @var ContextInterface $context
         */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The request is allowed for logged in customer'));
        }
        try {

            $evalReferalPromotion = $this->request->getHeader('evalProfessionalReferalPromotion') ?? '';
            $cart = $this->customerCartResolver->resolve($currentUserId);

            if (!$cart->getPostalCode()) {
                $getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                $cart->setPostalCode($getDefaultShippingPincode);
            }
            if (null !== $this->request->getParam('source_id') && ($this->request->getParam('source_id') === 'online' || $this->request->getParam('source_id') === 'store')) {
                $source = $this->request->getParam('source_id');
                $cart->setChannel($source);
            }
            if (null !== $this->request->getHeader('sourceChannelInfo')) {
                $sourceChannelInfo = $this->request->getHeader('sourceChannelInfo');
                $cart->setChannelInfo($sourceChannelInfo);
            }
            
            if ($evalReferalPromotion) {
                //remove special coupon
                $this->salesRuledata->checkIboSpecialCoupon($cart);
            }

            $this->cartRepository->save($cart);

            $cart = $this->customerCartResolver->resolve($currentUserId);

            if ($this->salesRuledata->isReferralPromotionApplied) {
                $this->salesRuledata->addLog('Entered the re-check condition');
                $applySpecialPromo = $this->salesRuledata->isSpecialDiscountApply($cart);
                // if ($applySpecialPromo) {
                //     $this->salesRuledata->addLog('Entered the re-check enable condition');
                //     $this->salesRuledata->setIboSpecialPromo($cart);
                //     $cart->setAppliedRuleIds($this->salesRuledata->getSpecialPromoRuleId());
                    // $this->cartRepository->save($cart);
                // }
            }

            $cart = $this->customerCartResolver->resolve($currentUserId);

            if ($this->helper->getPromiseStatus()) {
                // $this->addLog("Fetchcart Entered");
                $items = $cart->getAllVisibleItems();
                if (count($items) > 0) {
                    $this->helper->addLog("===========================", "fetchcart-promise.log");
                    $this->helper->addLog("Entered Fetchcart", "fetchcart-promise.log");
                    $this->helper->addLog("===========================", "fetchcart-promise.log");
                    $payload = "";
                    $postcode = "";
                    $address = "";
                    $fullfilment_triggered = false;
                    if ($cart->getPostalCode()) {
                        $postcode = $cart->getPostalCode();
                    } else {
                        $postcode = $this->helper->getDefaultShippingPostalCode();
                    }
                    // if($postcode){  //If customer is having the default shippingAddress - LoggedIn
                    //     $this->helper->addLog('Got customer postcode '.$postcode, "fetchcart-promise.log");
                    //     $url = $this->helper->getPromiseApi();//'http://35.200.219.97/promise-engine/v1/promise/';
                    //     $payload = $this->helper->CreateJsonRequestData($cart,$postcode);
                    // }else{  //If customer is doesn't have defaul shippingAddress - Guest/No address for loggedIn - Use shipping Pincode
                        $fullfilment_triggered = true;
                        $url = $this->helper->getCartPromiseApi();//'http://35.200.219.97/promise-engine/v1/fulfillment-options';
                        //$getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
                        //$this->helper->addLog('System will fetch Default system postcode:'.$getDefaultShippingPincode, "fetchcart-promise.log");
                        $payload = $this->helper->CreateCartRequestData($cart, $postcode);
                    //}
                    $traceId = $this->helper->getTraceId();
                    $client_id = $this->helper->getClientId();
                    $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                    $this->curl->setOption(CURLOPT_POST, true);
                    $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
                    $this->curl->setHeaders($headers);
                    $this->helper->addLog("FetchCart Curl Initiated with below payload: ", "fetchcart-promise.log");
                    $this->helper->addLog(json_encode($payload), "fetchcart-promise.log");

                    $startTime = microtime(true);
                    $this->helper->addLog("Promise call Start time: ".date("Y-m-d H:i:s").
                        " Micro sec: ".$startTime, "fetchcart-promise.log");

                    $this->curl->post($url, $payload);
                    $result = $this->curl->getBody();
                    $resultData = json_decode($result, true);

                    $endTime = microtime(true);
                    $this->helper->addLog(
                        "Promise call End time: ".date("Y-m-d H:i:s").
                        " Micro sec: ".$endTime. ", Difference in milliseconds:
                        ".number_format($endTime - $startTime, 5)/1000,
                        "fetchcart-promise.log"
                    );

                    $this->helper->addLog("Receive Promise Response Below: ", "fetchcart-promise.log");
                    $this->helper->addLog(json_encode($resultData), "fetchcart-promise.log");
                    $tmp_arr_data = [];

                    if ($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])) {    //Fullment-option API Response (customer doesn't have default shipping address
                        foreach ($resultData['promise_lines'] as $data) {
                            // $data['fulfillable_quantity']['quantity_number'] = 20;
                            $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                            if (($data['fulfillable_quantity'] == null) || ($data['fulfillable_quantity']['quantity_number'] != $data['quantity']['quantity_number'])) {
                                $this->helper->addLog("Fullfillment not satisfied for SKU:".$data['item']['offer_id'], "fetchcart-promise.log");
                            } else {
                                 $this->helper->addLog("Fullfillment satisfied for SKU:".$data['item']['offer_id'], "fetchcart-promise.log");
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
                    //     $this->helper->addLog("Receive promise options:".$promiseOptions, "fetchcart-promise.log");
                    //     $connection->query($sql);

                    //     foreach($data as $deliveryData){
                    //         $delOptionData = $deliveryData['delivery_group_lines'];
                    //         foreach($delOptionData as $delData) {
                    //             $promise_arr_data[$delData['item']['offer_id']] = $delData['fulfillable_quantity'];
                    //             if(($delData['fulfillable_quantity'] == null) || ($delData['fulfillable_quantity']['quantity_number'] != $delData['quantity']['quantity_number'])) {
                    //                 $this->helper->addLog("Promise call not satisfied for sku:".$delData['item']['offer_id'], "fetchcart-promise.log");
                    //             }else{
                    //                 $this->helper->addLog("Promise call satisfied for sku:".$delData['item']['offer_id'], "fetchcart-promise.log");
                    //             }
                    //         }
                    //     }
                    //     foreach ($items as $item) {
                    //         if($item->getProduct_type() == 'simple') {
                    //             if(is_array($promise_arr_data) && array_key_exists($item->getSku(), $promise_arr_data)){
                    //                 $itemData = serialize($promise_arr_data[$item->getSku()]);
                    //                 $item->setAdditionalData($itemData);
                    //                 // $item->save();
                    //             }
                    //         }
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
                    }
                    if (isset($resultData) && isset($resultData['errors'])) {
                        $this->helper->addLog("Product that you are trying to add is not available.", "fetchcart-promise.log");
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
                    }
                    $cart = $this->customerCartResolver->resolve($currentUserId);
                }
            }

        } catch (\Exception $e) {
            $cart = null;
        }

        return [
            'model' => $cart
        ];
    }
}
