<?php

namespace Anyhow\SupermaxPos\Block\Adminhtml;

class TaxForm extends \Magento\Backend\Block\Template
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
        return $this->getUrl('supermax/report/taxsave');
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

}

