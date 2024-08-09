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

class Coupon extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\CouponInterface
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
        \Magento\Customer\Model\Group $group
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
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function coupons()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)) {
                    $connection = $this->resource->getConnection();
                    $userId = $this->supermaxSession->getPosUserId();
                    $userData = $this->joinUserData($connection, $userId);
                    $user = $connection->query($userData)->fetch();
                    $storeId = !empty($user) ? $user['store_view_id'] : 0;
                    $storeData = $this->storeManager->getStore($storeId);
                    $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                    $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                    $couponCode = $params['coupon'];

                    if(isset($params['quote_id']) && $params['quote_id']) {
                        $quote = $this->quoteFactory->create()->load($params['quote_id']);
                    } else {
                        $cartId = $this->cartManagementInterface->createEmptyCart();
                        $quote = $this->cartRepositoryInterface->get($cartId);
                    }
                    $quoteId = $quote->getId();

                    $quote->setStoreId($storeId);
                    $quote->setBaseCurrencyCode($storeBaseCurrencyCode);
                    $quote->setQuoteCurrencyCode($storeCurrencyCode);
                    $quote->setStoreCurrencyCode($storeCurrencyCode);
                    
                    foreach($params['products'] as $productData) { 
                        $product = $this->productRepository->getById($productData['productId']);

                        $qty = $productData['quantity'];
                        $originalPrice = (float)$productData['cost_before_disc_without_tax'];
                        $baseOriginalPrice = (float)$this->helper->convert($originalPrice, $storeCurrencyCode, $storeBaseCurrencyCode);
                        $itemPrice = (float)$productData['final_cost'];
                        $baseItemPrice = (float)$this->helper->convert($itemPrice, $storeCurrencyCode, $storeBaseCurrencyCode);
                        $rowSubTotal = (float)$itemPrice * $qty;
                        $baseRowSubTotal = (float)$this->helper->convert($rowSubTotal, $storeCurrencyCode, $storeBaseCurrencyCode);
                        $rowTax = $baseRowTax = $rowTaxPercent = 0;

                        if(isset($productData['tax'])) {
                            $rowTax = (float)$productData['tax'] * $qty;
                            $baseRowTax = (float)$this->helper->convert($rowTax, $storeCurrencyCode, $storeBaseCurrencyCode);
                            $rowTaxPercent = $productData['tax_percent'];
                        }

                        $rowTotal = (float)$productData['final_price_without_formated'];
                        $baseRowTotal = (float)$this->helper->convert($rowTotal, $storeCurrencyCode, $storeBaseCurrencyCode);
                        
                        $quoteItem = $this->cartItem->create();                  
                        $quoteItem->setProductId($product->getId())
                            ->setSku($product->getSku())
                            ->setProductType($product->getTypeId())
                            ->setName($product->getName())
                            ->setQuoteId($quoteId)
                            ->setQty($qty)
                            ->setPrice($itemPrice)
                            ->setBasePrice($baseItemPrice)
                            ->setTaxPercent($rowTaxPercent)
                            ->setTaxAmount($rowTax)
                            ->setBaseTaxAmount($baseRowTax)
                            ->setRowTotal($rowSubTotal)
                            ->setBaseRowTotal($baseRowSubTotal)
                            ->setPriceInclTax($rowTotal)
                            ->setBasePriceInclTax($baseRowTotal)
                            ->setRowTotalInclTax($rowTotal)
                            ->setBaseRowtotalInclTax($baseRowTotal);
                        $quote->addItem($quoteItem);
                    }
                   
                    $quoteSubtotal = (float)$params['sub_total'];
                    $baseQuoteSubtotal = (float)$this->helper->convert($quoteSubtotal, $storeCurrencyCode, $storeBaseCurrencyCode);

                    $quoteGrandtotal = (float)$params['total'];
                    $baseQuoteGrandtotal = (float)$this->helper->convert($quoteGrandtotal, $storeCurrencyCode, $storeBaseCurrencyCode);

                    $quote->setSubtotal($quoteSubtotal);
                    $quote->setBaseSubtotal($baseQuoteSubtotal);
                    $quote->setGrandTotal($quoteGrandtotal);
                    $quote->setBaseGrandTotal($baseQuoteGrandtotal);
                    $quote->setCouponCode($couponCode)->collectTotals()->save(); 
                    $quoteCouponCode = $quote->getCouponCode();
                    
                    if($quoteCouponCode) {
                        $couponData = $this->getCouponDetails($connection, $couponCode);
                        if($couponData) {
                            $discountType = '';
                            if($couponData['simple_action'] == 'by_percent') {
                                $discountType = 'P';
                            } elseif($couponData['simple_action'] == 'by_fixed') {
                                $discountType = 'F';
                            } elseif($couponData['simple_action'] == 'cart_fixed') {
                                $discountType = 'CF';
                            }
                            $result['couponInfo'] = array(
                                "coupon_id" => $couponData['rule_id'],
                                "code" => $couponData['code'],
                                "name" => $couponData['name'],
                                "type" => $discountType,
                                "discount" => $couponData['discount_amount'],
                                "shipping" => "0",
                                "total" => "0.0000",
                                "product" => []
                            );
                        }
                    } else {
                        $error = true;
                    }
                    $result['quote_id'] = $quoteId;
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

    // To get storeId, outletEmail and outletSourceCode.
    public function joinUserData($connection, $userId)
    {
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'pos_outlet_id', 'store_view_id']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['email', 'source_code']
        )->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    public function getCouponDetails($connection, $couponCode){
        $select = $connection->select();
        $select->from(
            ['sr' => $this->resource->getTableName('salesrule')],
            ['rule_id', 'name', 'simple_action', 'discount_amount']
        )->joinLeft(
            ['sc' => $this->resource->getTableName('salesrule_coupon')],
            "sr.rule_id = sc.rule_id",
            ['code']
        )->where("sc.code = '$couponCode'");

        $couponData = $connection->query($select)->fetch();
        
        return $couponData;
    }

}
