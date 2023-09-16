<?php
/**
 * @package Embitel_CatalogInventory
 *
 */

namespace Embitel\CatalogInventory\Plugin;

use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class StockUpdatePlugin{

    /**
     * @var Configurable
     */
    private $_catalogProductTypeConfigurable;
    /**
     * @var CollectionFactory
     */
    private $_productCollectionFactory;
    /**
     * @var StockItemRepository
     */
    private $_stockItem;
    /**
     * @var ProductFactory
     */
    private $_productModel;

    public function __construct(
        Configurable $catalogProductTypeConfigurable,
        CollectionFactory $productCollectionFactory,
        StockItemRepository $stockItemRepository,
        ProductFactory $productModel,
        StockRegistryInterface $stockRegistry
    ){
        $this->_catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_stockItem = $stockItemRepository;
        $this->_productModel = $productModel;
        $this->_stockRegistry = $stockRegistry;
    }

    public function afterSave(\Magento\CatalogInventory\Model\Stock\StockItemRepository $subject, $stockItem) {
        $parentByChild = $this->_catalogProductTypeConfigurable->getParentIdsByChild($stockItem->getProductId());
        if (isset($parentByChild[0])) {
            $parentId = $parentByChild[0];
            $parentStock = $this->_stockRegistry->getStockItem($parentId);
            if (!$parentStock->getIsInStock()) {
                $parentStock->setIsInStock(true);
                $this->_stockItem->save($parentStock);
            }
        } else {
            $configProduct = $this->_productModel->create()->load($stockItem->getProductId());
            if ($configProduct->getTypeId() == 'configurable') {
                $configStock = $this->_stockRegistry->getStockItem($configProduct->getId());
                if (!$configStock->getIsInStock()) {
                    $configStock->setIsInStock(true);
                    $this->_stockItem->save($configStock);
                }
            }
        }

        return $stockItem;
    }
}
