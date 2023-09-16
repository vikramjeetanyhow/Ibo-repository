<?php

namespace Embitel\ProductFilter\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Action;

class ProductCategoriesText
{
    /**
     * @var ProductFactory
     */
    protected $product;

    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var Action
     */
    protected $productAction;

    /**
     * @param ProductFactory $product
     * @param CollectionFactory $productCollection
     * @param Action $productAction
     */
    public function __construct(
        ProductFactory $product,
        CollectionFactory $productCollection,
        Action $productAction
    ) {
        $this->product = $product;
        $this->productCollection = $productCollection;
        $this->productAction = $productAction;
    }

    /**
     * Update product's "category_names" attribute
     */
    public function syncCategoryNames()
    {
        $this->log("start category name add: " . date("H:i:s"));
        $productCollection = $this->productCollection->create();
        foreach ($productCollection as $product) {
            $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
            $categoryNames = [];
            foreach ($categories as $category) {
                $categoryNames[] = $category->getName();
            }
            if (!empty($categoryNames)) {
                $this->saveCategoryName($product->getId(), implode(" ", $categoryNames));
            }
        }
        $this->log("end category add: " . date("H:i:s"));
    }

    /**
     * Update product attribute.
     *
     * @param type $productId
     * @param type $categoryNames
     */
    private function saveCategoryName($productId, $categoryNames)
    {
        try {
            $this->productAction->updateAttributes([$productId], ['category_names' => $categoryNames], 0);
        } catch (\Exception $e) {
            $this->log("Product ID: " . $productId . "=>" . $e->getMessage());
        }
    }

    /**
     * @param type $message
     */
    private function log($message)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/custom.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}
