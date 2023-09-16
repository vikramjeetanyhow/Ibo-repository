<?php
/**
 * @package Embitel_CatalogInventory
 *
 */

namespace Embitel\CatalogInventory\Plugin;

use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use \Magento\Eav\Model\Config;

class StockAvailabilityZonePlugin{

    private ProductRepositoryInterface $_productRepository;
    private Config $_eavConfig;
    private ProductAction $productAction;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        Config $eavConfig,
        ProductAction $action
    ){

        $this->_productRepository = $productRepository;
        $this->_eavConfig = $eavConfig;
        $this->productAction = $action;
    }

    public function beforeUpdateStockItemBySku(
        \Magento\CatalogInventory\Model\StockRegistry $subject,
        $productSku,
        \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem)
    {
        $stockAvailableZone =  $stockItem->getExtensionAttributes()->getAvailabilityZone();

        if(empty($stockAvailableZone)) {
            return ;
        }

        if(is_array($stockAvailableZone) && count($stockAvailableZone) > 0) {
            $attribute = $this->_eavConfig->getAttribute('catalog_product', 'availability_zone'); //color is product attribute.
            $options = $attribute->getSource()->getAllOptions();
            $optionsExists = array();
            $optionValues = array();
            foreach ($options as $option) {
                if ($option['value'] > 0) {
                    $optionsExists[] = $option['label'];
                    $optionValues[$option['label']] = $option['value'];
                }
            }
            $stockZone = array();
            foreach ($stockAvailableZone as $zone){
                if(!in_array(strtoupper($zone), $optionsExists)){
                    throw new LocalizedException(__('Please Enter Valid Zone'));
                }
                $stockZone[] = $optionValues[$zone];
            }
            
            $product = $this->_productRepository->get($productSku);

            $updatedProductData = ['availability_zone' => implode(",",$stockZone)];
            $productId[] = $product->getId();
            $this->productAction->updateAttributes($productId, $updatedProductData, 0);
        }

       // return $stockItem;
    }
}
