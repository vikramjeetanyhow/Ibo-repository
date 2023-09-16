<?php

namespace Anyhow\SupermaxPos\Block\Adminhtml;

/**
 * @api
 * @since 100.0.2
 */
class CustomerForm extends \Magento\Backend\Block\Template
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        array $data = []
	) {
        parent::__construct($context, $data);
        $this->resource = $resourceConnection;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->helper = $helper;
	}
    public function getAdminUrl()
    {
        return $this->getUrl('supermax/report/customersave');
    }

    public function getAdminUrl2()
    {
        return $this->getUrl('supermax/report/customerreferralsave');
    }

    public function getCashierData()
    {
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

        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
        // $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        // $connection = $resource->getConnection();
        // $getStore = $resource->getTableName('ah_supermax_pos_report');
        // $sql = "SELECT pos_outlet_id FROM $getStore Where type ='reconcile' ";
        // $StoreData = $connection->query($sql);
        // if (!empty($StoreData)) {
        //     foreach ($StoreData as $Store) {  
        //         $posOutletId = $Store['pos_outlet_id'];
        //     }
        // }        
        // $connection = $this->resource->getConnection();
        // $cashierTable = $this->resource->getTableName('ah_supermax_pos_user');
        // $sql = "SELECT * FROM $cashierTable ";
        // if(isset($posOutletId) && $posOutletId !=0){
        //     $sql .= "Where pos_outlet_id IN ($posOutletId)";
        //     $cashierData = $connection->query($sql)->fetchAll();
        // }
    }

    public function getOutletData()
    {
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

    public function getStatusData()
    {
        $orderStatusCollection = $this->statusCollectionFactory->create();
        $orderStatus = $orderStatusCollection->getData();
        return $orderStatus;
    }
    
}