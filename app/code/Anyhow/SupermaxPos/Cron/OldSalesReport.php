<?php
Namespace Anyhow\SupermaxPos\Cron;
use Magento\Framework\App\Filesystem\DirectoryList;

class OldSalesReport {
    protected $_pageFactory;
    protected $logger;
    protected $helper;
	public function __construct(
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Framework\Filesystem $filesystem,
        \Embitel\Quote\Helper\Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\View\Result\PageFactory $pageFactory)
	{
		$this->_pageFactory = $pageFactory;
        $this->helper = $helper;
        $this->_fileFactory = $fileFactory;
        $this->logger = $logger;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->_storeManager = $storeManager;
        $this->resource = $resourceConnection;
        $this->timezone = $timezone;
    }

	public function execute()
	{
        $month = $this->timezone->date(new \DateTime())->format('Y-m');
        $lastMonth = date("Y-m",strtotime("-1 month"));
        $lastMonthName = date("F-Y",strtotime("-1 month"));
        $monthdate = $this->timezone->date(new \DateTime())->format('m');
        $year = $this->timezone->date(new \DateTime())->format('Y');
        $monthcurrent = $this->timezone->date(new \DateTime())->format('F');
        $monthcurrenTYear = $this->timezone->date(new \DateTime())->format('F-Y');
        $monthcurrenttime = $this->timezone->date(new \DateTime())->format('F-h:i:s');
        $filenaldate = cal_days_in_month(CAL_GREGORIAN,$monthdate,$year);
        $date_start = date("Y-m-d h:i:s",strtotime($lastMonth.'-1'));
        $date_end = date("Y-m-d h:i:s",strtotime($lastMonth.'-'.$filenaldate));
        $name = date('m-d-Y-H-i-s');
        $connection = $this->resource->getConnection();
        $saleasReportTable = $this->resource->getTableName('ah_supermax_pos_report');
        $salesReport = $connection->query("SELECT * FROM $saleasReportTable WHERE type ='sales' ")->fetch();
        $posUserId = 0;
        $posOutletId = $posOrderStatus = $posPaymentMethod = "";
        if(!empty($salesReport)){
            $posUserId = $salesReport['pos_user_id'];
            $assignedOutletId = (array)json_decode($salesReport['pos_outlet_id']);
            $posOutletId = !empty($assignedOutletId) ? implode("," , $assignedOutletId) : "";
            $posOrderStatus = $salesReport['status'];
            $posPaymentMethod = $salesReport['payment_method'];
        }
        $salesOrdersTable = $this->resource->getTableName('sales_order');
        $posOrdersTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $posOutletsTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $posUsersTable = $this->resource->getTableName('ah_supermax_pos_user');
        $ezetapPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_ezetap');
        $pinelabsPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_pinelabs');
        $orderStatusHistoryTable = $this->resource->getTableName('sales_order_status_history');
        $paymentDetail = $this->resource->getTableName('ah_supermax_pos_payment_detail');
        $salesOldOrdersTable = $this->resource->getTableName('ah_supermax_old_sales_order');
        $AlloutLet = "SELECT outlet_name,pos_outlet_id FROM $posOutletsTable";
        $OutletData = $connection->query($AlloutLet)->fetchAll();
        
        foreach($OutletData as $Outlet){
            $outeletid = $Outlet['pos_outlet_id'];
            $outeletName = $Outlet['outlet_name'];
            $sql = "SELECT so.`increment_id`, so.`created_at`, pou.`outlet_name`, CONCAT(COALESCE(pus.`firstname`,''), ' ', COALESCE(pus.`lastname`,''), '-', po.`pos_user_id`) AS `cashier`, (SELECT CONCAT(`firstname`, ' ', COALESCE(`lastname`,'')) FROM `customer_entity` WHERE `entity_id`= so.`customer_id`) AS `customer`, so.`grand_total`, (CASE WHEN (SELECT count(*) FROM $ezetapPaymentTable WHERE `order_id`= po.`order_id`) > 0 THEN 'EZETAP' WHEN (SELECT count(*) FROM $pinelabsPaymentTable WHERE `order_id`= po.`order_id`) > 0 THEN 'PINELABS' ELSE '-' END) AS `merchant`, (SELECT GROUP_CONCAT(`payment_method`, '(Amount: ',`amount`, ')') FROM $paymentDetail WHERE `pos_order_id`= po.`pos_order_id`) AS `payment_method`, (CASE WHEN JSON_VALUE(so.`additional_data`,'$.on_invoice_promotion') = 1 THEN 'True' ELSE 'False' END)  AS `on_invoice_promotion_status`, so.`status`, (SELECT GROUP_CONCAT(`comment`, ',') FROM $orderStatusHistoryTable WHERE `parent_id`= po.`order_id`) AS `order_comment` FROM $posOrdersTable AS po LEFT JOIN $salesOrdersTable AS so ON(so.`entity_id` = po.`order_id`) LEFT JOIN $posOutletsTable AS pou ON(pou.`pos_outlet_id` = po.`pos_outlet_id`) LEFT JOIN $posUsersTable AS pus ON(pus.`pos_user_id` = po.`pos_user_id`) WHERE (pou.outlet_name='$outeletName') AND DATE(so.`created_at`) >= DATE('$date_start') AND DATE(so.`created_at`) <= DATE('$date_end')";
            $salesReportData = $connection->query($sql)->fetchAll();
            if(!empty($salesReportData)) {
                $filepath = 'export/sales-detail-report-export-'.$outeletid.'-'.$name.'.csv';
                $this->directory->create('export');
                $stream = $this->directory->openFile($filepath, 'w+');
                $stream->lock();
                $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
                $columns = ['Order ID','Date', 'Store', 'Cashier', 'Customer', 'Total (' . $storeCurrencyCode . ')', 'Merchant', 'Payment Method (' . $storeCurrencyCode . ') ', 'On Invoice Promotion Discount', 'Order Status','Order Comment'];
                foreach ($columns as $column) {
                    $header[] = $column;
                }
                $stream->writeCsv($header);
                foreach($salesReportData as $item) {
                    $itemData = [];
                    $itemData[] = $item['increment_id'];
                    $itemData[] = $this->timezone->date(new \DateTime($item['created_at']))->format('Y-m-d h:i:s A');
                    $itemData[] = $item['outlet_name'];
                    $itemData[] = $item['cashier'];
                    $itemData[] = $item['customer'];
                    $itemData[] = $item['grand_total'];
                    $itemData[] = $item['merchant'];
                    $itemData[] = $item['payment_method'];
                    $itemData[] = $item['on_invoice_promotion_status'];
                    $itemData[] = $item['status'];
                    $itemData[] = $item['order_comment'];
                    $stream->writeCsv($itemData);
                }
                $this->helper->addLog("---------------------------------------------", "sales-order-monthly.log");
                $this->helper->addLog("Old sales Report Cron  Started", "sales-order-monthly.log");
                $connection = $this->resource->getConnection();
                $sql = "INSERT INTO ah_supermax_old_sales_order (show_by,complete_path,outlet_id) VALUES ('$lastMonthName','$filepath','$outeletid')";
                $connection->query($sql);  
                $this->helper->addLog('Month: '. $lastMonthName . ' store name '.$outeletName, "sales-order-monthly.log");
                $this->helper->addLog("Old sales Report Cron End", "sales-order-monthly.log");
                $this->helper->addLog("---------------------------------------------", "sales-order-monthly.log");
            }
            
        }
        
    }
    
}