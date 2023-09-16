<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ibo\Hdfc\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class updateOrderStatus implements ResolverInterface
{

    protected $resource;
    
    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Sales\Model\Order $order,
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig, 
        \Embitel\Quote\Helper\Data $helper,     
        BuilderInterface $builderInterface
    ) {
        $this->_resource = $resource;
        $this->order = $order;
        $this->orderRepository = $orderRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->builder = $builderInterface;
        $this->helper = $helper;

    }
    
    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
            $args = $args['input'];
            if((!isset($args['order_id'])) || (!isset($args['order_status'])) || (!isset($args['payment_method'])) || (!isset($args['payment_type']))){
                 throw new GraphQlInputException(__('order_id, order_status, payment_method, payment_type should be specified'));
             }
             $this->addLog(json_encode($args)); 

             $orderData = $this->order->loadByIncrementId($args['order_id']);
             $returnData = [];
             if($orderData->getId()) {
                $failedStatus = $this->getFailedStatus();
                $status = $args['order_status'];
                $statusHistory = $args['order_status_history'];
                $payment_type = $args['payment_type'];
                $additionalInfo = $args['additional_information']; 

                $order = $this->orderRepository->get($orderData->getId());
                $order->setState($status);
                $order->setStatus($status);
                if($statusHistory != '') {
                    $order->addStatusToHistory($order->getStatus(), $statusHistory);
                }
                $order->getPayment()->setHdfcPaymentType($payment_type);
                if($additionalInfo != '') {
                    $order->getPayment()->setAdditionalInformation( (array) $additionalInfo);
                }
                // $payment = $order->getPayment();
                // $transaction_id = '1234556';
                // $data = 'data';
                // $transaction = $this->builder->setPayment($payment)->setOrder($order)->setTransactionId($transaction_id)->setAdditionalInformation(
                //     [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $data])->setFailSafe(true)->build('authorization');
                // $payment->save();
                $order->save(); 
                
                if($status == 'processing') { 
                    $orderID = $order->getId();
                    $this->helper->SuccessOrderExecute($orderID);        
                }

                $returnData['order_id'] = $args['order_id'];
                $returnData['order_status'] = $order->getStatus();

             } else {
                $this->addLog('order_id not found');
                throw new GraphQlInputException(__('order_id not found'));
             }
            return $returnData;
    }

    public function getFailedStatus() {
        $status = $this->_scopeConfig->getValue("payment/prepaid/order_status_failed");
        return $status;
    }

    public function addLog($logData, $filename = "order_status_update.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    }
}
