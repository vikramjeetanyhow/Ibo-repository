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
use Magento\Quote\Api\CartManagementInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @inheritdoc
 */
class PlaceOrder implements ResolverInterface
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckCartCheckoutAllowance
     */
    private $checkCartCheckoutAllowance;

    protected $scopeConfig;

    /**
     * @param GetCartForUser $getCartForUser
     * @param CartManagementInterface $cartManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CartManagementInterface $cartManagement,
        OrderRepositoryInterface $orderRepository,
        CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        ScopeConfigInterface $scopeConfig,
        \Embitel\Quote\Helper\Data $helper
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->cartManagement = $cartManagement;
        $this->orderRepository = $orderRepository;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;
        $this->_scopeConfig = $scopeConfig;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }

        if (empty($args['input']['orderChannelInfo'])) {
            throw new GraphQlInputException(__('Required parameter "orderChannelInfo" is missing'));
        }
            $maskedCartId = $args['input']['cart_id'];

            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
            $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

            //If shipping method is missing then update before order place.
            $shippingAddress = $cart->getShippingAddress();
            try {
                if ($shippingAddress && !$shippingAddress->getShippingMethod()) {
                    $this->helper->addLog('Shipping method is missing so updating before order place: '.$cart->getId());
                    $shippingAddress->setShippingMethod('flatrate_flatrate');
                    $shippingAddress->save();
                }
            } catch (LocalizedException $e) {
                throw new GraphQlInputException(__("Error while updating custom shipping method during order place."));
            }

            $this->checkCartCheckoutAllowance->execute($cart);

            if ((int)$context->getUserId() === 0) {
                if (!$cart->getCustomerEmail()) {
                    throw new GraphQlInputException(__("Guest email for cart is missing."));
                }
                $cart->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
            }

        try {

            //To check for POD limit if payment is POD
            $podlimit = $this->_scopeConfig->getValue('payment/cashondelivery/max_order_total');
            $podlimit = (isset($podlimit)) ? $podlimit : 0;
            if(($cart->getPayment()->getMethod() == 'cashondelivery') && ($cart->getGrandTotal() > $podlimit) && ($podlimit > 0)) {
                throw new GraphQlInputException(__("Order Total exceeded the POD limit."));
            }

            $orderId = $this->cartManagement->placeOrder($cart->getId());
            $order = $this->orderRepository->get($orderId);

            $deliveryGroup = json_decode($order->getDelivery_group(), true);
            $deliverGroupLine = [];
            if (!(empty($deliveryGroup))) {
                foreach ($deliveryGroup as $newkey => $delivery) {
                    foreach($delivery['delivery_group_lines'] as $deliveygroup_line){
                        $offerId = $deliveygroup_line['item']['offer_id'];
                        $deliverGroupLine[$offerId] = isset($deliveygroup_line['promise_line_id']) ? $deliveygroup_line['promise_line_id'] : '';
                    }

                }
            }

            $this->helper->addLog('OrderID: '.$order->getId());
            $this->helper->addLog('OrderChannelInfo: '.$args['input']['orderChannelInfo']);

            if($order->getOrderChannel() != '') {
                $order->setOrderChannel($order->getOrderChannel());
            } else {
                $order->setOrderChannel('ONLINE');
            }
            $order->setOrderChannelInfo($args['input']['orderChannelInfo']);
            if (isset($args['input']['executive_id'])) {
                $order->setExecutiveId($args['input']['executive_id']);
            }
            $order->save();

            $items = array();
            foreach ($order->getAllVisibleItems() as $key=> $item) {
                $items[$key]["id"] = $item->getItemId();
                $items[$key]["sku"] = $item->getSku();
                $items[$key]["product_sale_price"]['value'] = ($item->getDiscountAmount())?(($item->getRowTotal() + $item->getTaxAmount())-$item->getDiscountAmount()):$item->getRowTotalInclTax();
                $items[$key]["quantity_ordered"] = $item->getQtyOrdered();
                $items[$key]["order_line_number"] = !empty($deliverGroupLine) ?  $deliverGroupLine[$item->getSku()] : '';
            }
            //if($order->getPayment()->getMethod() == 'prepaid') {
                $newOrderStatus = $this->_scopeConfig->getValue('payment/prepaid/order_status');
                $order->setState($newOrderStatus);
                $order->setStatus($newOrderStatus);
                $order->addStatusToHistory($order->getStatus(), 'Order status updated to pending_payment');
                $order->save();
            //}

            // if($order->getPayment()->getMethod() == 'cashondelivery' || $order->getPayment()->getMethod() == 'free') {
            //     $orderId = $order->getId();
            //     $this->helper->addLog('Enter Order Push via Place order');
            //     $this->helper->SuccessOrderExecute($orderId);
            // }

            return [
                'order' => [
                    'order_number' => $order->getIncrementId(),
                    // @deprecated The order_id field is deprecated, use order_number instead
                    'order_id' => $order->getIncrementId(),
                    'items' => $items
                ],
            ];

        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__('Unable to place order: %message', ['message' => $e->getMessage()]), $e);
        }
    }
}
