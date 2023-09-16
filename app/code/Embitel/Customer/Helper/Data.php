<?php
 
namespace Embitel\Customer\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper
{
    CONST CUSTOMER_ENTITY = 'customer_entity';
    protected $resourceConnection;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig; 

    private $connection;

    public function __construct(
        Context $context, 
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;  
        $this->resoruceConnection();
        parent::__construct($context);
    }

    public function getCustomerInfo()
    {
        $hourRecord = !empty($this->scopeConfig->getValue("ebo_customer_export/moengage_cron/records_per_hour")) ? $this->scopeConfig->getValue("ebo_customer_export/moengage_cron/records_per_hour") : "24 hour";
        $customerTable = $this->connection->getTableName(self::CUSTOMER_ENTITY);
        $query = "SELECT c.*  FROM " . $customerTable . " c "
                 . "WHERE (c.updated_at >= date_sub(now(),interval " . $hourRecord . "))";

        return $this->connection->fetchAll($query);
    }

    public function isReferrerIdExists($referrerId)
    {
        $customerTable = $this->connection->getTableName(self::CUSTOMER_ENTITY);
        $query = "SELECT c.*  FROM " . $customerTable . " c "
                 . "WHERE c.entity_id = " . $referrerId;

        return $this->connection->fetchAll($query);
    }
    
    private function resoruceConnection(){
        if(!$this->connection){
            $this->connection = $this->resourceConnection->getConnection();
        }

        return $this->connection;
    }
}