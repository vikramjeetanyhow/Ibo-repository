<?php
 
namespace Ibo\Order\Model\IR;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Order
{
    CONST SALES_ORDER_PARTNER = 'sales_order_partner';
    CONST CUSTOMER_ENTITY = 'customer_entity';
    CONST SALES_ORDER = 'sales_order';
    CONST CUSTOMER_GROUP = 'customer_group';
    protected $resourceConnection;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig; 

    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;  
    }

    public function create(array $requestData)
    {
        $connection = $this->resourceConnection->getConnection();

        $orderId = !empty($requestData['order_id']) ? $requestData['order_id'] : 0;
        $partnerType = !empty($requestData['partner_type']) ? $requestData['partner_type'] : '';
        if(!empty($orderId)){
            $sopTable = $connection->getTableName(self::SALES_ORDER_PARTNER);
            $query = "SELECT entity_id FROM " . $sopTable . " WHERE `parent_id` = " . $orderId;
            $count = $connection->fetchAll($query);
            if(count($count) == 0){
                $sql = "INSERT INTO " . $sopTable . "(`parent_id`,`partner_type`,`status_flag`) VALUES (" . $orderId . ",'" . $partnerType . "',0)";
                $connection->query($sql);
            }
        }
    }

    public function getOrderConversionInfo()
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderPartner = $connection->getTableName(self::SALES_ORDER_PARTNER);
        $customerTable = $connection->getTableName(self::CUSTOMER_ENTITY);
        $salesOrderTable = $connection->getTableName(self::SALES_ORDER);
        $customerGroupTable = $connection->getTableName(self::CUSTOMER_GROUP);
        $subquery = "(SELECT mobilenumber from customer_entity where entity_id = (SELECT value from customer_entity_varchar where entity_id = ce.entity_id AND attribute_id = (SELECT attribute_id from eav_attribute where attribute_code = 'referrer_customer_id'))) AS referrer_mobilenumber";
        $colum = 'so.increment_id,so.customer_firstname,ce.mobilenumber,so.grand_total,sop.entity_id,cg.customer_campaign_id,so.coupon_code';
        $query = "SELECT " . $colum . " FROM " . $salesOrderPartner . " sop "
                . "INNER JOIN " . $salesOrderTable . " so ON so.entity_id = sop.parent_id "
                . "INNER JOIN " . $customerTable . " ce ON ce.entity_id = so.customer_id "
                . "INNER JOIN " . $customerGroupTable . " cg ON cg.customer_group_id = ce.group_id "
                . "WHERE sop.partner_type = 'ir_order_conversion' AND sop.status_flag = 0";

        return $connection->fetchAll($query);
    }

    public function updatePushedIRData($id,$statusCode){
        $connection = $this->resourceConnection->getConnection();
        $salesOrderPartner = $connection->getTableName(self::SALES_ORDER_PARTNER);
        $sql = "UPDATE " . $salesOrderPartner . " SET status_flag = " . $statusCode ." WHERE `entity_id` = " . $id;
        return $connection->query($sql);
    }
}