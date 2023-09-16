<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class GetReport implements \Anyhow\SupermaxPos\Api\Supermax\GetReportInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET for Post api
     * @api
     * @param string $startdate
     * @param string $enddate
     * @return string
     */
    public function getReport($startdate, $enddate)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $productsByQuantity = array();
                $productsByTotal = array();
                $ordersByDay = array();
                $ordersByWeek = array();
                $ordersByMonth = array();
                $ordersByYear = array();
                $customersByDay = array();
                $customersByWeek = array();
                $customersByMonth = array();
                $customersByYear = array();
                $lastOrders = array();
                $lastCustomers = array();

                $connection = $this->resource->getConnection();             
                $userId =$this->supermaxSession->getPosUserId();

                if(!empty($startdate)){
                    $filterStartDate = date('Y-m-d H:i:s', strtotime($startdate));
                } else {
                    $filterStartDate = date('Y-m-d H:i:s');
                }

                if(!empty($enddate)){
                    $filterEndDate = date('Y-m-d H:i:s', strtotime($enddate));
                    // . ' +1 day'));
                } else {
                    $filterEndDate = date('Y-m-d H:i:s');
                }

                $filter_data = array(
                    'filter_date_start'	     => $filterStartDate,
                    'filter_date_end'	     => $filterEndDate
                );

                // products by qantity
                $filter_data['sort'] = "quantity";
                $filter_data['order'] = "DESC";
                $productsByQuantityDatas = $this->getPurchased($filter_data);
                if(!empty($productsByQuantityDatas)){
                    foreach($productsByQuantityDatas as $productsByQuantityData){
                        $productsByQuantity[] = array(
                            'name' => html_entity_decode($productsByQuantityData['name']),
                            'model' => html_entity_decode($productsByQuantityData['sku']),
                            'quantity' => (int)$productsByQuantityData['quantity'],
                            'total' => (float)$productsByQuantityData['total']
                        ); 
                    }
                }

                // products by total
                $filter_data['sort'] = "total";
                $filter_data['order'] = "DESC";
                $productsByTotalDatas = $this->getPurchased($filter_data);
                if(!empty($productsByTotalDatas)){
                    foreach($productsByTotalDatas as $productsByTotalData){
                        $productsByTotal[] = array(
                            'name' => html_entity_decode($productsByTotalData['name']),
                            'model' => html_entity_decode($productsByTotalData['sku']),
                            'quantity' => (int)$productsByTotalData['quantity'],
                            'total' => (float)$productsByTotalData['total']
                        ); 
                    }
                }


                $filterGroups = array('day', 'week', 'month', 'year');
                foreach ($filterGroups as $key => $filterGroup) {
                    $filter_data['filter_group'] = $filterGroup;
                    $OrdersBy['orders_by_' . $filterGroup] = $this->getOrders($filter_data);
                    $customersBy['customers_by_' . $filterGroup] = $this->getCustomer($filter_data);
                }
                 
                //orders by day
                if(!empty($OrdersBy['orders_by_day'])){
                    foreach($OrdersBy['orders_by_day'] as $orderByDayData){
                        $ordersByDay[] = array(
                            'date_start' => html_entity_decode($orderByDayData['date_start']),
                            'date_end' => html_entity_decode($orderByDayData['date_end']),
                            'orders' => (int)$orderByDayData['orders'],
                            'products' => (int)$orderByDayData['products'],
                            'tax' => (float)$orderByDayData['tax'],
                            'total' => (float)$orderByDayData['total']
                        ); 
                    }
                }

                // customers by day
                if(!empty($customersBy['customers_by_day'])){
                    foreach($customersBy['customers_by_day'] as $customersByDayData){
                        $customersByDay[] = array(
                            'date_start' => html_entity_decode($customersByDayData['date_start']),
                            'date_end' => html_entity_decode($customersByDayData['date_end']),
                            'customers' => (int)$customersByDayData['customers']
                        ); 
                    }
                }

                // orders by week
                if(!empty($OrdersBy['orders_by_week'])){
                    foreach($OrdersBy['orders_by_week'] as $orderByWeekData){
                        $ordersByWeek[] = array(
                            'date_start' => html_entity_decode($orderByWeekData['date_start']),
                            'date_end' => html_entity_decode($orderByWeekData['date_end']),
                            'orders' => (int)$orderByWeekData['orders'],
                            'products' => (int)$orderByWeekData['products'],
                            'tax' => (float)$orderByWeekData['tax'],
                            'total' => (float)$orderByWeekData['total']
                        ); 
                    }
                }
               

                // customers by week
                if(!empty($customersBy['customers_by_week'])){
                    foreach($customersBy['customers_by_week'] as $customersByWeekData){
                        $customersByWeek[] = array(
                            'date_start' => html_entity_decode($customersByWeekData['date_start']),
                            'date_end' => html_entity_decode($customersByWeekData['date_end']),
                            'customers' => (int)$customersByWeekData['customers']
                        ); 
                    }
                }

                // orders by month
                if(!empty($OrdersBy['orders_by_month'])){
                    foreach($OrdersBy['orders_by_month'] as $orderByMonthData){
                        $ordersByMonth[] = array(
                            'date_start' => html_entity_decode($orderByMonthData['date_start']),
                            'date_end' => html_entity_decode($orderByMonthData['date_end']),
                            'orders' => (int)$orderByMonthData['orders'],
                            'products' => (int)$orderByMonthData['products'],
                            'tax' => (float)$orderByMonthData['tax'],
                            'total' => (float)$orderByMonthData['total']
                        ); 
                    }
                }
                
                // customers by month
                if(!empty($customersBy['customers_by_month'])){
                    foreach($customersBy['customers_by_month'] as $customersByMonthData){
                        $customersByMonth[] = array(
                            'date_start' => html_entity_decode($customersByMonthData['date_start']),
                            'date_end' => html_entity_decode($customersByMonthData['date_end']),
                            'customers' => (int)$customersByMonthData['customers']
                        ); 
                    }
                }

                // orders by year
                if(!empty($OrdersBy['orders_by_year'])){
                    foreach($OrdersBy['orders_by_year'] as $orderByYearData){
                        $ordersByYear[] = array(
                            'date_start' => html_entity_decode($orderByYearData['date_start']),
                            'date_end' => html_entity_decode($orderByYearData['date_end']),
                            'orders' => (int)$orderByYearData['orders'],
                            'products' => (int)$orderByYearData['products'],
                            'tax' => (float)$orderByYearData['tax'],
                            'total' => (float)$orderByYearData['total']
                        ); 
                    }
                }

                // customers by year
                if(!empty($customersBy['customers_by_year'])){
                    foreach($customersBy['customers_by_year'] as $customersByYearData){
                        $customersByYear[] = array(
                            'date_start' => html_entity_decode($customersByYearData['date_start']),
                            'date_end' => html_entity_decode($customersByYearData['date_end']),
                            'customers' => (int)$customersByYearData['customers']
                        ); 
                    }
                }

                $interval = date_diff(date_create($filterEndDate), date_create($filterStartDate)); 
                $lastDateEnd = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($filterStartDate)));
                $lastDateStart = date('Y-m-d H:i:s', strtotime($interval->format('%R%a day'), strtotime($lastDateEnd)));

                $filter_data = array(
                    'filter_date_start'	     => $lastDateStart,
                    'filter_date_end'	     => $lastDateEnd,
                    'filter_group'           => ''
                );

                // last orders
                $lastOrdersDatas = $this->getOrders($filter_data);
                if(!empty($lastOrdersDatas)){
                    foreach($lastOrdersDatas as $lastOrdersData){
                        $lastOrders[] = array(
                            'date_start' => html_entity_decode($lastOrdersData['date_start']),
                            'date_end' => html_entity_decode($lastOrdersData['date_end']),
                            'orders' => (int)$lastOrdersData['orders'],
                            'products' => (int)$lastOrdersData['products'],
                            'tax' => (float)$lastOrdersData['tax'],
                            'total' => (float)$lastOrdersData['total']
                        ); 
                    }
                }

                // last customers
                $lastCustomersDatas = $this->getCustomer($filter_data);
                if(!empty($lastCustomersDatas)){
                    foreach($lastCustomersDatas as $lastCustomersData){
                        $lastCustomers[] = array(
                            'date_start' => html_entity_decode($lastCustomersData['date_start']),
                            'date_end' => html_entity_decode($lastCustomersData['date_end']),
                            'customers' => (int)$lastCustomersData['customers']
                        ); 
                    }
                }

                $result = array(
                    'filter_date_start' => html_entity_decode($startdate),
                    'filter_date_end' => html_entity_decode($enddate),
                    'products_by_quantity' => $productsByQuantity,
                    'products_by_total' => $productsByTotal,
                    'orders_by_day' => $ordersByDay,
                    'orders_by_week' => $ordersByWeek,
                    'orders_by_month' => $ordersByMonth,
                    'orders_by_year' => $ordersByYear,
                    'customers_by_day' => $customersByDay,
                    'customers_by_week' => $customersByWeek,
                    'customers_by_month' => $customersByMonth,
                    'customers_by_year' => $customersByYear,
                    'last_orders' => $lastOrders,
                    'last_customers' => $lastCustomers
                
                );
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    public function getPurchased($data = array()) { 
        $connection = $this->resource->getConnection();  
        $salesOrderTable = $this->resource->getTableName('sales_order');
        $salesOrderItemsTable = $this->resource->getTableName('sales_order_item');
        $supermaxOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        
        $sql = "SELECT soi.name, soi.sku, SUM(soi.qty_ordered) AS quantity, SUM(soi.row_total_incl_tax) AS total FROM $salesOrderItemsTable soi LEFT JOIN $salesOrderTable so ON (soi.order_id = so.entity_id) RIGHT JOIN $supermaxOrderTable spo ON (spo.order_id = so.entity_id)";
        

        $userId =$this->supermaxSession->getPosUserId();
        $sql .= " WHERE spo.pos_user_id = '" . (int)$userId . "'";
        
		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(so.created_at) >= '" .$data['filter_date_start']. "'";
        }
        
		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(so.created_at) <= '" .$data['filter_date_end']. "'";
		}

		$sql .= " GROUP BY soi.product_id";

		if (isset($data['sort']) && $data['sort'] == "quantity") {
			$sql .= " ORDER BY quantity";
		} else {
			$sql .= " ORDER BY total";
        }

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}
		$query =  $connection->query($sql);
        return $query;
    }

    public function getOrders($data = array()) {
        $connection = $this->resource->getConnection();  
        $salesOrderTable = $this->resource->getTableName('sales_order');
        $salesOrderItemsTable = $this->resource->getTableName('sales_order_item');
        // $salesOrderTaxTable = $this->resource->getTableName('sales_order_tax');
        $supermaxOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');

        $sql = "SELECT MIN(so.created_at) AS date_start, MAX(so.created_at) AS date_end, COUNT(*) AS `orders`, SUM((SELECT SUM(soi.qty_ordered) FROM $salesOrderItemsTable soi WHERE soi.order_id = so.entity_id GROUP BY soi.order_id)) AS products, SUM(so.tax_amount) AS tax, SUM(so.grand_total) AS `total` FROM $supermaxOrderTable spo LEFT JOIN $salesOrderTable so ON (spo.order_id = so.entity_id)";
        
        $userId =$this->supermaxSession->getPosUserId();
        $sql .= " WHERE spo.pos_user_id = '" . (int)$userId. "'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(so.created_at) >= '" .$data['filter_date_start']. "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(so.created_at) <= '" .$data['filter_date_end']. "'";
		}

		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'week';
		}

		switch($group) {
			case 'day';
				$sql .= " GROUP BY YEAR(so.created_at), MONTH(so.created_at), DAY(so.created_at)";
				break;
			default:
			case 'week':
				$sql .= " GROUP BY YEAR(so.created_at), WEEK(so.created_at)";
				break;
			case 'month':
				$sql .= " GROUP BY YEAR(so.created_at), MONTH(so.created_at)";
				break;
			case 'year':
				$sql .= " GROUP BY YEAR(so.created_at)";
				break;
		}

		$sql .= " ORDER BY so.created_at DESC";

		$query = $connection->query($sql);
		return $query;
    }

    public function getCustomer($data = array()) {
        $connection = $this->resource->getConnection();  
        $customerTable = $this->resource->getTableName('customer_entity');
        $supermaxCustomerTable = $this->resource->getTableName('ah_supermax_pos_customer');

        $sql = "SELECT MIN(ce.created_at) AS date_start, MAX(ce.created_at) AS date_end, COUNT(*) AS customers FROM $customerTable ce RIGHT JOIN $supermaxCustomerTable spc ON (spc.customer_id = ce.entity_id)";

        $userId =$this->supermaxSession->getPosUserId();
		$sql .= " WHERE spc.pos_user_id = '" .(int)$userId. "'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(ce.created_at) >= '" .$data['filter_date_start']. "'";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(ce.created_at) <= '" .$data['filter_date_end']. "'";
		}

		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'week';
		}

		switch($group) {
			case 'day';
				$sql .= " GROUP BY YEAR(ce.created_at), MONTH(ce.created_at), DAY(ce.created_at)";
				break;
			default:
			case 'week':
				$sql .= " GROUP BY YEAR(ce.created_at), WEEK(ce.created_at)";
				break;
			case 'month':
				$sql .= " GROUP BY YEAR(ce.created_at), MONTH(ce.created_at)";
				break;
			case 'year':
				$sql .= " GROUP BY YEAR(ce.created_at)";
				break;
		}

		$sql .= " ORDER BY ce.created_at DESC";

        $query = $connection->query($sql);

		return $query;
	}
}