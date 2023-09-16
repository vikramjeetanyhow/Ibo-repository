<?php

namespace Embitel\Oodo\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class OodoPush extends AbstractHelper
{
    CONST OODO_PUSH_TABLE = 'oodo_customer_master';
    CONST CUSTOMER_ENTITY = 'customer_entity';
    protected $resourceConnection;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function create(array $requestData)
    {
        $connection = $this->resourceConnection->getConnection();

        $customerId = !empty($requestData['customer_id']) ? $requestData['customer_id'] : 0;
        if(!empty($customerId)){
            $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
            $query = "SELECT customer_id FROM " . $themeTable . " WHERE `customer_id` = " . $customerId;
            $count = $connection->fetchAll($query);
            if(count($count) == 0){
                $sql = "INSERT INTO " . $themeTable . "(`customer_id`) VALUES (" . $customerId . ")";
            }else{
                $sql = "UPDATE " . $themeTable . " SET `attempts` = 0 WHERE `customer_id` = " . $customerId;
            }
            $connection->query($sql);
        }
    }

    public function get()
    {
        $thresholdValue = $this->scopeConfig->getValue("oodo/customer_push/attempt_threshold");
        $attempThresholdValue = !empty($thresholdValue) ? $thresholdValue : 3;
        $connection = $this->resourceConnection->getConnection();
        $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
        $customerTable = $connection->getTableName(self::CUSTOMER_ENTITY);
        $query = "SELECT o.*" . " FROM " . $themeTable . " o "
                . "INNER JOIN " . $customerTable . " c ON c.entity_id = o.customer_id "
                . "INNER JOIN customer_group cg ON cg.customer_group_id = c.group_id "
                . "INNER JOIN customer_entity_int cei ON cei.entity_id = c.entity_id "
                . "INNER JOIN eav_attribute_option_value eaov ON eaov.option_id = cei.value "
                 . "WHERE attempts <= " . $attempThresholdValue
                 . " AND cg.customer_group_code != 'B2C'"
                 . " AND (o.created_at <= date_sub(now(),interval 2 minute)) LIMIT 100";

        return $connection->fetchAll($query);
    }

    public function getUnApprovedCustomers()
    {
        $thresholdValue = $this->scopeConfig->getValue("oodo/customer_push/attempt_threshold");
        $hourRecord = !empty($this->scopeConfig->getValue("oodo/customer_push/records_per_hour")) ? $this->scopeConfig->getValue("oodo/customer_push/records_per_hour") : "12 hour";
        $attempThresholdValue = !empty($thresholdValue) ? $thresholdValue : 3;
        $connection = $this->resourceConnection->getConnection();
        $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
        $customerTable = $connection->getTableName(self::CUSTOMER_ENTITY);
        $query = "SELECT c.mobilenumber,c.created_at,eaov.value AS approval_status,"
                . "c.updated_at,CONCAT(c.firstname,' ',(CASE WHEN c.lastname IS NULL THEN '' ELSE c.lastname END)) AS customer_name,"
                . "(select value from eav_attribute_option_value where option_id = (select value from customer_entity_int where entity_id = c.entity_id and attribute_id = (SELECT attribute_id from eav_attribute where attribute_code = 'customer_type'))) AS customer_type "
                . "FROM " . $customerTable . " c "
                . "INNER JOIN customer_entity_int cei ON cei.entity_id = c.entity_id "
                . "INNER JOIN eav_attribute_option_value eaov ON eaov.option_id = cei.value "
                 . "WHERE cei.attribute_id = (SELECT attribute_id from eav_attribute where attribute_code = 'approval_status')"
                 . " AND eaov.value != 'approved'"
                 . " AND (c.created_at >= date_sub(now(),interval " . $hourRecord . "))";
        return $connection->fetchAll($query);
    }

    public function updateFailureAttempt($id, $attempt){
        $connection = $this->resourceConnection->getConnection();
        $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
        $sql = "UPDATE " . $themeTable . " SET `attempts` = " . $attempt . " WHERE `id` = " . $id;
        return $connection->query($sql);
    }

    public function deletePushedOodoData($id){
        $connection = $this->resourceConnection->getConnection();
        $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
        $sql = "DELETE FROM " . $themeTable . " WHERE `id` = " . $id;
        return $connection->query($sql);
    }

    public function deletePushedCustomerData($customerId){
        $connection = $this->resourceConnection->getConnection();
        $themeTable = $connection->getTableName(self::OODO_PUSH_TABLE);
        $sql = "DELETE FROM " . $themeTable . " WHERE `customer_id` = " . $customerId;
        return $connection->query($sql);
    }
}
