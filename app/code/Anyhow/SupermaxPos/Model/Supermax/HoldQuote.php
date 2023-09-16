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

class HoldQuote implements \Anyhow\SupermaxPos\Api\Supermax\HoldQuoteInterface
{
    protected $model;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Model\Supermax\FetchQuote $fetchQuote,
        \Magento\Catalog\Model\Product $productData,
        \Anyhow\SupermaxPos\Model\Supermax\Data\AllProducts $allProducts,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
        $this->quoteFactory = $quoteFactory;
        $this->supermaxSession = $supermaxSession;
        $this->fetchQuote = $fetchQuote;
        $this->productData = $productData;
        $this->allProducts = $allProducts;
        $this->timezone = $timezone;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
    public function updateHold()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(isset($params['quote_id'])) {
                    $connection = $this->resource->getConnection();
                    $userId = $this->supermaxSession->getPosUserId();
                    $quoteId = $params['quote_id'];
                    $comment = $params['comment'];
                    $status = isset($params['status']) ? $params['status'] : 0;
                    $terminalId = $params['terminal_id'];
                    $userData = $this->getUserData($userId, $terminalId);
                    $outletId = $userData['pos_outlet_id'];
                    $addData = array();
                    $holdAdditionalData = array(
                        "hold_time" => $this->timezone->date(new \DateTime((strtotime(time()))))->format('Y-m-d h:i:s A'),
                        "hold_user_id" => $userData['pos_user_id'],
                        "hold_store_id" => $userData['store_id'],
                        "hold_terminal" => $userData['pos_terminal_code'],
                        "hold_reason_id" => $params['pos_price_reduction_id'],
                        "hold_reason_code" => $params['reason_title'],
                        "hold_comment" => $comment,
                        "unhold_time" => "",
                        "unhold_user_id" => "",
                        "unhold_store_id" => "",
                        "unhold_terminal" => ""
                    );
                    $type = $params['type'];
                    $posQuoteTable = $this->resource->getTableName('ah_supermax_pos_quote');
                    $quoteData = $connection->query("SELECT * FROM $posQuoteTable WHERE quote_id='" . $quoteId . "'")->fetch();
                    if(empty($quoteData)) {
                        $addData[0] = $holdAdditionalData;
                        $jsonHoldAdditionaldata = json_encode($addData, true);
                        $connection->query("INSERT INTO $posQuoteTable SET quote_id = $quoteId, pos_outlet_id = $outletId, pos_user_id = $userId, type = '$type', comment = '$comment', status = $status, hold_time=0, additional_data= '$jsonHoldAdditionaldata', date_added = NOW(), date_modified = NOW()"); 
                    } else {
                        $addData = (array)json_decode($quoteData['additional_data'], true);
                        $addData[count($addData)] = $holdAdditionalData;
                        $jsonHoldAdditionaldata = json_encode($addData, true);
                        $connection->query("UPDATE $posQuoteTable SET pos_outlet_id = $outletId, pos_user_id = $userId, type = '$type', additional_data = '$jsonHoldAdditionaldata', comment = '$comment', status = $status, date_modified = NOW() WHERE quote_id = $quoteId");
                    }

                    $quote = $this->quoteFactory->create()->load($quoteId);
                    $quote->setProfessionalNumber("");
                    $quote->setIsProfessionalReferralApplied(false);
                    $quote->setCouponCode("")->collectTotals()->save(); 
                }
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function getHold() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $connection = $this->resource->getConnection();
                $userId = $this->supermaxSession->getPosUserId();
                $userData = $this->helper->joinUserOutletData($userId);
                $outletId = 0;
                if(!empty($userData)) {
                    $outletId = $userData['pos_outlet_id'];
                }
                $type = "hold_cart";
                $currentDate = date("Y-m-d H:i:s");
                $posQuoteTable = $this->resource->getTableName('ah_supermax_pos_quote');
                $posUserTable = $this->resource->getTableName('ah_supermax_pos_user');
                $quoteTable = $this->resource->getTableName('quote');
                $customerTable = $this->resource->getTableName('customer_entity');
                $orderTable = $this->resource->getTableName('sales_order');
                $resultData = $connection->query("SELECT pq.quote_id, DATE(pq.date_added) AS hold_date, q.reserved_order_id, q.customer_firstname, q.customer_lastname, q.additional_data, c.mobilenumber, pu.firstname, pu.lastname FROM $posQuoteTable AS pq LEFT JOIN $quoteTable as q ON(pq.quote_id = q.entity_id) LEFT JOIN $customerTable AS c ON(c.entity_id = q.customer_id) LEFT JOIN $posUserTable AS pu ON(pu.pos_user_id = pq.pos_user_id) WHERE pq.quote_id NOT IN (SELECT quote_id FROM $orderTable WHERE quote_id IS NOT NULL) AND pq.type ='$type' AND date(pq.date_added) = CURDATE() AND pq.pos_outlet_id = $outletId AND pq.status=1")->fetchAll();

