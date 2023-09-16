<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Embitel\Quote\Helper\Data;
//use Embitel\QuoteGraphQl\Model\Resolver\DataProvider\PromotionList;

class PromiseInventory implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    private $helper;
    
    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        Data $helper
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->helper = $helper;
    }
    
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if(!isset($args['page_type_id'])) {
            throw new GraphQlInputException(__('page_type_id should be specified'));
        }      
        if ($args['page_type_id'] == 0) {
            if((!isset($args['product_sku']) || $args['product_sku'] == "") || (!isset($args['pincode']) || $args['pincode'] =="")){
                 throw new GraphQlInputException(__('product_sku and pincode should be specified'));
             }
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $productSku = $args['product_sku'];
            $status = 0;
            $response = $this->helper->productQuantityCheck($productSku,$args['pincode']);
            if(is_array($response) && $response['status'] == 1) { 
                $deliveryData = $response['data']['promise_lines'][0];
                $promiseOptions = (isset($deliveryData['promise_options']))?$deliveryData['promise_options']:[];
                if(($deliveryData['fulfillable_quantity'] == null) || ($deliveryData['fulfillable_quantity']['quantity_number'] != $deliveryData['quantity']['quantity_number'])) {
                    $status = 0;
                }else{
                    $status = 1;
                }
                if(count($promiseOptions) >0 ){
                    foreach ($promiseOptions as $key=>$promiseOption){
                        $pageData['promise_options'][$key]["delivery_method"] = $promiseOptions[$key]['delivery_method'];
                        $pageData['promise_options'][$key]["delivery_option"] = $promiseOptions[$key]['delivery_option'];
                        $pageData['promise_options'][$key]["node_id"] = $promiseOptions[$key]['node_id'];
//                        $pageData['promise_options'][$key]["promise_delivery_info"][]['is_selected']=true;
                       if(isset($promiseOptions[$key]["promise_delivery_info"])){
//                           $tmpCnt = 0;
                           foreach($promiseOptions[$key]["promise_delivery_info"] as $newKey => $promiseDeliveryInfo){
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['is_selected'] = $promiseDeliveryInfo['is_selected'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_id'] = $promiseDeliveryInfo['delivery_slot']['slot_id'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_type'] = $promiseDeliveryInfo['delivery_slot']['slot_type'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['from_date_time'] = $promiseDeliveryInfo['delivery_slot']['from_date_time'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['to_date_time'] = $promiseDeliveryInfo['delivery_slot']['to_date_time'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['currency'] = $promiseDeliveryInfo['delivery_cost']['currency'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['cent_amount'] = $promiseDeliveryInfo['delivery_cost']['cent_amount'];  
                             $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['fraction'] = $promiseDeliveryInfo['delivery_cost']['fraction'];  
//                             $tmpCnt++;
                           }
                       }
                    }
                }
            } else {
                $status = 0;
            }
        }
        if ($args['page_type_id'] == 1) {
             if((!isset($args['cart_id']) || $args['cart_id'] == "") || (!isset($args['pincode']) || $args['pincode'] =="")){
                 throw new GraphQlInputException(__('cart_id and pincode should be specified'));
             }
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $maskedCartId = $args['cart_id'];
            $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
            $status = 0;

            if($cart->getId()){
                $response = $this->helper->cartQuantityCheck($cart,$args['pincode']); 
                if(is_array($response) && $response['status'] == 1) {                   
                    $items = $cart->getAllItems();
                    $status = 1; 
                    foreach ($items as $item) { 
                        if($item->getEboInventoryFlag() == 0) { 
                            $status = 0;
                            break;
                        }                
                    } 
                } else {
                    $status = 0;
                }
                if($status == 1){
                    $deliveryData = $response['data']['promise_lines'][0];
                    $promiseOptions = (isset($deliveryData['promise_options']))?$deliveryData['promise_options']:[];
                    if(count($promiseOptions) >0 ){
                        foreach ($promiseOptions as $key=>$promiseOption){
                            $pageData['promise_options'][$key]["delivery_method"] = $promiseOptions[$key]['delivery_method'];
                            $pageData['promise_options'][$key]["delivery_option"] = $promiseOptions[$key]['delivery_option'];
                            $pageData['promise_options'][$key]["node_id"] = $promiseOptions[$key]['node_id'];
    //                        $pageData['promise_options'][$key]["promise_delivery_info"][]['is_selected']=true;
                           if(isset($promiseOptions[$key]["promise_delivery_info"])){
    //                           $tmpCnt = 0;
                               foreach($promiseOptions[$key]["promise_delivery_info"] as $newKey => $promiseDeliveryInfo){
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['is_selected'] = $promiseDeliveryInfo['is_selected'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_id'] = $promiseDeliveryInfo['delivery_slot']['slot_id'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_type'] = $promiseDeliveryInfo['delivery_slot']['slot_type'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['from_date_time'] = $promiseDeliveryInfo['delivery_slot']['from_date_time'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['to_date_time'] = $promiseDeliveryInfo['delivery_slot']['to_date_time'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['currency'] = $promiseDeliveryInfo['delivery_cost']['currency'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['cent_amount'] = $promiseDeliveryInfo['delivery_cost']['cent_amount'];  
                                 $pageData['promise_options'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['fraction'] = $promiseDeliveryInfo['delivery_cost']['fraction'];  
    //                             $tmpCnt++;
                               }
                           }
                        }
                    } 
                }
            }else{
                throw new GraphQlInputException(__('Cart is not available.'));
            }             
        }  

        if($status == 0){
            $pageData['status'] = 0;
            $pageData['message'] = "Items are not deliverables to ".$args['pincode'].". Please try changing the address";   
        }else{
            $pageData['status'] = 1;
            $pageData['message'] = "Items are deliverables to ".$args['pincode'].".";   
        }

        return $pageData;
    }

}
