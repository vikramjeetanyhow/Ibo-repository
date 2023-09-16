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
class MRPTotals implements ResolverInterface
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

        return $this->getMRPTotalValues($quote);
    }

    /**
     * Get Discount Values
     *
     * @param Quote $quote
     * @return array
     */
    private function getMRPTotalValues(Quote $quote)
    {
        $mrpTotal = [];
        $mrp = [];
        $mrpAmount = [];
        if(!empty($this->getProductData($quote))) {

            $mrp['value'] = $this->getProductData($quote);
            $mrpAmount['amount'] = $mrp;
            $mrpTotal[] = $mrpAmount;
            return $mrpTotal;       
        }
        return null;
    }

    private function getProductData($quote)
    {
        $itemMRPTotal = 0;
        $allItems = $quote->getAllVisibleItems();
        foreach ($allItems as $item) {
            $productId = $item->getProductId();
            $product = $this->productRepository->getById($productId);

            $itemPrice = $item->getBasePriceInclTax();
            $itemMrp = $product->getMrp();
            if($itemMrp <= $itemPrice) {
                $itemMrp = $itemPrice;
            }

            $itemMRPTotal += $itemMrp * $item->getQty();
        }
        if($itemMRPTotal < 0) {
            $itemMRPTotal = 0;
        }
        return $itemMRPTotal;
    }
}
