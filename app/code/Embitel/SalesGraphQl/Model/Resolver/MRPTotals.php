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
class MRPTotals implements ResolverInterface
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
        $mrp = [];
        $mrpAmount = [];
        $itemMRPTotal = 0;
        $order = $this->orderRepository->get($orderId);
        foreach ($order->getAllVisibleItems() as $_item) {
            $productId = $_item->getProductId();
            $product = $this->productRepository->getById($productId);
            $itemQty = $_item->getQtyOrdered();

            $itemPrice = $_item->getBasePriceInclTax();
            $itemMrp = $product->getMrp();
            if($itemMrp <= $itemPrice) {
                $itemMrp = $itemPrice;
            }


            $itemMRPTotal += $itemMrp * $itemQty;
        }
        if($itemMRPTotal < 0) {
            $itemMRPTotal = 0;
        }
        $mrp['value'] = $itemMRPTotal;
        $mrpAmount['amount'] = $mrp;
        $mrpTotal[] = $mrpAmount;
       
        return $mrpTotal;
    }
}