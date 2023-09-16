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
class MRPDiscounts implements ResolverInterface
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        $quote = $value['model'];

        return $this->getMRPDiscountValues($quote);
    }

    /**
     * Get Discount Values
     *
     * @param Quote $quote
     * @return array
     */
    private function getMRPDiscountValues(Quote $quote)
    {
       $mrpTotalDiscount = [];
        $mrpDiscount = [];
        $mrpDiscountAmount = [];
        if(!empty($this->getProductData($quote))) {

            $mrpDiscount['value'] = $this->getProductData($quote);
            $mrpDiscountAmount['amount'] = $mrpDiscount;
            $mrpTotalDiscount[] = $mrpDiscountAmount;
            return $mrpTotalDiscount;       
        }
        return null;
    }

    private function getProductData($quote)
    {
        $itemPriceTotal = 0;
        $itemTotalDiscount = 0;
        $allItems = $quote->getAllVisibleItems();
        foreach ($allItems as $item) {
            $productId = $item->getProductId();
            $product = $this->productRepository->getById($productId);

            $itemMRPTotal = $product->getMrp() * $item->getQty();
            $itemPrice = $item->getBasePriceInclTax() * $item->getQty();
            if($itemMRPTotal < $itemPrice){
                $itemPriceTotal += 0;
            }
            else{
                $itemPriceTotal += $itemMRPTotal - $itemPrice;
            }
        }
       return $itemPriceTotal;
    }
}
