<?php
namespace Ibo\CatalogSearch\Model\Adapter\BatchDataMapper;

use Embitel\AdvancedSearch\Model\ResourceModel\Index;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Provide data mapping for price fields
 */
class CourierTypeFieldsProvider implements AdditionalFieldsProviderInterface
{ 
    /**
     * @var Index
     */
    private $resourceIndex;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Index $resourceIndexCustom
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Index $resourceIndexCustom,
        ResourceConnection $resourceConnection
    ) {
        $this->resourceIndexCustom = $resourceIndexCustom;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }
    
    /**
     * @inheritdoc
     */
    public function getFields(array $productIds, $storeId)
    { 
        $fields = [];
        foreach ($productIds as $productId) { 
            $courierType = $this->getCourierType($productId);
            if($courierType != '') {
                $fields[$productId]['ibo_courier_type'] = $courierType;
            }
        }
        return $fields;
    }

    protected function getCourierType($productId) {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select cpev.value from catalog_product_entity_varchar as cpev 
                where cpev.attribute_id = (select attribute_id from eav_attribute where attribute_code = 'courier_type' and entity_type_id = 4) 
                and cpev.entity_id = (select entity_id from catalog_product_entity where entity_id = ". $productId .")";
        
        $result = $connection->fetchAll($sql);
        $courierType = '';
        if (isset($result[0])) {
            $courierType = $result[0]['value'];
        }        
        return $courierType;
    }
}
