<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

use Magento\Framework\DataObject;

class CreateQuote extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\CreateQuoteInterface
{    
    protected $coupon;
    protected $saleRule; 

    public function __construct( 
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $cartItem,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Customer\Model\Group $group,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\App\RequestInterface $request
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->cartItem = $cartItem;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->quoteFactory = $quoteFactory;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->ruleFactory = $ruleFactory;
        $this->group = $group;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function createQuote() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)) {
                    $result['coupons'] = array();
                    $cartId = $this->cartManagementInterface->createEmptyCart();
                    $quote = $this->cartRepositoryInterface->get($cartId);
                    $quoteId = $quote->getId();
                    
                    $connection = $this->resource->getConnection();
                    $userId = $this->supermaxSession->getPosUserId();
                    $userData = $this->joinUserData($connection, $userId);
                    $user = $connection->query($userData)->fetch();
                    $storeId = !empty($user) ? $user['store_view_id'] : 0;
                    $outletStoreId = !empty($user) ? $user['outlet_store_id'] : "";
                    $storeData = $this->storeManager->getStore($storeId);
                    $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                    $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();

                    $rules = $this->ruleFactory->create()->getCollection()
                        ->addFieldToSelect('*')
                        ->addFieldToFilter("is_active", array('eq'=> "1"));

                    if(isset($params['customer_id']) && $params['customer_id']) {
                        $customer = $this->customerRepository->getById($params['customer_id']);
                        $customerEmail = filter_var($customer->getEmail(), FILTER_VALIDATE_EMAIL) ? $customer->getEmail() : $this->helper->getCustomerCustomEmail($customer->getId());
                        $customer->setEmail($customerEmail);
                        $quote->assignCustomer($customer);
                        $quote->setCustomerIsGuest(0);
                        $rules->addCustomerGroupFilter($customer->getGroupId()); 
                    } else {
                        $existingGroup = $this->group->load('NOT LOGGED IN', 'customer_group_code');
                        $rules->addCustomerGroupFilter($existingGroup->getCustomerGroupId()); 
                        $quote->setCustomerIsGuest(1);
                    }
                    $quote->setIsActive(0);
                    $remoteAddress = $this->remoteAddress->getRemoteAddress();
                    if ($remoteAddress !== false) {
                        $quote->setRemoteIp($remoteAddress);
                        $quote->setXForwardedFor(
                            $this->request->getServer('HTTP_X_FORWARDED_FOR')
                        );
                    }
                    $quote->setStoreId($storeId);
                    $quote->setBaseCurrencyCode($storeBaseCurrencyCode);
                    $quote->setQuoteCurrencyCode($storeCurrencyCode);
                    $quote->setStoreCurrencyCode($storeCurrencyCode);
                    $quote->setChannel("store");
                    $quote->setChannelInfo($outletStoreId);

                    if(isset($params['products']) && !empty($params['products'])) {
                        $quoteItems = $quote->getAllItems();
                        $quoteProductIds = array();

                        if(!empty($quoteItems)) {
                            foreach($quoteItems as $item) {
                                $quoteProductIds[$item->getProductId()] = $item->getItemId();
                            }
                        }

                        $postProductIds = $this->addProductToQuote($params, $quote, $storeCurrencyCode, $storeBaseCurrencyCode, $quoteProductIds);

                        // Delete Quote Items
                        if(!empty($quoteProductIds)) {
                            foreach($quoteProductIds as $key => $value) {
                                if(!in_array($key, $postProductIds)) {
                                    $quoteItem = $quote->getItemById($quoteProductIds[$key]);
                                    $quoteItem->delete();
                                }
                            }
                        }
                    }

                    $quote->collectTotals()->save(); 
                    $result = $this->getQuoteData($quote);
                    $result['coupons'] = array();
                    $salesruleIds = explode(',', $quote->getAppliedRuleIds());
                   
                    $allRules = $rules->getData();
                    if(!empty($allRules)) {
                        foreach($allRules as $rule) {
                            // $couponCode = $rule['code'];
                            // if(!empty($couponCode)) {
                            //     $quote->setCouponCode($couponCode)->collectTotals()->save(); 
                            //     $quoteCouponCode = $quote->getCouponCode();
                            if(in_array($rule['rule_id'], $salesruleIds)) {
                                // if($quoteCouponCode) {
                                $discountType = '';
                                if($rule['simple_action'] == 'by_percent') {
                                    $discountType = 'P';
                                } elseif($rule['simple_action'] == 'by_fixed') {
                                    $discountType = 'F';
                                } elseif($rule['simple_action'] == 'cart_fixed') {
                                    $discountType = 'CF';
                                }
                                $result['coupons'][] = array(
                                    "coupon_id" => $rule['rule_id'],
                                    "code" => $rule['code'],
                                    "name" => $rule['name'],
                                    "description" => $rule['description'],
                                    "type" => $discountType,
                                    "discount" => $rule['discount_amount'],
                                    'sortorder' => $rule['sort_order']
                                );
                                // }
                            }
                            // $quote->setCouponCode('')->collectTotals()->save();
                            // }
                        }
                    }
                    $result['quote_id'] = $quoteId;
                    $result['reserved_order_id'] = $quote->getReservedOrderId();
                    // $quote->delete();
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    public function joinUserData($connection, $userId) {
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'pos_outlet_id', 'store_view_id']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['email', 'source_code', 'outlet_store_id' => 'store_id']
        )->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    public function addProductToQuote($params, $quote, $storeCurrencyCode, $storeBaseCurrencyCode, $quoteProductIds = array()) {
        $postProductIds = array();
        foreach($params['products'] as $productData) { 
            $product = $this->productRepository->getById($productData['productId']);
            $postProductIds[] = $productData['productId'];
            $quoteId = $quote->getId();
            $qty = $productData['quantity'];
            // $originalPrice = (float)$productData['cost_before_disc_without_tax'];
            // $baseOriginalPrice = (float)$this->helper->convert($originalPrice, $storeCurrencyCode, $storeBaseCurrencyCode);
            $itemPrice = (float)$productData['final_cost'];
            $baseItemPrice = (float)$this->helper->convert($itemPrice, $storeCurrencyCode, $storeBaseCurrencyCode);
            // $rowSubTotal = (float)$itemPrice * $qty;
            // $baseRowSubTotal = (float)$this->helper->convert($rowSubTotal, $storeCurrencyCode, $storeBaseCurrencyCode);
            $rowTax = $baseRowTax = $rowTaxPercent = 0;

            if(isset($productData['tax'])) {
                $rowTaxPercent = $productData['tax_percent'];
            }

            $quoteItem = array_key_exists($productData['productId'], $quoteProductIds) ? $quote->getItemById($quoteProductIds[$productData['productId']]) : $this->cartItem->create();

            $quoteItem->setProductId($product->getId())
                ->setSku($product->getSku())
                ->setProductType($product->getTypeId())
                ->setName($product->getName())
                ->setQuoteId($quoteId)
                ->setQty($qty)
                ->setPrice($itemPrice)
                ->setBasePrice($baseItemPrice)
                ->setTaxPercent($rowTaxPercent)
                ->setVendorId($productData['seller_id']);

            if(array_key_exists($productData['productId'], $quoteProductIds)) {
                $quoteItem->save();
            } else {
                $quote->addItem($quoteItem); 
            }
        }

        return $postProductIds;
    }

    private function getQuoteData($quote) {
        $quoteId = $quote->getId();
        $data = array(
            "items_count" => $quote->getItemsCount(),
            "items_qty" => $quote->getItemsQty(),
            "grand_total" => $quote->getGrandTotal(),
            "base_grand_total" => $quote->getBaseGrandTotal(),
            "applied_rule_ids" => $quote->getAppliedRuleIds(),
            "reserved_order_id" => $quote->getReservedOrderId(),
            "subtotal" => $quote->getSubtotal(),
            "base_subtotal" => $quote->getBaseSubtotal(),
            "subtotal_with_discount" => $quote->getSubtotalWithDiscount(),
            "base_subtotal_with_discount" => $quote->getbaseSubtotalWithDiscount(),
            "quote_id" => $quoteId,
        );
        $connection = $this->resource->getConnection();
        $quoteItemTable = $this->resource->getTableName('quote_item');
        $items = $connection->query("SELECT * FROM $quoteItemTable WHERE quote_id = '" . (int)$quoteId . "'")->fetchAll();
        if(!empty($items)) {
            foreach ($items as $key => $item) {
                $data['items'][$key] = array(
                    "item_id" => (int)$item['item_id'],
                    "product_id" => (int)$item['product_id'],
                    "applied_rule_ids" => $item['applied_rule_ids'],
                    "qty" => (int)$item['qty'],
                    "price" => (float)$item['price'],
                    "base_price" => (float)$item['base_price'],
                    "discount_percent" => (float)$item['discount_percent'],
                    "discount_amount" => (float)$item['discount_amount'],
                    "base_discount_amount" => (float)$item['base_discount_amount'],
                    "tax_percent" => (float)$item['tax_percent'],
                    "tax_amount" => (float)$item['tax_amount'],
                    "base_tax_amount" => (float)$item['base_tax_amount'],
                    "row_total" => (float)$item['row_total'],
                    "base_row_total" => (float)$item['base_row_total'],
                    "row_total_with_discount" => (float)$item['row_total_with_discount'],
                    "base_tax_before_discount" => (float)$item['base_tax_before_discount'],
                    "tax_before_discount" => (float)$item['tax_before_discount'],
                    "base_cost" => (float)$item['base_cost'],
                    "price_incl_tax" => (float)$item['price_incl_tax'],
                    "base_price_incl_tax" => (float)$item['base_price_incl_tax'],
                    "row_total_incl_tax" => (float)$item['row_total_incl_tax'],
                    "base_row_total_incl_tax" => (float)$item['base_row_total_incl_tax']
                );
            }
        } else {
            $data['items'] = array();
        }

        return $data;
    }

    // private function updateQuoteChannel($quoteId) {
    //     $connection = $this->resource->getConnection();
    //     $quoteTable = $this->resource->getTableName('quote');
    //     $connection->query("UPDATE $quoteTable set channel='STORE' WHERE entity_id = '" . (int)$quoteId . "'");
    // }
}
