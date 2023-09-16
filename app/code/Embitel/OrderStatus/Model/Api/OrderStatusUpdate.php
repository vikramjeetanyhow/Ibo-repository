<?php

namespace Embitel\OrderStatus\Model\Api;

use Embitel\OrderStatus\Api\OrderStatusUpdateInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Embitel\Quote\Helper\Data;

class OrderStatusUpdate implements OrderStatusUpdateInterface
{
    /**
     * @var EventManager
     */
    private $eventManager;

    protected $logger;

    public function __construct(
        \Magento\Sales\Model\Order $order,
        OrderRepositoryInterface $orderRepository,
        EventManager $eventManager,
        Data $data
    )
    {
        $this->order = $order;
        $this->orderRepository = $orderRepository;
        $this->eventManager = $eventManager;
        $this->quoteHelper = $data;
    }
    /**
     * @inheritdoc
     */
    public function statusUpdate($orderId,$status)
    {
        $this->addLog("================Request Start=============");
        $this->addLog('Request->  Order Id :'.$orderId ." , Status :".$status);

        $data = [];
        $data['status'] = false;
        $data['message'] = '';

        if(($orderId == '') || ($status == '') || ($status != 'canceled')) {

            $data['status'] = false;
            $data['message'] = 'The order ID / status should not be empty';

        } else {
            try {

                $orderData = $this->order->loadByIncrementId($orderId);
                if($orderData->getId()) {

                    $order = $this->orderRepository->get($orderData->getId());
                    $order->setState($status);
                    $order->setStatus($status);
                    $order->addStatusToHistory($order->getStatus(), 'Order status updated by OMS');
                    $order->save();
                    $data['status'] = true;
                    $data['message'] = 'The order ID '.$orderId.' status is updated';
                    $this->eventManager->dispatch('update_order_status', ['order' => $this]);

                    //update customer attribute for first time promotion for cacelled order
                    $this->quoteHelper->addLog('==========================', "first_time_promo.log");
                    $this->quoteHelper->addLog('Order Cancelled: '. $orderId, "first_time_promo.log");
                    $this->quoteHelper->addLog('Customer Id: '. $order->getCustomerId(), "first_time_promo.log");
                    $this->quoteHelper->updateFirstTimePromoAttribute($order->getAppliedRuleIds(),
                        $order->getCustomerId(), false);
                    $this->quoteHelper->addLog('==========================', "first_time_promo.log");
                } else {
                    $data['status'] = false;
                    $data['message'] = 'The order ID is invalid / not found on Magento';
                }
            } catch(Exception $e) {
                $this->addLog($e->getMessage());
            }

        }

        $return['response'] = $data;

        $this->addLog('Response :'.json_encode($return));
        $this->addLog("================Response End=============");

        return $return;
   }

   public function addLog($logData, $filename = "order_status_update_oms.log")
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
