<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\SalesGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

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
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository
    ) { 
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    { 
        if (!isset($value['order_id'])) {
            throw new LocalizedException(__('"order id" value should be specified'));
        }
        $orderId = $value['order_id'];

        return $this->getOrderDataById($orderId);
    }

     /**
     * Get Item's MRP
     *
     * @param Sales Order $orderId
     * @return array
     */
    private function getOrderDataById($orderId)
    {
        $mrpTotal = [];
        $mrpDiscount = [];
        $mrpDiscountAmount = [];
        $itemMRPTotal = 0;
        $mrpItemDiscount = 0;
        $order = $this->orderRepository->get($orderId);
        foreach ($order->getAllVisibleItems() as $_item) {
            $productId = $_item->getProductId();
            $itemBasePriceSingleQty = $_item->getPriceInclTax();
            $itemQty = $_item->getQtyOrdered();
            $sellingPrice = $itemBasePriceSingleQty * $itemQty;
            $product = $this->productRepository->getById($productId);
            $itemMRPTotal = $product->getMrp() * $itemQty;

            if($itemMRPTotal < $sellingPrice){
                $mrpItemDiscount += 0;
            }
            else{
                $mrpItemDiscount += $itemMRPTotal - $sellingPrice;
            }
        }
        $mrpDiscount['value'] = $mrpItemDiscount;
        $mrpDiscountAmount['amount'] = $mrpDiscount;
        $mrpTotal[] = $mrpDiscountAmount;
       
        return $mrpTotal;
    }
}
