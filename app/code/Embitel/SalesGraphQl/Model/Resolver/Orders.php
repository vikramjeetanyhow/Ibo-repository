<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\SalesGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * Orders data reslover
 */
class Orders implements ResolverInterface
{
    /**
     * @var CollectionFactoryInterface
     */
    private $collectionFactory;

    /**
     * @param CollectionFactoryInterface $collectionFactory
     */
    public function __construct(
        CollectionFactoryInterface $collectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->statusCollection = $statusCollection;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }
        if(isset($args['input'])) {
            $input = $args['input'];
            $status = $input['status'];
            $statuses = explode(',',$status);

            if($status == '') {
                throw new GraphQlInputException(__('The status should be specified'));
            }

            $statusCollection = $this->statusCollection->toOptionArray();
            foreach($statusCollection as $stat) {
                $statuslist[$stat['value']] = $stat['label']; 
            } 
            foreach($statuses as $status) {
                if(!(array_key_exists($status,$statuslist))) {
                    throw new GraphQlInputException(__('The order status not available on magento.'));
                }
            }

            $items = [];
            $orders = $this->collectionFactory->create($context->getUserId());
            $orders->addAttributeToSelect('*')
            ->addFieldToFilter('status', ['in' => $statuses])
            ->addFieldToFilter('increment_id', ['nlike' => '0%']);
        } else {
            $items = [];
            $orders = $this->collectionFactory->create($context->getUserId());
        }

        /** @var Order $order */
        foreach ($orders as $order) {
                $items[] = [
                    'id' => $order->getId(),
                    'increment_id' => $order->getIncrementId(),
                    'order_number' => $order->getIncrementId(),
                    'created_at' => $order->getCreatedAt(),
                    'grand_total' => $order->getGrandTotal(),
                    'status' => $order->getStatus(),
                    'status_code' => $order->getStatus(),
                    'no_items' => $order->getTotalItemCount(),
                    'invoice_url' => "",
                    'order_channel' => $order->getOrderChannel(),
                    'promise_created_at' => (($order->getPromiseCreatedAt() != null) && ($order->getPromiseCreatedAt() != '')) ? $order->getPromiseCreatedAt() : '',
                    'promise_expires_at' => (($order->getPromiseExpiresAt() != null) && ($order->getPromiseExpiresAt() != '')) ? $order->getPromiseExpiresAt() : '',
                ];
        }
        return ['items' => $items];
    }
}
