<?php

namespace Anyhow\SupermaxPos\Block\Adminhtml;

/**
 * @api
 * @since 100.0.2
 */
class Form extends \Magento\Backend\Block\Template
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
        return $this->getUrl('supermax/report/save');
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

    public function getAllPaymentMethods(){
        $payment_methods = array(
			'CASH' => 'Cash Payment',
			'CREDIT-CARD' => 'Credit Card Payment',
            'DEBIT-CARD' => 'Debit Card Payment',
			// 'PAY-ON-DELIVERY' => 'POD Payment',
			'UPI' => 'Ezetap UPI/QR Payment',
            'EZETAP-EMI' => 'Ezetap EMI Payment',
            // 'NET-BANKING' => 'Internet Banking',
            'OFFLINE' => 'Offline Payment',
            'WALLET' => 'Wallet Payment',
            'CARD' => 'Pinelab Card (CC+DC) Payment',
            'PINELABS-UPI' => 'Pinelabs UPI/QR Payment',
            'EMI' => 'Pinelabs EMI Payment',
            'BANK-DEPOSIT' => 'Bank-Deposit Payment',
            'BHARATPE' => 'BharatPe Payment',
            'SARALOAN' => 'Saraloan Payment',
		);

		// if($this->scopeConfig->getValue("ah_supermax_scan_configuration/ah_supermax_scan_basic_configutaion/ah_supermax_scan_status", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 0)){
		// 	$payment_methods['braintree'] = 'Braintree Payment';
		// 	$payment_methods['pay_on_exit'] = 'Pay On Exit Payment';
		// }
        return $payment_methods;
    }   
}