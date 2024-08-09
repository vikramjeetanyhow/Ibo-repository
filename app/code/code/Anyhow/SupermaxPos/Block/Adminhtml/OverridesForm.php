<?php

namespace Anyhow\SupermaxPos\Block\Adminhtml;

/**
 * @api
 * @since 100.0.2
 */
class OverridesForm extends \Magento\Backend\Block\Template
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        array $data = []
	) {
        parent::__construct($context, $data);
        $this->resource = $resourceConnection;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
	}
    public function getAdminUrl(){
        return $this->getUrl('supermax/report/overridessave');
    }

    public function getCashierData(){
        $assignedOutletId = $this->helper->assignedOutletIds();
        $assignedOutletIds = is_array($assignedOutletId) ? implode(",",$assignedOutletId) : 0;
        $connection = $this->resource->getConnection();
        $cashierTable = $this->resource->getTableName('ah_supermax_pos_user');
        $sql = "SELECT * FROM $cashierTable ";
        if($assignedOutletIds) {
            $sql .= "Where pos_outlet_id IN ($assignedOutletIds)";
        }
        $cashierData = $connection->query($sql)->fetchAll();        
        return $cashierData;
    }

    public function getApproverData(){
        $assignedOutletId = $this->helper->assignedOutletIds();
        $assignedOutletIds = is_array($assignedOutletId) ? implode(",",$assignedOutletId) : 0;
        $connection = $this->resource->getConnection();
        $cashierTable = $this->resource->getTableName('ah_supermax_pos_user');
        $sql = "SELECT * FROM $cashierTable ";
        if($assignedOutletIds) {
            $sql .= "Where pos_outlet_id IN ($assignedOutletIds)";
        }
        $cashierData = $connection->query($sql)->fetchAll();        
        return $cashierData;
    }

    public function getPermissionData() {
        $permisions = array(
            'cart_product_price' => __('Cart Product Price'),
            'cart_product_discount' => __('Cart Product Discount'),
            // 'cart_product_quantity' => __('Cart Product Quantity'),
            // 'cart_customer' => __('Cart Customer'),
            'cart_discount' => __('Cart Discount'),
            // 'cart_coupon' => __('Cart Coupon'),
            // 'dashboard' => __('Dashboard'),
            // 'register_and_cash_mgmt' => __('Register & Cash Management')
            'mop_offline' => __('MOP Offline'),
            'delivery_charge' => __("Delivery Charge"),
            'on_invoice_promotion' => __("On Invoice Promotion"),
        ); 
        return $permisions;
    }

    public function getOutletData(){
        $assignedOutletId = $this->helper->assignedOutletIds();
        $assignedOutletIds = is_array($assignedOutletId) ? implode(",",$assignedOutletId) : 0;

        $connection = $this->resource->getConnection();
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $sql = "SELECT * FROM $outletTable ";
        if($assignedOutletIds) {
            $sql .= "Where pos_outlet_id IN ($assignedOutletIds)";
        }
        $outletData = $connection->query($sql)->fetchAll();
        return $outletData;
    }

    public function getStatusData(){
        $orderStatusCollection = $this->statusCollectionFactory->create();
        $orderStatus = $orderStatusCollection->getData();
        return $orderStatus;
    }
    
}