<?php
namespace Ibo\AvailabilityZone\Model\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;

class AvailabilityZonesDataProvider implements AdditionalFieldsProviderInterface
{
    /**
     * Get the product by id
     *
     * @var Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Initialization moving custom data into elastic search server
     *       
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ResourceConnection $resourceConnection
    ) {
        $this->productRepository = $productRepository;
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
        $data = '';
        $fields= [];
        $availableZones = '';
        foreach ($productIds as $productId) {     
            $data = $this->getProductById($productId);
            /*print_r($data->getAvailabilityZone());
            die;*/
            if($data->getAvailabilityZone()!=''){
                //$availableZones = $this->getAvailZoneLabel($data->getAvailabilityZone());
                //print_r($availableZones);die;
                /*if($availableZones != '') {
                    $availableZones = rtrim($availableZones ,',');
                }  */             
                //$fields[$productId]['ibo_availability_zone'] = $availableZones;
               $fields[$productId]['availability_zone'] =$data->getAvailabilityZone();
                // $fields[$productId]['availability_zone_value'] = ["gujarat","testing"];
            }
        }
       /* print_r($fields);
            die;*/
        return $fields;
    }

    protected function getAvailZoneLabel($optionds) { 
        $zones = [];
        $connection = $this->resourceConnection->getConnection();
        $sql = "select value from eav_attribute_option_value 
                where option_id in (".$optionds.")";        
        $zoneLabels = $connection->fetchAll($sql);
        if(!empty($zoneLabels)) { 
            foreach ($zoneLabels as $zoneLabel) {
               $zones[] = $zoneLabel['value'];
            }
        }
        return $zones;
    }

    /**
     * Get the product by id
     *
     * @param int $productId
     * @param bool $editMode
     * @param int|null $storeId
     *
     * @return \Magento\Catalog\Model\Product $product product object
     */
    public function getProductById($productId, $editMode = false, $storeId = null)
    {
        return $this->productRepository->getById($productId, $editMode, $storeId);
    }
}
