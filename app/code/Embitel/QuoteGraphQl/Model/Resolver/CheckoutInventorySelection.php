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

class CheckoutInventorySelection implements ResolverInterface
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
        
        if ((!isset($args['cart_id']) || $args['cart_id'] == "")
        || (!isset($args['pincode']) || $args['pincode'] =="")
        || (!isset($args['promise_id']) || $args['promise_id'] =="")
        || (!isset($args['slot_id']) || $args['slot_id'] =="")
        || (!isset($args['node_id']) || $args['node_id'] =="")
        || (!isset($args['delivery_group_id']) || $args['delivery_group_id'] =="")) {
            throw new GraphQlInputException(__('cart_id, pincode, promise Id, slot id, node id, delivery group id should be specified'));
        }
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $maskedCartId = $args['cart_id'];
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $status = 0;
        if ($cart->getId()) {
            $response = $this->helper->processstatusCheck($cart, $args);
            if (is_array($response) && $response['status'] == 1) {
                $pageData['message'] = "success";
                foreach ($response['data']['delivery_groups'] as $deliveryData) {
                    $deliveryData = $response['data']['delivery_groups'][0];
                    $promiseOptions = $deliveryData['promise_options'];
                    if (count($promiseOptions) >0) {
                        foreach ($promiseOptions as $key => $promiseOption) {
                            $pageData['promise_message'][$key]["delivery_method"] = $promiseOptions[$key]['delivery_method'];
                            $pageData['promise_message'][$key]["delivery_option"] = $promiseOptions[$key]['delivery_option'];
                            $pageData['promise_message'][$key]["node_id"] = $promiseOptions[$key]['node_id'];
                          //$pageData['promise_message'][$key]["promise_delivery_info"][]['is_selected']=true;
                            if (isset($promiseOptions[$key]["promise_delivery_info"])) {
                                foreach ($promiseOptions[$key]["promise_delivery_info"] as $newKey => $promiseDeliveryInfo) {
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['is_selected'] = $promiseDeliveryInfo['is_selected'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_id'] = $promiseDeliveryInfo['delivery_slot']['slot_id'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['slot_type'] = $promiseDeliveryInfo['delivery_slot']['slot_type'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['from_date_time'] = $promiseDeliveryInfo['delivery_slot']['from_date_time'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_slot']['to_date_time'] = $promiseDeliveryInfo['delivery_slot']['to_date_time'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['currency'] = $promiseDeliveryInfo['delivery_cost']['currency'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['cent_amount'] = $promiseDeliveryInfo['delivery_cost']['cent_amount'];
                                    $pageData['promise_message'][$key]["promise_delivery_info"][$newKey]['delivery_cost']['fraction'] = $promiseDeliveryInfo['delivery_cost']['fraction'];
 //                             $tmpCnt++;
                                }
                            }
                       
                        }
                    
                    }
                }
            }
            
        } else {
            $pageData['message'] = "failure";
            $pageData['promise_id'] = "Cart is not available.";
            return $pageData;
        }
        return $pageData;
    }
}
