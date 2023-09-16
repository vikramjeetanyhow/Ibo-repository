<?php 

namespace Embitel\CustomerReports\Model; 
use Magento\Framework\App\ResourceConnection; 

class ReportManagement { 
    
    protected $resourceConnection; 
    
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function getCustomers() 
    { 
        $finalResult = array();
        $connection = $this->resourceConnection->getConnection();
        $query = "select (select customer_group_code from customer_group where customer_group_id = group_id) as group_code, count(entity_id) as customers, year(created_at) as year, month(created_at) as month, '' as week from customer_entity group by group_id, month(created_at), year(created_at) union select (select customer_group_code from customer_group where customer_group_id = group_id) as group_code, count(entity_id) as customers, year(created_at) as year, month(created_at) as month, week(created_at) as week from customer_entity where month(created_at) = month(current_date) group by group_id, year(created_at), week(created_at)"; 
        $result = $connection->fetchAll($query);
        if(count($result)>0) { 
            foreach($result as $dataResult) { 
                if($dataResult['group_code'] != "General" && $dataResult['group_code'] != "Retailer" && $dataResult['group_code'] != "Wholesale" && $dataResult['group_code'] != "NOT LOGGED IN") { 
                    $finalResult[] = $dataResult;
                }
            }
        }
        return $finalResult;
    } 

    public function getCustomerOrders() 
    { 
        $finalResult = array();
        $connection = $this->resourceConnection->getConnection();
        $query = "select (select customer_group_code from customer_group where customer_group_id = a.group_id) as group_code, count(a.entity_id) as customers, year(a.created_at) as year, month(a.created_at) as month, '' as week from customer_entity a join sales_order o on a.entity_id = o.customer_id and o.status != 'canceled' group by a.group_id, month(a.created_at), year(a.created_at) union select (select customer_group_code from customer_group where customer_group_id = b.group_id) as group_code, count(b.entity_id) as customers, year(b.created_at) as year, month(b.created_at) as month, week(b.created_at) as week from customer_entity b join sales_order so on b.entity_id = so.customer_id and so.status != 'canceled' where month(b.created_at) = month(current_date) group by b.group_id, year(b.created_at), week(b.created_at)"; 
        $result = $connection->fetchAll($query); 
        if(count($result)>0) { 
            foreach($result as $dataResult) { 
                if($dataResult['group_code'] != "General" && $dataResult['group_code'] != "Retailer" && $dataResult['group_code'] != "Wholesale" && $dataResult['group_code'] != "NOT LOGGED IN") { 
                    $finalResult[] = $dataResult;
                }
            }
        }
        return $finalResult;
    } 
    
}