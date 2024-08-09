<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Report;

use Magento\Framework\App\Filesystem\DirectoryList;

class salesreport extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;
	
	public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Framework\App\Request\Http $request,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesOrders\CollectionFactory $CollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
	) {
		parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
		$this->resource = $resourceConnection;
		$this->request = $request;
		$this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;
        $this->salesOrder = $CollectionFactory;
        $this->_orderCollectionFactory = $orderCollectionFactory;
	}

	public function execute()
	{
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        $period = 'day';
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $user = $objectManager->get('Magento\Backend\Model\Auth\Session')->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report'); 
        $sql = "SELECT * FROM $tableName Where type ='sales' ";
        $reportData = $connection->query($sql)->fetchAll();
        $posOutletIdArray = [];
        if(!empty($reportData)){
            foreach($reportData as $report){
                $to = $report['to'];
                $from = $report['from'];
                $period = $report['period'];
                $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);
                $posOutletIdArray[] = json_decode($report['pos_outlet_id']);
                $posOrderStatus = $report['status'];
                $posPaymentMethod = $report['payment_method'];
            }
            $salesItemtableName = $resource->getTableName('sales_order_item'); 
            $salesorder = $this->_orderCollectionFactory->create();
            $salesorder->getSelect()
            ->joinLeft(
                ['aspo' => $resource->getTableName('ah_supermax_pos_orders')],
                'main_table.entity_id = aspo.order_id',
                ['name'=> 'pos_order_id', 'order_id', 'device_type']
            )
            ->columns(['products' => new \Zend_Db_Expr(
                "SUM((SELECT SUM($salesItemtableName.qty_ordered) FROM $salesItemtableName WHERE $salesItemtableName.order_id = main_table.entity_id GROUP BY $salesItemtableName.order_id))"
            )])
            ->joinLeft(
                ['sp_outlet' => $resource->getTableName('ah_supermax_pos_outlet')],
                'main_table.order_channel_info = sp_outlet.inventory_node',
                ['outlet_name','pos_outlet_id']
            )
            ->join(
                ['aspoa' => $resource->getTableName('ah_supermax_pos_outlet_address')],
                'sp_outlet.pos_outlet_id = aspoa.parent_outlet_id',
                ['city','region','postcode','country_id']
            )
            ->joinLeft(
                ['appd' => $resource->getTableName('ah_supermax_pos_payment_detail')],
                'aspo.pos_order_id = appd.pos_order_id',
                ['payment_code']
            )
            ->columns('SUM(appd.amount) as total')
            ->columns('MIN(main_table.created_at) as date_start')
            ->columns('MAX(main_table.created_at) as date_end')
            ->columns('COUNT(DISTINCT main_table.entity_id) as orders')
            ->where('main_table.entity_id = aspo.order_id')
            ->where("DATE(main_table.created_at) >= '$from' And DATE(main_table.created_at) <= '$to' ")
            ->group('outlet_name')
            ->group('payment_code');

            if($assignedOutletIds){
                $salesorder->getSelect()->where("aspo.pos_outlet_id IN (?)", $assignedOutletIds);
             } else if(isset($posOutletId) && $posOutletId !=''){
                $salesorder->getSelect()->where("aspo.pos_outlet_id IN (?)", $posOutletIdArray[0]);
            }
            
            //Start export functionality
            $name = date('m-d-Y-H-i-s');
            $filepath = 'export/sales-report-export-' .$name. '.csv';
            $this->directory->create('export');
    
            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();
    
            $columns = ['Store Id', 'Store Name', 'Location', 'Date Start', 'Date End', 'No. Orders', 'CASH', 'CREDIT-CARD', 'EZETAP-EMI', 'OFFLINE', 'Pay on Invoice', 'UPI', 'WALLET', 'DEBIT-CARD'];
    
            foreach ($columns as $column) 
            {
                $header[] = $column;
            }
    
            $stream->writeCsv($header);
          // echo $salesorder->getSelect(); die();
            $breakPointArray = [];
            foreach($salesorder->getData() as $k => $salesData){
                $breakPointArray[$salesData['order_channel_info']]['outlet_id'] = $salesData['order_channel_info'];
                $breakPointArray[$salesData['order_channel_info']]['outlet_name'] = $salesData['outlet_name'];
                $breakPointArray[$salesData['order_channel_info']]['address'] = $salesData['city']. ' ' .$salesData['region']. ' ' .$salesData['postcode']. ' ' .$salesData['country_id'];
                $breakPointArray[$salesData['order_channel_info']]['date_start'] = $salesData['date_start'];
                $breakPointArray[$salesData['order_channel_info']]['date_end'] = $salesData['date_end'];
                $breakPointArray[$salesData['order_channel_info']]['count_orders'][] = $salesData['orders'];
                $breakPointArray[$salesData['order_channel_info']]['total'][$k] = $salesData['total'];
                $breakPointArray[$salesData['order_channel_info']][$salesData['payment_code']] =  $salesData['total'];
                    
            }

            foreach($breakPointArray as $report){
                $itemData = [];
                $itemData[] = $report['outlet_id'];
                $itemData[] = $report['outlet_name'];
                $itemData[] = $report['address'];
                $itemData[] = $from;
                $itemData[] = $to;
                $itemData[] = array_sum($report['count_orders']);
                $itemData[] = $report['CASH'];
                $itemData[] = !empty($report['CREDIT-CARD']) ? $report['CREDIT-CARD']: '';
                $itemData[] = !empty($report['EZETAP-EMI']) ? $report['EZETAP-EMI'] : '';
                $itemData[] = !empty($report['OFFLINE']) ? $report['OFFLINE']: '';
                $itemData[] = !empty($report['PARTIAL-PAYMENT-BALANCE']) ? $report['PARTIAL-PAYMENT-BALANCE'] : '';
                $itemData[] = !empty($report['UPI']) ? $report['UPI'] : '';
                $itemData[] = !empty($report['WALLET']) ? $report['WALLET'] : '';
                $itemData[] = !empty($report['DEBIT-CARD']) ? $report['DEBIT-CARD'] : '';
                $stream->writeCsv($itemData);
            }
            $stream->unlock();
            $stream->close();
            $content = [];
            $content['type'] = 'filename';
            $content['value'] = $filepath;
            $content['rm'] = true;

            $csvfilename = 'sales-report-export-'.$name.'.csv';
            return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);

        }
	}
}