<?php
namespace Ibo\CatalogSearch\Model\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\ImageFactory;

class ParentSkuDataProvider implements AdditionalFieldsProviderInterface
{
    /**
     * Get the product by id
     *
     * @var Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    private $resourceProduct;
    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    private $resourceConfigurable;

    protected $_childCollection = [];

    /**
     * @var ImageFactory
     */
    private $productImageFactory;

    /**
     * @var StockRegistryInterface|null
     */
    private $stockRegistry;

    /**
     * Initialization moving custom data into elastic search server
     *       
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        Product $resourceProduct,
        Configurable $resourceConfigurable,
        StockRegistryInterface $stockRegistry,
        ResourceConnection $resourceConnection,
        ImageFactory $productImageFactory
    ) {
        $this->productRepository = $productRepository;
        $this->resourceProduct = $resourceProduct;
        $this->resourceConfigurable = $resourceConfigurable;
        $this->stockRegistry = $stockRegistry;
        $this->resourceConnection = $resourceConnection;
        $this->productImageFactory = $productImageFactory;
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
        $productData = '';
        $fields = [];
        
        foreach ($productIds as $productId) {
            // $parentSku = null;
            // $parentEntityId = null;
            // $instockValues = null;
            // $isPublishedValues = null;
            // $isEnabledValues = null;
            // $variantCount = 0;
            $imageUrl = $this->getImageUrl($productId);
            $typeAndSku = $this->getTypeId($productId);
            $fields[$productId]['product_type_id'] = $typeAndSku['type_id'];
            $fields[$productId]['image_url'] = $imageUrl;
            $fields[$productId]['sort_order_new'] = (int)$this->getProductSortOrder($productId);

            // if ($typeAndSku['type_id'] == 'simple') {
            //     $parentData = $this->getParentSku($productId);
            //     if(array_key_exists('sku',$parentData) &&
            //     array_key_exists('entity_id', $parentData)) {
            //         $parentSku = $parentData['sku'];
            //         $parentEntityId = $parentData['entity_id'];
            //         $instockValues = $parentData['in_stock_ids'];
            //         $isPublishedValues = $parentData['is_published_ids'];
            //         $isEnabledValues = $parentData['is_enabled_ids'];
            //         $variantCount = $parentData['variant_count'];
            //     } else {
            //         $parentSku = $typeAndSku['sku'];
            //         $parentEntityId = $productId;
            //     }                
            // } else {
            //     $parentSku = $typeAndSku['sku'];
            //     $parentEntityId = $productId;
            // }

            // $fields[$productId]['parent_sku'] = $parentSku;
            // $fields[$productId]['parent_entity_id'] = (int)$parentEntityId;
            // $fields[$productId]['in_stock_ids'] = $instockValues;            
            // $fields[$productId]['is_published_ids'] = $isPublishedValues;
            // $fields[$productId]['is_enabled_ids'] = $isEnabledValues;
            // $fields[$productId]['variant_count'] = (int)$variantCount;
            // $fields[$productId]['product_type_id'] = $typeAndSku['type_id'];
            // $fields[$productId]['image_url'] = $imageUrl;

        }
        return $fields;
    }
    protected function getProductSortOrder($productId) { 
        $sortOrder = 0;
        $connection = $this->resourceConnection->getConnection();
        $sql = "select cpev.value from catalog_product_entity_varchar as cpev 
                where cpev.attribute_id = (select attribute_id from eav_attribute where attribute_code = 'sort_order' and entity_type_id = 4) 
                and cpev.entity_id = (select entity_id from catalog_product_entity where entity_id = ". $productId .")";
        
        $result = $connection->fetchAll($sql);
        
        if (isset($result[0])) {
            $sortOrder = $result[0]['value'];
        } 
        return $sortOrder;
    }
    protected function getTypeId($productId) {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select type_id,sku from catalog_product_entity where entity_id = ". $productId;
        $result = $connection->fetchAll($sql);
        return $result[0];
    }

    public function getParentSku($productId) {
        $parentIds = $this->resourceConfigurable->getParentIdsByChild($productId);

        $parentSku = '';
        $parentData = [];
        if (!empty($parentIds)) {
            $parentData = $this->resourceProduct->getProductsSku($parentIds);
        }
        if(!empty($parentData)) {
            $variantCount = 0; 
            $instockIds = [];
            $isPublishedIds = [];
            $isEnabledIds = [];
            $childrenIds = $this->resourceConfigurable->getChildrenIds($parentIds[0]);
            $childrens = $childrenIds[0];
            foreach ($childrens as $key => $childId) {
                $inStock = $this->getStockStatus($childId);
                $isPublished = $this->getIsPublished($childId);
                $isEnabled = $this->getIsEnabled($childId);
                if ($inStock) {
                    $instockIds[] = $childId; 
                }
                if ($isPublished) {
                    $isPublishedIds[] = $childId;
                }
                if ($isEnabled) {
                    $isEnabledIds[] = $childId;
                }

                if($inStock && $isPublished && $isEnabled) {
                    $variantCount = ++$variantCount;
                }
            }
            $instockVariants = implode(',',$instockIds);
            $publishedVariants = implode(',',$isPublishedIds);
            $isEnabledVariants = implode(',', $isEnabledIds);
            $parentData[0]['in_stock_ids'] = $instockVariants;
            $parentData[0]['is_published_ids'] = $publishedVariants;
            $parentData[0]['is_enabled_ids'] = $isEnabledVariants;
            $parentData[0]['variant_count'] = $variantCount;
            return $parentData[0];
        } else {
            return $parentData;
        }
    }

    /**
     * get stock status
     *
     * @param int $productId
     * @return bool 
     */
    public function getStockStatus($productId)
    {
        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockRegistry->getStockItem($productId);
        $isInStock = $stockItem ? $stockItem->getIsInStock() : false;
        return $isInStock;
    } 

    public function getIsEnabled($productId) {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select cei.value from catalog_product_entity_int as cei 
                where cei.attribute_id = (select attribute_id from eav_attribute where attribute_code = 'status' and entity_type_id = 4) 
                and cei.entity_id = (select entity_id from catalog_product_entity where entity_id = ". $productId .")";
        $result = $connection->fetchAll($sql);

        if (array_key_exists(0,$result)) {
            $isEnabled = $result[0]['value'] == 1 ? true : false;
        }
        else {
            $isEnabled = false;
        }
        return $isEnabled;
    }

    public function getIsPublished($productId) {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select cei.value from catalog_product_entity_int as cei 
                where cei.attribute_id = (select attribute_id from eav_attribute where attribute_code = 'is_published' and entity_type_id = 4) 
                and cei.entity_id = (select entity_id from catalog_product_entity where entity_id = ". $productId .")";
        $result = $connection->fetchAll($sql);

        if (array_key_exists(0,$result)) {
            $isPublished = $result[0]['value'] == 1 ? true : false;
        }
        else {
            $isPublished = false;
        }
        return $isPublished;
    }

    protected function getImageUrl($productId) {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select cpev.value from catalog_product_entity_varchar as cpev 
                where cpev.attribute_id = (select attribute_id from eav_attribute where attribute_code = 'image' and entity_type_id = 4) 
                and cpev.entity_id = (select entity_id from catalog_product_entity where entity_id = ". $productId .")";
        
        $result = $connection->fetchAll($sql);
        
        if (!isset($result[0])) {
            $imagePath = 'no_selection';   
        } else {
            $imagePath = $result[0]['value'];
        }

        $image = $this->productImageFactory->create();
        $image->setDestinationSubdir('image')
            ->setBaseFile($imagePath);
        
        return $image->getUrl();
    } 
        
}