                if(!empty($resultData)) {
                    foreach ($resultData as $data) {
                        $onInvoicePromotion = "no";
                        if(!empty($data['additional_data'])) {
                            $additionalData = (array)json_decode($data['additional_data']);
                            if(isset($additionalData['on_invoice_promotion']) && $additionalData['on_invoice_promotion']) {
                                $onInvoicePromotion = "yes";
                            }
                        }
                        $result[] = array(
                            "quote_id" => $data['quote_id'],
                            "hold_date" => $data['hold_date'],
                            "reserved_order_id" => $data['reserved_order_id'],
                            "customer_firstname" => $data['customer_firstname'],
                            "customer_lastname" => $data['customer_lastname'],
                            "mobilenumber" => $data['mobilenumber'],
                            "firstname" => $data['firstname'],
                            "lastname" => $data['lastname'],
                            "invoiceB2Ppromo" => $onInvoicePromotion
                        );
                    }
                }
                // pq.date_added >= curdate() - 1
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    public function resumeHold() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(isset($params['quote_id'])) {
                    $quoteId = $params['quote_id'];
                    $terminalId = $params['terminal_id'];
                    $userId = $this->supermaxSession->getPosUserId();
                    $userData = $this->getUserData($userId, $terminalId);
                    $connection = $this->resource->getConnection();
                    $posQuoteTable = $this->resource->getTableName('ah_supermax_pos_quote');
                    $quoteData = $connection->query("SELECT * FROM $posQuoteTable WHERE quote_id='" . $quoteId . "'")->fetch();
                    if(!empty($quoteData)) {
                        $addData = (array)json_decode($quoteData['additional_data'], true);
                        $arrayLength = (int)count($addData)-1;
                        $addData[$arrayLength]["unhold_time"] = $this->timezone->date(new \DateTime((strtotime(time()))))->format('Y-m-d h:i:s A');
                        $addData[$arrayLength]["unhold_user_id"] = $userData['pos_user_id'];
                        $addData[$arrayLength]["unhold_store_id"] = $userData['store_id'];
                        $addData[$arrayLength]["unhold_terminal"] = $userData['pos_terminal_code'];
                        $jsonHoldAdditionaldata = json_encode($addData, true);
                        $holdTime = (int)((strtotime(date("Y-m-d H:i:s")) - strtotime($quoteData['date_modified'])) / 60);
                        $connection->query("UPDATE $posQuoteTable SET hold_time = hold_time + $holdTime, additional_data = '$jsonHoldAdditionaldata' WHERE quote_id=$quoteId");
                    }
                    $quote = $this->quoteFactory->create()->load($quoteId);
                    $quote->setProfessionalNumber("");
                    $quote->setIsProfessionalReferralApplied(false);
                    $quote->setCouponCode("")->collectTotals()->save(); 
                    $couponCode = $quote->getCouponCode();
                    $applicableCoupons = $this->fetchQuote->getCoupons($quote, $couponCode, false);
                    if(!empty($applicableCoupons)) {
                        foreach ($applicableCoupons as $key => $coupon) {
                            $applicableCoupons[$key]['applied'] = ($coupon['code'] == $quote->getCouponCode()) ? true : false;
                        }
                    }

                    $result = $this->getHoldQuoteData($quote);
                    $result['applicable_coupons'] = $applicableCoupons;
                    $result['coupons'] = $this->fetchQuote->getAllAppliedPromotions($quote);
                    $result['applied_coupon'] = $quote->getCouponCode();
                }
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    private function getHoldQuoteData($quote) {
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
            "customer_id" => $quote->getCustomerId(),
            "customer_phone" => $this->getCustomerPhone($quote->getCustomerId()),
            "order_postcode" => $quote->getPostalCode()
        );
        $data['invoiceB2Ppromo'] = 'no';
        $quoteAdditionalData = $quote->getAdditionalData();
        if(!empty($quoteAdditionalData)) {
            $quoteAddData = (array)json_decode($quoteAdditionalData);
            if(($quoteAddData['on_invoice_promotion']) && $quoteAddData['on_invoice_promotion']) {
                $data['invoiceB2Ppromo'] = 'yes';
            }
        }
    
