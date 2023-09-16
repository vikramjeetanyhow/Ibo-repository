<?php 

namespace Embitel\CustomerReports\Api; 
interface ReportManagementInterface {

	/**
	 * GET Customers report api
	 * @return $this
	 */
	
	public function getCustomers(); 

	/**
	 * GET Customers Order report api
	 * @return $this
	 */
	
	public function getCustomerOrders(); 
}