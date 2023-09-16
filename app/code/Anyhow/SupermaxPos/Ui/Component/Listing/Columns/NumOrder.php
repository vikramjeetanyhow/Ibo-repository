<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class NumOrder extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $components = [],
        array $data = [],
        \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
        $this->helper = $helper;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {      
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('ah_supermax_pos_report'); 
        $orderTable = $this->resource->getTableName('sales_order');

        $sqlSales = "SELECT * FROM $tableName Where type ='sales' ";
        $salesReportData = $connection->query($sqlSales);
        $assignedOutletId = $this->helper->assignedOutletIds();
        $assignedOutletIds = is_array($assignedOutletId) ? implode(",",$assignedOutletId) : 0;
        foreach($salesReportData as $salesReport){
            $posUserId = $salesReport['pos_user_id'];
            $posOutlet = json_decode($salesReport['pos_outlet_id']);
            $posOrderStatus = $salesReport['status'];
            $posPaymentMethod = $salesReport['payment_method'];
            
            //     $period = $salesReport['period'];
                // $posUserId = $salesReport['pos_user_id'];
                // $posOutletId = json_decode($salesReport['pos_outlet_id']);
                // $posOrderStatus = $salesReport['status'];
                // $posPaymentMethod = $salesReport['payment_method'];
        }
        $posOutletId = is_array($posOutlet) ? implode(",",$posOutlet) : '';
        if(isset($dataSource['data']['items'])) {           
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {

                $from = date("Y-m-d", strtotime($item['date_start']));
                $to = date("Y-m-d", strtotime($item['date_end']));
                
                $sql = "SELECT COUNT(main_table.entity_id) AS orders FROM sales_order AS main_table LEFT JOIN ah_supermax_pos_orders AS spo ON main_table.entity_id = spo.order_id LEFT JOIN ah_supermax_pos_payment_detail AS ppd ON spo.pos_order_id = ppd.pos_order_id WHERE main_table.entity_id = spo.order_id AND DATE(main_table.created_at) >= '$from' And DATE (main_table.created_at)<= '$to'";
                if(isset($posUserId) && $posUserId !=0){
                    $sql .= " AND spo.pos_user_id = $posUserId";                 }        
                if(isset($posOrderStatus) && $posOrderStatus !="0"){
                    $sql .= " AND main_table.status = $posOrderStatus";                    
                }
                if(isset($posPaymentMethod) && $posPaymentMethod !="0"){
                    $sql .= " AND ppd.payment_code = '$posPaymentMethod'";                    
                }            
                if($assignedOutletIds) {
                    $sql .= " AND spo.pos_outlet_id IN ($assignedOutletIds)";        
                } else if(isset($posOutletId) && $posOutletId != ''){
                    $sql .= " AND spo.pos_outlet_id IN ($posOutletId) ";
                }
                $orderData = $connection->query($sql)->fetchAll();
                foreach ($orderData as $order) {                 
                    $item[$fieldName] = (int)$order['orders'];
                }
            }

        }
        return $dataSource;
    }
}