<?php
namespace Embitel\OrderStatus\Cron;
use Embitel\Quote\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;

class UpdateOrderStatus
{
    /**
     * @var Data
     */

    private OrderManagementInterface $orderManagement;
    private ScopeConfigInterface $_scopeConfig;
    private ResourceConnection $_resource;
    private Data $helper;
    private ManagerInterface $eventManager;

    /**
     * @param Data $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resource
     * @param OrderManagementInterface $orderManagement
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource,
        OrderManagementInterface $orderManagement,
        ManagerInterface $eventManager
    ) {
        $this->helper = $helper;
        $this->_scopeConfig = $scopeConfig;
        $this->_resource = $resource;
        $this->orderManagement = $orderManagement;
        $this->eventManager = $eventManager;
    }

    public function execute()
    {
        if ($this->getCronStatus()) {
            $this->helper->addLog("---------------------------------------------", "failed-orders-cancel.log");
            $this->helper->addLog("Cancle Order Cron Started", "failed-orders-cancel.log");

            $connection = $this->_resource->getConnection();
            $tableName = $this->_resource->getTableName('sales_order');
            $orderMin = ($this->getOrderPickMin()) ? $this->getOrderPickMin() : 60;
            $olimit = ($this->getOrderLimit()) ? $this->getOrderLimit() : 50;
            $sql = "select entity_id from " . $tableName . " where status ='" . $this->getOrderStatusFrom() . "' and date(created_at) <= (NOW() - INTERVAL " . $orderMin . " MINUTE) limit " . $olimit;
            $this->helper->addLog($sql, "failed-orders-cancel.log");
            $orders = $connection->fetchAll($sql);
            foreach ($orders as $order) {
                $this->orderManagement->cancel($order['entity_id']);
                $this->helper->addLog($sql, "failed-orders-cancel.log");
                $this->helper->addLog("Updated order id " . $order['entity_id'] . " to cancel", "failed-orders-cancel.log");
            }
            $this->eventManager->dispatch('update_order_status', ['order' => ""]);

            $this->helper->addLog("Cancel Cron End", "failed-orders-cancel.log");
            $this->helper->addLog("---------------------------------------------", "failed-orders-cancel.log");
        }
    }

    public function getCronStatus()
    {
        return $this->_scopeConfig->getValue("Order_success_status/order_cancel_cron/cron_status");
    }

    public function getOrderPickMin()
    {
        $orderTime = $this->_scopeConfig->getValue("Order_success_status/order_cancel_cron/ordertime");
        return (int)$orderTime;
    }

    public function getOrderLimit()
    {
        return $this->_scopeConfig->getValue("Order_success_status/order_cancel_cron/olimit");
    }

    public function getOrderStatusFrom()
    {
        return $this->_scopeConfig->getValue("Order_success_status/order_cancel_cron/fromstatus");
    }

    public function getOrderStatusTo()
    {
        return $this->_scopeConfig->getValue("Order_success_status/order_cancel_cron/tostatus");
    }
}
