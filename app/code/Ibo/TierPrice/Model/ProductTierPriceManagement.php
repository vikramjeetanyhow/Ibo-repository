<?php
namespace Ibo\TierPrice\Model;

use Ibo\TierPrice\Api\ProductTierPriceManagementInterface as ProductTierPriceApiInterface;
use Magento\Framework\App\ResourceConnection;

class ProductTierPriceManagement implements ProductTierPriceApiInterface {
 
    /**
     * @var ResourceConnection
     */
    protected $resources;

    protected $connection;   

    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ResourceConnection $resources
    ) {           
        $this->resources = $resources;
        $this->connection = $this->resources->getConnection();
    }
 
    /**
     * Get product TierPrice in prices array.
     *
     * @api
     * @param string $sku
     * @param int $customer_group_id
     * @param string $prize_zone     
     * @return mixed
     * @throws \Magento\Framework\Exception\NotFoundException     
     */
    public function getTierPrice($sku,$customer_group_id,$prize_zone){

        $response = $this->getData($sku,$customer_group_id,$prize_zone);
        return $response;
    } 
   
    public function getData($sku,$customer_group_id,$prize_zone)
    {
        $responseData = $result = [];        
        $productSql = "SELECT e.sku,tier_price.qty quantity,tier_price.value amount 
                        FROM `catalog_product_entity` AS `e`
                        JOIN catalog_product_entity_tier_price tier_price ON e.entity_id = tier_price.entity_id ";        
        $productSql .= " WHERE sku = '".$sku."' AND customer_group_id = ".$customer_group_id." AND customer_zone = '".$prize_zone."'";        
        $result = $this->connection->fetchAll($productSql);

        if(count($result) == 0 && $prize_zone != 'default'){
            $productSql = "SELECT e.sku,tier_price.qty quantity,tier_price.value amount 
                        FROM `catalog_product_entity` AS `e`
                        JOIN catalog_product_entity_tier_price tier_price ON e.entity_id = tier_price.entity_id ";        
            $productSql .= " WHERE sku = '".$sku."' AND customer_group_id = ".$customer_group_id." AND customer_zone = 'default'";            
            $result = $this->connection->fetchAll($productSql);
        }
        if(count($result) == 0){
            $productSql = "SELECT e.sku,1 quantity,cped.value amount 
                        FROM `catalog_product_entity` AS `e`
                        JOIN catalog_product_entity_decimal cped ON e.entity_id = cped.entity_id
                        AND cped.attribute_id = (SELECT attribute_id FROM eav_attribute
                        WHERE attribute_code = 'price' AND entity_type_id =
                        (SELECT entity_type_id FROM eav_entity_type
                        WHERE entity_type_code = 'catalog_product'))";        
            $productSql .= " WHERE sku = '".$sku."'" ;
            $result = $this->connection->fetchAll($productSql);
        }
        
        if(count($result) > 0){
            foreach ($result as $resultData) {
                $responseData['prices'][] = ["amount" => (float) $resultData['amount'] ,"quantity" => (int)$resultData['quantity']];
            }
        }
        if(count($responseData) > 0){             
             return [$responseData];  
        }
        $responseData['error'] = "Offer does not exist";
        return [$responseData];        
    }
}