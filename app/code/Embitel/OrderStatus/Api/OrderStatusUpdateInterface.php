<?php
namespace Embitel\OrderStatus\Api;

interface OrderStatusUpdateInterface
{
    /**
     * GET for Post api
     * @param string $orderId
     * @param string $status
     * @return array
     */
    public function statusUpdate($orderId,$status);
}