        $items = $quote->getAllItems();
        if(!empty($items)) {
            foreach ($items as $key => $item) {
                $product = $this->productData->load($item->getProductId());
                $origionalPrice = (array)json_decode($item->getAdditionalData());
                $data['items'][$key] = array(
                    "item_id" => (int)$item->getItemId(),
                    "product_id" => (int)$item->getProductId(),
                    "offer_id" => (int)$item->getSku(),
                    "applied_rule_ids" => $item->getAppliedRuleIds(),
                    "quantity" => (int)$item->getQty(),
                    "price" => (float)$item->getPrice(),
                    "base_price" => (float)$item->getBasePrice(),
                    "discount_percent" => (float)$item->getDiscountPercent(),
                    "discount_amount" => (float)$item->getDiscountAmount(),
                    "base_discount_amount" => (float)$item->getBaseDiscountAmount(),
                    "tax_percent" => (float)$item->getTaxPercent(),
                    "tax_amount" => (float)$item->getTaxAmount(),
                    "base_tax_amount" => (float)$item->getBaseTaxAmount(),
                    "row_total" => (float)$item->getRowTotal(),
                    "base_row_total" => (float)$item->getBaseRowTotal(),
                    "row_total_with_discount" => (float)$item->getRowTotalWithDiscount(),
                    "base_tax_before_discount" => (float)$item->getBaseTaxBeforeDiscount(),
                    "tax_before_discount" => (float)$item->getTaxBeforeDiscount(),
                    "base_cost" => (float)$item->getBaseCost(),
                    "price_incl_tax" => (float)$item->getPriceInclTax(),
                    "base_price_incl_tax" => (float)$item->getBasePriceInclTax(),
                    "row_total_incl_tax" => (float)$item->getRowTotalInclTax(),
                    "base_row_total_incl_tax" => (float)$item->getBaseRowTotalInclTax(),
                    "category_id" => $this->allProducts->getCategoryIds($product),
                    'status' => (bool)$product->getStatus(),
                    'short_name' => html_entity_decode($item->getName()), 
                    'name' => html_entity_decode($item->getName()),
                    'sku' => html_entity_decode($item->getSku()),
                    'type' => html_entity_decode($item->getProductType()),
                    'media' => array("0" => array("position" => 0, "url" => "")),
                    'discounts' => $this->allProducts->getTierPriceTable($product->getId()),
                    'is_bom' => ($product->getIsBom()) ? true : false,
                    'brand_id' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                    'brand_name' => !empty($product->getAttributeText('brand_Id')) ? $this->allProducts->productFieldProcessor->getBrandNameById(strtolower($product->getAttributeText('brand_Id'))) : '',
                    'seller_id' =>!empty($product->getSellerId()) ?  $product->getSellerId() : '',
                    'is_lot_controlled' => ($product->getIsLotControlled()) ? true : false,
                    'quantity_uom' => $product->getAttributeText('sale_uom'),
                    'service_category' => $product->getServiceCategory(),
                    'store_fulfilment_mode' => isset($origionalPrice['store_fulfilment_mode']) ? $origionalPrice['store_fulfilment_mode'] : $this->allProducts->getFulfilmentOption($product),
                    'courier_type' => ($product->getCourierType()) ? $product->getCourierType() : 'F',
                    'ean' => $product->getEan(),
                    "package_dimensions" => array(
                        "height_in_cm"  => $product->getPackageHeightInCm(),
                        "length_in_cm"  => $product->getPackageLengthInCm(),
                        "width_in_cm"   => $product->getPackageWidthInCm(),
                        "weight_in_kg"  => $product->getPackageWeightInKg()
                    ),
                    "baseCost" => isset($origionalPrice['baseCost']) ? $origionalPrice['baseCost'] :  0,
                    "cartKey" => isset($origionalPrice['cartKey']) ? $origionalPrice['cartKey'] :  0,
                    "cost" => isset($origionalPrice['cost']) ? $origionalPrice['cost'] :  0,
                    "mrp" => isset($origionalPrice['mrp']) ? $origionalPrice['mrp'] :  0,
                    "mrp_overridded_price" => isset($origionalPrice['mrp_overridded_price']) ? $origionalPrice['mrp_overridded_price'] :  0,
                    "overridedPrice" => isset($origionalPrice['overridedPrice']) ? $origionalPrice['overridedPrice'] :  0,
                    "overrideprice" => isset($origionalPrice['overrideprice']) ? $origionalPrice['overrideprice'] :  0,
                    "override_data" => isset($origionalPrice['override_data']) ? $origionalPrice['override_data'] :  array(),
                    "lot_data" => $item->getLotInfo() ? (array)json_decode($item->getLotInfo()) : array()
                );

                if(isset($origionalPrice['overridded_price'])) {
                    $data['items'][$key]["overridded_price"] = $origionalPrice['overridded_price'];
                }
                if(isset($origionalPrice['added_discount'])) {
                    $data['items'][$key]["added_discount"] = $origionalPrice['added_discount'];
                }
                if(isset($origionalPrice['price'])) {
                    $data['items'][$key]['price'] = $origionalPrice['price'];
                }
                if(isset($origionalPrice['total_discount_amount'])) {
                    $data['items'][$key]['total_discount_amount'] = $origionalPrice['total_discount_amount'];
                }
                if(isset($origionalPrice['total_discount_percent'])) {
                    $data['items'][$key]['total_discount_percent'] = $origionalPrice['total_discount_percent'];
                }
                if(isset($origionalPrice['base_cost_formatted'])) {
                    $data['items'][$key]['base_cost_formatted'] = $origionalPrice['base_cost_formatted'];
                }
                if(isset($origionalPrice['updateQuantity'])) {
                    $data['items'][$key]['updateQuantity'] = $origionalPrice['updateQuantity'];
                }
                if(isset($origionalPrice['mrpOverriddedPrice'])) {
                    $data['items'][$key]['mrpOverriddedPrice'] = $origionalPrice['mrpOverriddedPrice'];
                }
            }
        } else {
            $data['items'] = array();
        }

        return $data;
    }

    private function getCustomerPhone($customerId) {
        $phone = "";
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName('customer_entity');
        $data = $connection->query("SELECT mobilenumber FROM $customerTable  WHERE entity_id =$customerId")->fetch();
        if(!empty($data)) {
            $phone = $data['mobilenumber'];
        }

        return $phone;
    }

    private function getUserData($userId, $terminalId) {
        $connection = $this->resource->getConnection();
        $posOutletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $posUserTable = $this->resource->getTableName('ah_supermax_pos_user');
        $posTerminalTable = $this->resource->getTableName('ah_supermax_pos_terminals');
        $query = "SELECT pu.username as pos_user_id, ot.store_id, ot.pos_outlet_id, (SELECT code FROM $posTerminalTable WHERE pos_terminal_id = $terminalId) as pos_terminal_code FROM $posUserTable as pu LEFT JOIN $posOutletTable as ot ON(pu.pos_outlet_id = ot.pos_outlet_id) WHERE pu.pos_user_id= $userId";
        return $connection->query($query)->fetch();
    }
}



