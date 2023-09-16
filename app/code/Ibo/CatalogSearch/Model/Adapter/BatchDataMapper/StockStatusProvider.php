<?php
namespace Ibo\CatalogSearch\Model\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Framework\App\ResourceConnection;

class StockStatusProvider implements AdditionalFieldsProviderInterface
{
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Mapping the static field
     *
     * @param $productIds product id's
     * @param $storeId    store id
     * 
     * @return array $fields fields object
     */
    public function getFields(array $productIds, $storeId)
    {  
        $fields = [];
        foreach ($productIds as $productId) {
            $stockStatus = $this->getStockStatus($productId);
            $fields[$productId]['ibo_stock_status'] = $stockStatus;
        }
        return $fields;
    }
   
    /**
     * get stock status
     *
     * @param int $productId
     * @return bool 
     */
    public function getStockStatus($productId)
    {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select stock_status from cataloginventory_stock_status where product_id = ".$productId;
        $result = $connection->fetchAll($sql);
        if (array_key_exists(0,$result)) {
            $isInStock = $result[0]['stock_status'];
        }
        else {
            $isInStock = 0;
        }
              
        return $isInStock;
    } 
}