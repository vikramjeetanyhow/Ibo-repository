<?php

namespace Embitel\ProductImport\Observer;

use Exception;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class OodoPriceUpdateAfter implements ObserverInterface
{
    /**
     * Cron status path
     */
    const XML_PATH_STATUS_FLAG = 'ebo/ebo_product_enable/reset_status_flag';

    /**
     * Cron publish path
     */
    const XML_PATH_PUBLISH_FLAG = 'ebo/ebo_product_publish/reset_publish_flag';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    
    protected $connection;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $offerIds = $observer->getOfferIds();
        $this->log("Total offer ids to process:" . count($offerIds));
        if (count($offerIds) < 1) {
            return true;
        }

        try {
            if ($this->getConfig(self::XML_PATH_STATUS_FLAG)) {
                $this->insertOrUpdate('two_step_status_cron', $offerIds);
            }
            if ($this->getConfig(self::XML_PATH_PUBLISH_FLAG)) {
                $this->insertOrUpdate('two_step_publish_cron', $offerIds);
            }
        } catch (Exception $ex) {
            $this->log(__METHOD__);
            $this->log("Error on updating publish/status flag: " . $ex->getMessage());
        }
    }

    /**
     * Get attribute id.
     *
     * @param type $attributeCode
     */
    protected function getAttributeId($attributeCode)
    {
        $tableNameEa = $this->connection->getTableName('eav_attribute');
        $tableNameEet = $this->connection->getTableName('eav_entity_type');
        return $this->connection->fetchOne("SELECT attribute_id FROM {$tableNameEa} WHERE attribute_code = '{$attributeCode}' AND entity_type_id = (SELECT entity_type_id FROM {$tableNameEet} WHERE entity_type_code = 'catalog_product')");
    }

    /**
     * Update product enable & publish cron count.
     *
     * @param type $attributeCode
     * @param type $offerIds
     */
    protected function insertOrUpdate($attributeCode, $offerIds)
    {        
        $tableNameCpe = $this->connection->getTableName('catalog_product_entity');
        $tableNameCpei = $this->connection->getTableName('catalog_product_entity_int');

        $attributeId = $this->getAttributeId($attributeCode);
        if ($attributeId < 1) {
            $this->log("Please check attribute: " . $attributeCode);
            return true;
        }

        $offerIdsString = implode(",", $offerIds);

        $queryRowId = "SELECT e.entity_id FROM {$tableNameCpe} e JOIN {$tableNameCpei} v2 ON e.entity_id = v2.entity_id AND v2.value != 0 AND v2.attribute_id = {$attributeId} WHERE e.sku IN ({$offerIdsString})";
        $rowIdsIn = $this->connection->fetchCol($queryRowId);

        if (count($rowIdsIn) > 0) {
            $where = ['attribute_id = ?' => (int)$attributeId, 'entity_id IN (?)' => $rowIdsIn];
            $this->connection->update($tableNameCpei, ['value' => 0], $where);
        }

        $queryNotInRowId = "SELECT e.entity_id FROM {$tableNameCpe} e LEFT JOIN {$tableNameCpei} v2 ON e.entity_id = v2.entity_id AND v2.attribute_id = {$attributeId} WHERE v2.value IS NULL AND e.sku IN ({$offerIdsString})";
        $rowIdsNotIn = $this->connection->fetchCol($queryNotInRowId);
        if (count($rowIdsNotIn) > 0) {
            $data = [];
            foreach ($rowIdsNotIn as $rowId) {
                $data[] = [
                    'attribute_id' => $attributeId,
                    'store_id' => 0,
                    'value' => 0,
                    'entity_id' => $rowId
                ];
            }
            $this->connection->insertMultiple($tableNameCpei, $data);
        }
    }
    
    /**
     * Log to file.
     *
     * @param type $message
     */
    public function log($message)
    {
        $logFileName = BP . '/var/log/2step_cron_flag.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
    }
}
