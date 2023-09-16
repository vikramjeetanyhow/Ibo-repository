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

class PromiseData implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    private $helper;

    protected $resource;
    
    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        Data $helper,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->helper = $helper;
        $this->_resource = $resource;
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
           
            if((!isset($args['cart_id']) || $args['cart_id'] == "") || (!isset($args['pincode']) || $args['pincode'] =="")){
                 throw new GraphQlInputException(__('cart_id and pincode should be specified'));
             }
            
            $cartId = $args['cart_id'];
            $status = 0;
            
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $maskedCartId = $args['cart_id'];
            $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

            if(!$cart->getId()){ 
                $status = 0;
                $pageData['message'] = 'Failed. The Cart has no items';
            } else {        
                $this->helper->addlog('Entered Promise Data Call');
                
                //To set the shipping Method AS of now the flatrate shipping method is used
                $shippingAddress = $cart->getShippingAddress();
                $shippingAddress->setShippingMethod(null);
                $shippingAddress->setCollectShippingRates(true);
                $cart->save(); 
                $this->helper->addlog('Entered Promise Data Call and unset shipping method'); 
                
                $response = $this->helper->getPromiseData($cart,$args['pincode']);
                
                if(is_array($response) && $response['status'] == 1) { 
                    $this->helper->addlog('Promise Data v1/promise Response Got');
                    $status = 1;
                    $deliveryData = $response['data'];
                
                    $pageData['promise_id'] = $deliveryData['promise_id'];
                    $pageData['expires_at'] = $deliveryData['expires_at'];
                    $pageData['created_at'] = $deliveryData['created_at'];

                    $promiseSelectionData = [];
                    $promiseSelectionData['promise_id'] = $deliveryData['promise_id'];
                    $promiseSelectionData['cart_id'] = $cart->getId();
                    $promiseSelectionData['order_number'] = ($cart->getReservedOrderId())?$cart->getReservedOrderId():$cart->getId();
                    //$deliveryData['delivery_groups'] = null;
                    if(isset($deliveryData['delivery_groups'])) {
                        $deliverygroupdata = $deliveryData['delivery_groups'];
                        $deliveryGroupsData = (isset($deliverygroupdata))?json_encode($deliverygroupdata):null;
                        foreach($deliveryData['delivery_groups'] as $key => $deliveryGroupData) {
                            $pageData['delivery_groups'][$key]['delivery_group_id'] = $deliveryGroupData['delivery_group_id'];
                            $promiseSelectionData['delivery_group_id'] = $deliveryGroupData['delivery_group_id'];

                            $deliveryGrouplines = $deliveryGroupData['delivery_group_lines'];
                            if(count($deliveryGrouplines) >0 ){
                                foreach($deliveryGrouplines as $deliveryKey=>$deliveryGrouplines) {
                                    //print_r($deliveryGrouplines['delivery_group_line_id']); exit;
                                    $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['delivery_group_line_id'] = $deliveryGrouplines['delivery_group_line_id'];
                                    $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['promise_line_id'] = $deliveryGrouplines['promise_line_id'];
                                    $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['parent_promise_line_id'] = $deliveryGrouplines['parent_promise_line_id'];
                                    if(isset($deliveryGrouplines['item'])) { //echo 'j';print_r($deliveryGrouplines['item']['offer_id']); exit;
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['offer_id'] = $deliveryGrouplines['item']['offer_id'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['service_category'] = $deliveryGrouplines['item']['service_category'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['category_id'] = $deliveryGrouplines['item']['category_id'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['category_code'] = $deliveryGrouplines['item']['category_id'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['category_id'] = $deliveryGrouplines['item']['category_id'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['item']['requires_shipping'] = $deliveryGrouplines['item']['requires_shipping'];
                                    }
                                    if(isset($deliveryGrouplines['quantity'])) { //echo 'j';print_r($deliveryGrouplines['item']['offer_id']); exit;
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['quantity']['quantity_number'] = $deliveryGrouplines['quantity']['quantity_number'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['quantity']['quantity_uom'] = $deliveryGrouplines['quantity']['quantity_uom'];
                                    }
                                    if(isset($deliveryGrouplines['fulfillable_quantity'])) { //echo 'j';print_r($deliveryGrouplines['item']['offer_id']); exit;
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['fulfillable_quantity']['quantity_number'] = $deliveryGrouplines['fulfillable_quantity']['quantity_number'];
                                        $pageData['delivery_groups'][$key]['delivery_group_lines'][$deliveryKey]['fulfillable_quantity']['quantity_uom'] = $deliveryGrouplines['fulfillable_quantity']['quantity_uom'];
                                    }
                                }

                            }

                            $promiseOptions = $deliveryGroupData['promise_options'];
                            

                            if(count($promiseOptions) >0 ){ 

                                $promiseOptionsData = (isset($promiseOptions))?json_encode($promiseOptions):null;    
                                $connection = $this->_resource->getConnection();
                                $tableName = $this->_resource->getTableName('quote');
                                $sql = "update ".$tableName." set promise_options='".$promiseOptionsData."', delivery_group='".$deliveryGroupsData."', promise_id ='".$deliveryData['promise_id']."', promise_created_at ='".$deliveryData['created_at']."', promise_expires_at ='".$deliveryData['expires_at']."' where entity_id = ".$cart->getId();
                                $connection->query($sql);
                                $this->helper->addlog('Updated v1/promise promise options on Quote table');

                                foreach ($promiseOptions as $newkey=>$promiseOption){
                                    $pageData['delivery_groups'][$key]['promise_options'][$newkey]["delivery_method"] = $promiseOptions[$newkey]['delivery_method'];
                                    $pageData['delivery_groups'][$key]['promise_options'][$newkey]["delivery_option"] = $promiseOptions[$newkey]['delivery_option'];
                                    $pageData['delivery_groups'][$key]['promise_options'][$newkey]["node_id"] = $promiseOptions[$newkey]['node_id'];
                                    $promiseSelectionData['delivery_method'] = $promiseOptions[$newkey]['delivery_method'];
                                    $promiseSelectionData['delivery_option'] = $promiseOptions[$newkey]['delivery_option'];
                                    $promiseSelectionData['node_id'] = $promiseOptions[$newkey]['node_id'];

                                    if(isset($promiseOptions[$newkey]["promise_delivery_info"])){
                                        foreach($promiseOptions[$newkey]["promise_delivery_info"] as $newKey1 => $promiseDeliveryInfo){
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['is_selected'] = $promiseDeliveryInfo['is_selected'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_slot']['slot_id'] = $promiseDeliveryInfo['delivery_slot']['slot_id'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_slot']['slot_type'] = $promiseDeliveryInfo['delivery_slot']['slot_type'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_slot']['from_date_time'] = $promiseDeliveryInfo['delivery_slot']['from_date_time'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_slot']['to_date_time'] = $promiseDeliveryInfo['delivery_slot']['to_date_time']; 
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_slot']['estimated_shipping_date'] = $promiseDeliveryInfo['delivery_slot']['estimated_shipping_date'];   
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_cost']['currency'] = $promiseDeliveryInfo['delivery_cost']['currency'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_cost']['cent_amount'] = $promiseDeliveryInfo['delivery_cost']['cent_amount'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['delivery_cost']['fraction'] = $promiseDeliveryInfo['delivery_cost']['fraction'];  
                                            $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['carrier_info']['operator_id'] = $promiseDeliveryInfo['carrier_info']['operator_id'];   
                                            
                                            $promiseSelectionData['slot_id'] = $promiseDeliveryInfo['delivery_slot']['slot_id'];
                                        }
                                    }
                                }

                                // To set the promise selection
                                if ((isset($promiseSelectionData['promise_id']) && $promiseSelectionData['promise_id'] != "")
                                    && (isset($promiseSelectionData['cart_id']) && $promiseSelectionData['cart_id'] != "")
                                    && (isset($promiseSelectionData['delivery_group_id']) && $promiseSelectionData['delivery_group_id'] != "")
                                    && (isset($promiseSelectionData['slot_id']) && $promiseSelectionData['slot_id'] != "")
                                    && (isset($promiseSelectionData['node_id']) && $promiseSelectionData['node_id'] != "")
                                    && (isset($promiseSelectionData['delivery_method']) && $promiseSelectionData['delivery_method'] != "")
                                    && (isset($promiseSelectionData['delivery_option']) && $promiseSelectionData['delivery_option'] != "")) {

                                        $this->helper->addlog('Promise Selection Input Received from promise call');
                                        //$promiseSelectionData['promise_id'] = '12345';
                                        $selectionData = $this->helper->StatusExecute($promiseSelectionData);
                                        $this->helper->addlog('Executed Promise Selection Call');
                                       
                                        if(is_array($selectionData) && $selectionData['status'] == 1) {
                                            $this->helper->addlog('Promise Selection Call successfully executed');
                                            $status = 1;
                                            //To set the shipping Method AS of now the flatrate shipping method is used
                                            $shippingAddress = $cart->getShippingAddress();
                                            $shippingAddress->setShippingMethod('flatrate_flatrate');
                                            $shippingAddress->setCollectShippingRates(true);
                                            $cart->save(); 
                                            $this->helper->addlog('Delivery Method set via promise call');
                                        } else {
                                            $status = 0;
                                            $pageData = $this->removePromiseOptions($pageData);
                                            $pageData['message'] = 'Failed. The Promise selection is not successful';
                                            $this->helper->addlog('The Promise selection is not successful');
                                        }
                                    } else {
                                        $status = 0;
                                        $pageData = $this->removePromiseOptions($pageData);
                                        $pageData['message'] = 'Failed. The Input for the Promise selection is missing and not successful';
                                        $this->helper->addlog('The input for the Promise selection is not received from promise call');
                                    }
                            }

                        }
                    } else {
                        $status = 0;
                        $pageData['message'] = "Failed. Delivery group is null";
                    }

                    if(isset($deliveryData) && isset($deliveryData['out_of_stock']) && count($deliveryData['out_of_stock']) > 0){ 
                        $status = 0;
                        $pageData['message'] = "Failed. Product out of stock";
                    }

                } else {
                    $status = 0;
                    $pageData['message'] = "Failed. Pincode not serviceable or Promise API call error";
                    $this->helper->addlog('Failed. Pincode not serviceable or Promise API call error');
                }
            }
            
        if($status == 0){
            $pageData['status'] = 0;  
        }else{
            $pageData['status'] = 1;
            $pageData['message'] = "Success";   
        }

        return $pageData;
    }

    public function removePromiseOptions($pageData) {
        foreach($pageData['delivery_groups'] as $key => $pageGroupData) {
            $promiseOptions = $pageGroupData['promise_options'];
             foreach ($promiseOptions as $newkey=>$promiseOptions){
                 $pageData['delivery_groups'][$newkey]['promise_options'] = [];
             }
         }
         return $pageData;
    }

}
