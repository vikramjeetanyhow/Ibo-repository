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
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\HTTP\Client\Curl;

/**
 * @inheritdoc
 */
class RemoveItemFromCart implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var MaskedQuoteIdToQuoteId
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var ArgumentsProcessorInterface
     */
    private $argsSelection;

    /**
     * @param GetCartForUser $getCartForUser
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param ArgumentsProcessorInterface $argsSelection
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        CartItemRepositoryInterface $cartItemRepository,
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        ArgumentsProcessorInterface $argsSelection,
        EventManager $eventManager,
        \Embitel\Quote\Helper\Data $helper,
        Curl $curl
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->cartItemRepository = $cartItemRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->argsSelection = $argsSelection;
        $this->_eventManager = $eventManager; 
        $this->helper = $helper;
        $this->curl = $curl;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $processedArgs = $this->argsSelection->process($info->fieldName, $args);
        if (empty($processedArgs['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }
        $maskedCartId = $processedArgs['input']['cart_id'];
        try {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($maskedCartId);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Could not find a cart with ID "%masked_cart_id"', ['masked_cart_id' => $maskedCartId])
            );
        }

        if (empty($processedArgs['input']['cart_item_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_item_id" is missing.'));
        }
        $itemId = $processedArgs['input']['cart_item_id'];

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        try {
            $this->cartItemRepository->deleteById($cartId, $itemId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('The cart doesn\'t contain the item'));
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $this->_eventManager->dispatch('update_shippingcost_time', ['quote' => $cart]);

        if ($this->helper->getPromiseStatus()) {
            // $this->addLog("Fetchcart Entered");
            $items = $cart->getAllVisibleItems();
            if (count($items) > 0) {
                $this->helper->addLog("===========================", "removecart-promise.log");
                $this->helper->addLog("Entered Removecart", "removecart-promise.log");
                $this->helper->addLog("===========================", "removecart-promise.log");
                $payload = "";
                $postcode = "";
                $address = "";
                $fullfilment_triggered = false;
                if ($cart->getPostalCode()) {
                    $postcode = $cart->getPostalCode();
                } else {
                    $postcode = $this->helper->getDefaultShippingPostalCode();
                }
                
                $fullfilment_triggered = true;
                $url = $this->helper->getCartPromiseApi();  
                $payload = $this->helper->CreateCartRequestData($cart, $postcode);
                
                $traceId = $this->helper->getTraceId();
                $client_id = $this->helper->getClientId();
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->setOption(CURLOPT_POST, true);
                $headers = ["Content-Type" => "application/json", "trace_id" => $traceId, "client_id" => $client_id];
                $this->curl->setHeaders($headers);
                $this->helper->addLog("RemoveCart Curl Initiated with below payload: ", "removecart-promise.log");
                $this->helper->addLog(json_encode($payload), "removecart-promise.log");

                $startTime = microtime(true);
                $this->helper->addLog("Promise call Start time: ".date("Y-m-d H:i:s").
                    " Micro sec: ".$startTime, "removecart-promise.log");

                $this->curl->post($url, $payload);
                $result = $this->curl->getBody();
                $resultData = json_decode($result, true);

                $endTime = microtime(true);
                $this->helper->addLog(
                    "Promise call End time: ".date("Y-m-d H:i:s").
                    " Micro sec: ".$endTime. ", Difference in milliseconds:
                    ".number_format($endTime - $startTime, 5)/1000,
                    "removecart-promise.log"
                );

                $this->helper->addLog("Receive Promise Response Below: ", "removecart-promise.log");
                $this->helper->addLog(json_encode($resultData), "removecart-promise.log");
                $tmp_arr_data = [];

                if ($fullfilment_triggered && isset($resultData) && isset($resultData['promise_lines'])) {    //Fullment-option API Response (customer doesn't have default shipping address
                    foreach ($resultData['promise_lines'] as $data) {
                        // $data['fulfillable_quantity']['quantity_number'] = 20;
                        $tmp_arr_data[$data['item']['offer_id']] = $data['fulfillable_quantity'];
                        if (($data['fulfillable_quantity'] == null) || ($data['fulfillable_quantity']['quantity_number'] != $data['quantity']['quantity_number'])) {
                            $this->helper->addLog("Fullfillment not satisfied for SKU:".$data['item']['offer_id'], "removecart-promise.log");
                        } else {
                             $this->helper->addLog("Fullfillment satisfied for SKU:".$data['item']['offer_id'], "removecart-promise.log");
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
                $outOfStockData = [];
                if (isset($resultData) && isset($resultData['out_of_stock']) && count($resultData['out_of_stock']) > 0) {
                    $this->helper->addLog('Products out of stock.'.json_encode($resultData), "removecart-promise.log");
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
                    $this->helper->addLog("Product that you are trying to add is not available.", "removecart-promise.log");
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
                //$cart = $this->customerCartResolver->resolve($currentUserId);
            }
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
