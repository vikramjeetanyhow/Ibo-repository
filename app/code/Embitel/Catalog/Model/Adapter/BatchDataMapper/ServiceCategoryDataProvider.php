<?php
/**
 *  Pusing service_category to ES
 *
 * PHP version 7.4
 *
 * @category  Magento
 * @package   Embitel\Catalog
 * @author    Hitendra Badiani <hitendra.badiani@embitel.com>
 * @copyright 2022 Embitel Technologies (I) Pvt Ltd
 */
declare(strict_types=1);

namespace Embitel\Catalog\Model\Adapter\BatchDataMapper;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
/**
 * 
 *
 * PHP version 7.4
 *
 * @category  Magento
 * @package   Embitel\Catalog
 * @author    Hitendra Badiani <hitendra.badiani@embitel.com>
 * @copyright 2022 Embitel Technologies (I) Pvt Ltd
 */
class ServiceCategoryDataProvider implements AdditionalFieldsProviderInterface
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
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
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
        
        /*foreach ($productIds as $productId) {     
            $data = $this->getProductById($productId); // you can get you data here
            $serviceCategory = $data->getResource()->getAttribute('service_category')->getFrontend()->getValue($data);
            if($serviceCategory!='' && $serviceCategory != 'Please Select'){
                $fields[$productId]['service_category'] = $serviceCategory;
                $fields[$productId]['service_category_value'] = $serviceCategory;
            }
        }*/
        foreach ($productIds as $productId) {     
            $data = $this->getProductById($productId); // you can get you data here
            if($data->getServiceCategory()!=''){
                $fields[$productId]['service_category'] = $data->getServiceCategory();
                $fields[$productId]['service_category_value'] = $data->getServiceCategory();
            }
        }
        return $fields;
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
