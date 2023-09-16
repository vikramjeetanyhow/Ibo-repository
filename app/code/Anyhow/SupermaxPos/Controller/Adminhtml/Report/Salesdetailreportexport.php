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

class Salesdetailreportexport extends \Magento\Backend\App\Action {

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone

    ) {
        $this->_fileFactory = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->_storeManager = $storeManager;
        $this->resource = $resourceConnection;
        $this->timezone = $timezone;
        parent::__construct($context);
    }

    public function execute() {   
        $date_start = $this->getRequest()->getParam('date_start');
        $date_end = $this->getRequest()->getParam('date_end');
        $name = date('m-d-Y-H-i-s');
        $filepath = 'export/sales-detail-report-export-' . $name . '.csv';
        $this->directory->create('export');
        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();
        $storeCurrencyCode = $this->_storeManager->getStore()->getBaseCurrencyCode();
        $columns = ['Order ID','Date', 'Store', 'Cashier', 'Customer', 'Total (' . $storeCurrencyCode . ')', 'Merchant', 'Payment Method (' . $storeCurrencyCode . ') ', 'On Invoice Promotion Discount', 'Order Status','Order Comment'];
        foreach ($columns as $column) {
            $header[] = $column;
        }
        $stream->writeCsv($header);

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

        $sql = "SELECT so.`increment_id`, so.`created_at`, pou.`outlet_name`, CONCAT(COALESCE(pus.`firstname`,''), ' ', COALESCE(pus.`lastname`,''), '-', po.`pos_user_id`) AS `cashier`, (SELECT CONCAT(`firstname`, ' ', COALESCE(`lastname`,'')) FROM `customer_entity` WHERE `entity_id`= so.`customer_id`) AS `customer`, so.`grand_total`, (CASE WHEN (SELECT count(*) FROM $ezetapPaymentTable WHERE `order_id`= po.`order_id`) > 0 THEN 'EZETAP' WHEN (SELECT count(*) FROM $pinelabsPaymentTable WHERE `order_id`= po.`order_id`) > 0 THEN 'PINELABS' ELSE '-' END) AS `merchant`, (SELECT GROUP_CONCAT(`payment_method`, '(Amount: ',`amount`, ')') FROM $paymentDetail WHERE `pos_order_id`= po.`pos_order_id`) AS `payment_method`, (CASE WHEN JSON_VALUE(so.`additional_data`,'$.on_invoice_promotion') = 1 THEN 'True' ELSE 'False' END)  AS `on_invoice_promotion_status`, so.`status`, (SELECT GROUP_CONCAT(`comment`, ',') FROM $orderStatusHistoryTable WHERE `parent_id`= po.`order_id`) AS `order_comment` FROM $posOrdersTable AS po LEFT JOIN $salesOrdersTable AS so ON(so.`entity_id` = po.`order_id`) LEFT JOIN $posOutletsTable AS pou ON(pou.`pos_outlet_id` = po.`pos_outlet_id`) LEFT JOIN $posUsersTable AS pus ON(pus.`pos_user_id` = po.`pos_user_id`) WHERE DATE(so.`created_at`) >= DATE('$date_start') AND DATE(so.`created_at`) <= DATE('$date_end')";

        if(!empty($posUserId)) {
            $sql .= " AND po.`pos_user_id`=$posUserId";
        }
        if(!empty($posOutletId)) {
            $sql .= " AND po.`pos_outlet_id` IN ($posOutletId)";
        }
        if(!empty($posOrderStatus)) {
            $sql .= " AND so.`status`=$posOrderStatus";
        }
        if(!empty($posPaymentMethod)) {
            $sql .= " AND so.`payment_method`=$posPaymentMethod";
        }
       
        $salesReportData = $connection->query($sql)->fetchAll();
        if(!empty($salesReportData)) {
            foreach($salesReportData as $item) {
                $itemData = [];
                $itemData[] = $item['increment_id'];
                $itemData[] =  $this->timezone->date(new \DateTime($item['created_at']))->format('Y-m-d h:i:s A');
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
        }
        $stream->unlock();
        $stream->close();
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = true;
        $csvfilename = 'sales-detail-report-export-' . $name . '.csv';
        return $this->_fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }

    public function sendEmail($fileUrl){
        $this->inlineTranslation->suspend();
        $emailTemplateVariables = ['message' => "Please find latest order report in the attachment"];
        $senderEmail = $this->scopeConfig->getValue(self::SENDER_EMAIL, ScopeInterface::SCOPE_STORE);
        $senderName = $this->scopeConfig->getValue(self::SENDER_NAME, ScopeInterface::SCOPE_STORE);
        $senderInfo = ['name' => $senderName, 'email' => $senderEmail];
        $receiverEmails = explode(',',trim($this->scopeConfig->getValue(self::RECEIVER_EMAIL, ScopeInterface::SCOPE_STORE)));
        $fileName = basename($fileUrl);
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('order_report')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId()])
            ->setTemplateVars($emailTemplateVariables)
            ->setFrom($senderInfo)
            ->addTo($receiverEmails)
            ->addAttachment(file_get_contents($fileUrl), $fileName, 'application/csv')
            ->getTransport();
        try {
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $exception) {
            $this->helper->addLog($exception->getMessage(), "order-report.log");
        }
        $this->file->deleteFile($fileUrl);
    }
}