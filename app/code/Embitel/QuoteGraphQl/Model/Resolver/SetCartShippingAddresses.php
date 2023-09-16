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
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\SetBillingAddressOnCart as SetBillingAddressOnCartModel;
use Magento\QuoteGraphQl\Model\Cart\SetShippingAddressesOnCartInterface;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Embitel\Quote\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteRepository;
use Magento\QuoteGraphQl\Model\Cart\AssignShippingAddressToCart;
/**
 * Mutation resolver for setting shipping addresses for shopping cart
 */
class SetCartShippingAddresses implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var Embitel\Quote\Helper\Data
     */
    private $helper;

    /**
     * @var SetShippingAddressesOnCartInterface
     */
    private $setShippingAddressesOnCart;

    /**
     * @var CheckCartCheckoutAllowance
     */
    private $checkCartCheckoutAllowance;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var AssignShippingAddressToCart
     */
    private $assignShippingAddress;

    protected $resource;

    /**
     * @param GetCartForUser $getCartForUser
     * @param SetShippingAddressesOnCartInterface $setShippingAddressesOnCart
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        SetShippingAddressesOnCartInterface $setShippingAddressesOnCart, 
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        SetBillingAddressOnCartModel $setBillingAddressOnCart,
        \Magento\Eav\Model\Config $eavConfig,
        CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        Data $helper,
        \Magento\Framework\App\ResourceConnection $resource,
        QuoteRepository $quoteRepository,
        AssignShippingAddressToCart $assignShippingAddress
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->setShippingAddressesOnCart = $setShippingAddressesOnCart;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;
        $this->setBillingAddressOnCart = $setBillingAddressOnCart; 
        $this->customerFactory = $customerFactory; 
        $this->eavConfig = $eavConfig;
        $this->helper = $helper;
        $this->_resource = $resource;
        $this->quoteRepository = $quoteRepository;
        $this->assignShippingAddress = $assignShippingAddress;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['input']['cart_id'];

        if(isset($args['input']['delivery_together'])) {
            $deliveryTogether = $args['input']['delivery_together'];
        } else {
            $deliveryTogether = true;
        }

        if (empty($args['input']['shipping_addresses'])) {
            throw new GraphQlInputException(__('Required parameter "shipping_addresses" is missing'));
        }
        $shippingAddresses = $args['input']['shipping_addresses'];

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $this->checkCartCheckoutAllowance->execute($cart);
        $this->setShippingAddressesOnCart->execute($context, $cart, $shippingAddresses);
        // reload updated cart
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId); 
        $promiseDetails = [];   

        if($cart->getShippingAddress()->getPostcode()) { 
            $postalCode = $cart->getShippingAddress()->getPostcode();
            $cart->setPostalCode($postalCode);
           // $postalCode = '312222';
            $promiseDetails = $this->setPromiseDetails($cart,$postalCode,$deliveryTogether);
            
        }
        //Set default billing address for customers
        if($context->getUserId()) { 
             $customer = $this->customerFactory->create()->load($context->getUserId()); 
             $optionlabel = "";
             if($customer->getTaxvat()) { 
                //Set default billing address for verified GST customers
                if(null!== ($customer->getData('approval_status'))){ 
                    $approvalStatus = $customer->getData('approval_status');  
                    $attribute = $this->eavConfig->getAttribute('customer', "approval_status"); 
                    $optionlabel = $attribute->getSource()->getOptionText($approvalStatus); 
                } 
                if(isset($optionlabel) && strtolower($optionlabel) == "approved") { 
                    //Approved so set alway default billing in cart
                    $billingAddressId = $customer->getDefaultBilling(); 
                    if($billingAddressId > 0) { 
                        $billingAddress['customer_address_id'] = (int)$billingAddressId;
                        $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                    } else {  
                        //set billing same as shipping
                        if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                            $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                            $billingAddress['customer_address_id'] = (int)$billingId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        }
                    } 
                }else{
                    if(!$cart->getBillingAddress()->getFirstName()  && !$cart->getBillingAddress()->getCustomerAddressId()) { 
                        //If cart has no billing fetch default billing and set in cart
                        $billingAddressId = $customer->getDefaultBilling(); 
                        if($billingAddressId){ 
                            if($billingAddressId > 0) { 
                                $billingAddress['customer_address_id'] = (int)$billingAddressId;
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            } else {  
                                //set billing same as shipping
                                if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                    $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                    $billingAddress['customer_address_id'] = (int)$billingId; 
                                    $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                                }
                            } 
                        }else{ 
                            //set billing same as shipping
                            if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                $billingAddress['customer_address_id'] = (int)$billingId; 
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            }
                        }
                    }
                } 
             }else{ 
                //Set default billing address for customer if not set 
                if(!$cart->getBillingAddress()->getFirstName()  && !$cart->getBillingAddress()->getCustomerAddressId()) { //If cart has no billing fetch default billing and set in cart
                    $billingAddressId = $customer->getDefaultBilling(); 
                    if($billingAddressId){ 
                        if($billingAddressId > 0) { 
                            //Get default billing address and set in cart
                            $billingAddress['customer_address_id'] = (int)$billingAddressId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        } else {  
                            //set billing same as shipping
                            if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                $billingAddress['customer_address_id'] = (int)$billingId; 
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            }
                        } 
                    }else{ 
                        //set billing same as shipping
                        if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                            $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                            $billingAddress['customer_address_id'] = (int)$billingId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        }
                    }
                }
             }
        }
        //$shippingAddress = $cart->getShippingAddress();
        //$shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod('flatrate_flatrate'); //shipping method 
        //$cart->save();           
        return [
            'cart' => [
                'model' => $cart,
            ],
            'promise_data' => $promiseDetails
        ];
    }

    private function setPromiseDetails($cart, $pincode,$deliveryTogether){
        if(!$cart->getId()){ 
            $status = 0;
            $pageData['message'] = 'Failed. The Cart has no items';
        } else {        
            $this->helper->addlog('Entered Promise Data Call');
            
            $response = $this->helper->getPromiseData($cart, $pincode,$deliveryTogether);
            
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
                                        $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['carrier_info']['carrier_type'] = $promiseDeliveryInfo['carrier_info']['carrier_type'];
                                        $pageData['delivery_groups'][$key]['promise_options'][$newkey]["promise_delivery_info"][$newKey1]['carrier_info']['is_cod_available'] = $promiseDeliveryInfo['carrier_info']['is_cod_available'];      
                                        
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

                                    // $this->helper->addlog('Promise Selection Input Received from promise call');
                                    // //$promiseSelectionData['promise_id'] = '12345';
                                    // $selectionData = $this->helper->StatusExecute($promiseSelectionData);
                                    // $this->helper->addlog('Executed Promise Selection Call');
                                   
                                    // if(is_array($selectionData) && $selectionData['status'] == 1) {
                                    //     $this->helper->addlog('Promise Selection Call successfully executed');
                                    //     $status = 1;
                                        //To set the shipping Method AS of now the flatrate shipping method is used
                                        $cart->setPromiseOptions($promiseOptionsData);
                                        $cart->setDeliveryGroup($deliveryGroupsData);
                                        $cart->setPromiseId($deliveryData['promise_id']);
                                        $cart->setPromiseCreatedAt($deliveryData['created_at']);
                                        $cart->setPromiseExpiresAt($deliveryData['expires_at']);
                                        // $shippingAddress = $cart->getShippingAddress();
                                        // $shippingAddress->setShippingMethod('flatrate_flatrate');
                                        // $shippingAddress->setCollectShippingRates(true);
                                        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                                        // $this->assignShippingAddress->execute($cart, $shippingAddress);
                                        $this->quoteRepository->save($cart);
                                        // $this->helper->addlog('Delivery Method set via promise call');
                                    // } else {
                                    //     $status = 0;
                                    //     $pageData = $this->removePromiseOptions($pageData);
                                    //     $pageData['message'] = 'Failed. The Promise selection is not successful';
                                    //     $this->helper->addlog('The Promise selection is not successful');
                                    // }
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

                    foreach ($deliveryData['out_of_stock'] as $key => $stockdata) {
                        $pageData['out_of_stock'][$key]['offer_id'] = $stockdata['offer_id'];
                        $pageData['out_of_stock'][$key]['is_courierable'] = $stockdata['is_courierable'];
                        $pageData['out_of_stock'][$key]['reason'] = $stockdata['reason'];
                    }
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

    private function removePromiseOptions($pageData) {
        foreach($pageData['delivery_groups'] as $key => $pageGroupData) {
            $promiseOptions = $pageGroupData['promise_options'];
             foreach ($promiseOptions as $newkey=>$promiseOptions){
                 $pageData['delivery_groups'][$newkey]['promise_options'] = [];
             }
         }
         return $pageData;
    }
}
