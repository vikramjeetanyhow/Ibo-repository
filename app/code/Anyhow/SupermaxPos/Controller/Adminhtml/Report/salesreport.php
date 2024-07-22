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
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxSalesOrders\CollectionFactory $CollectionFactory
	) {
		parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
		$this->resource = $resourceConnection;
		$this->request = $request;
		$this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;
        $this->salesOrder = $CollectionFactory;
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
        if(!empty($reportData)){
            foreach($reportData as $report){
              //  echo "<pre>"; print_r($report);
                $to = $report['to'];
                $from = $report['from'];
                $period = $report['period'];
                $posUserId = $report['pos_user_id'];
                $posOutletId = json_decode($report['pos_outlet_id']);
                $posOrderStatus = $report['status'];
                $posPaymentMethod = $report['payment_method'];
            }
            $salesItemtableName = $resource->getTableName('sales_order_item'); 
            $salesorder = $this->salesOrder->create();
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
                ['outlet_name']
            )
            ->joinLeft(
                ['quote_add' => $resource->getTableName('quote_address')],
                'main_table.quote_id = quote_add.quote_id',
                ['city','region','postcode','country_id']
            )
            ->joinLeft(
                ['appd' => $resource->getTableName('ah_supermax_pos_payment_detail')],
                'aspo.pos_order_id = appd.pos_order_id',
                ['payment_code']
            )
            ->columns('SUM(main_table.tax_amount) as tax')
            // ->columns('SUM(main_table.grand_total) as total')
            ->columns('SUM(appd.amount) as total')
            ->columns(['total_profit_loss' => new \Zend_Db_Expr(
                "SUM((SELECT (SUM($salesItemtableName.price - $salesItemtableName.cost)*$salesItemtableName.qty_ordered) FROM $salesItemtableName WHERE $salesItemtableName.order_id = main_table.entity_id GROUP BY $salesItemtableName.order_id))"
            )])
            ->columns('MIN(main_table.created_at) as date_start')
            ->columns('MAX(main_table.created_at) as date_end')
            ->columns('COUNT(DISTINCT main_table.entity_id) as orders')
            // ->where("spo.pos_user_id = $posUserId")
            // ->where("spo.pos_outlet_id = $posOutletId")
            // ->where("main_table.status = '$posOrderStatus'")
            ->where('main_table.entity_id = aspo.order_id')
            ->where("DATE(main_table.created_at) >= '$from' And DATE(main_table.created_at) <= '$to' ");
    
            if(isset($posUserId) && $posUserId !=0){
                $salesorder->getSelect()->where("aspo.pos_user_id = $posUserId");
            }
            // if(isset($posOutletId) && $posOutletId !=0){
            //     $this->getSelect()->where("spo.pos_outlet_id = $posOutletId");
            // }
            if(isset($posOrderStatus) && $posOrderStatus !="0"){
                $salesorder->getSelect()->where("main_table.status = '$posOrderStatus'");
            }
            
                if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
                    $salesorder->getSelect()->where("appd.payment_code = '$posPaymentMethod'");
                    // $this->getSelect()->where("JSON_UNQUOTE(JSON_EXTRACT(spo.payment_data, '$[0].payment_code')) = '$posPaymentMethod'");
                }
    
            if($period == 'day'){
                $salesorder->getSelect()->group('Date(main_table.created_at)');
            } elseif($period == 'week'){
                $salesorder->getSelect()->group('Week(main_table.created_at)');
            } elseif($period == 'month'){
                $salesorder->getSelect()->group('Month(main_table.created_at)');
            } elseif($period == 'year'){
                $salesorder->getSelect()->group('Year(main_table.created_at)');
            }
            
            if($assignedOutletIds){
                $salesorder->getSelect()->where("aspo.pos_outlet_id IN (?)", $assignedOutletIds);
             } else if(isset($posOutletId) && $posOutletId !=''){
                $salesorder->getSelect()->where("aspo.pos_outlet_id IN (?)", $posOutletId);
            }

            //Start export functionality
            $name = date('m-d-Y-H-i-s');
            $filepath = 'export/sales-report-export-' .$name. '.csv';
            $this->directory->create('export');
    
            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();
    
            $columns = ['Store Id', 'Store Name', 'Location', 'Date Start', 'Date End', 'No. Orders', 'MOPS Cash/Card'];
    
            foreach ($columns as $column) 
            {
                $header[] = $column;
            }
    
            $stream->writeCsv($header);
            // echo $this->getSelect()->__toString();
           //echo $salesorder->getSelect();
           foreach($salesorder as $salesData){
               // print_r($salesData->getData());
                $itemData = [];
                $itemData[] = $salesData['order_channel_info'];
                $itemData[] = $salesData['outlet_name'];
                $itemData[] = $salesData['city']. ' ' .$salesData['region']. ' ' .$salesData['postcode']. ' ' .$salesData['country_id'];
                $itemData[] = $salesData['date_start'];
                $itemData[] = $salesData['date_end'];
                $itemData[] = $salesData['orders'];
                $itemData[] = $salesData['payment_code'];
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