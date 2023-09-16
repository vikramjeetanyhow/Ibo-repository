<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * @inheritdoc
 */
class TotalDiscounts implements ResolverInterface
{

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {  
        $discountTotal = [];
        $addDiscount = [];
        $discountAmount = [];

        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        $quote = $value['model'];

        $additionalDiscount = $this->getDiscountValues($quote);
        $mrpDiscount = $this->getMRPDiscountValues($quote);

        $addDiscount['value'] = $additionalDiscount + $mrpDiscount;
        $discountAmount['amount'] = $addDiscount;
        $discountTotal[] = $discountAmount;
        return $discountTotal;       

    }

    /**
     * Get Discount Values
     *
     * @param Quote $quote
     * @return array
     */
    private function getMRPDiscountValues(Quote $quote)
    {
       $mrpDiscountValue = 0;
        if(!empty($this->getProductData($quote))) {

            $mrpDiscountValue = $this->getProductData($quote);
        }
        if($mrpDiscountValue < 0)
        {
            $mrpDiscountValue = 0;
        }
        return $mrpDiscountValue;
    }

    private function getProductData($quote)
    {
        $itemPriceTotal = 0;
        $allItems = $quote->getAllVisibleItems();
        foreach ($allItems as $item) {
            $productId = $item->getProductId();
            $product = $this->productRepository->getById($productId);

            $mrpPrice = $product->getMrp();
            $sellingPrice = $item->getBasePriceInclTax();

            if($sellingPrice > $mrpPrice){
                $itemPriceTotal += 0;
            }else{
                $itemMRPTotal = $mrpPrice * $item->getQty();
                $itemPrice = $sellingPrice * $item->getQty();
                $itemPriceTotal += $itemMRPTotal - $itemPrice;
            }

       }
       return $itemPriceTotal;
    }

     /**
     * Get Discount Values
     *
     * @param Quote $quote
     * @return array
     */
    private function getDiscountValues(Quote $quote)
    {
        $amountValue = 0;
        $address = $quote->getShippingAddress();
        $totalDiscounts = $address->getExtensionAttributes()->getDiscounts();
        if ($totalDiscounts && is_array($totalDiscounts)) {
            foreach ($totalDiscounts as $value) {
                $discount = [];
                $amount = [];
                $discountData = $value->getDiscountData();
                $amountValue += $discountData->getAmount();
              //  $amount['currency'] = $quote->getQuoteCurrencyCode();
            }
        }
        return $amountValue;
    }
}
