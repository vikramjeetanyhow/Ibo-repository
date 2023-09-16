<?php

namespace Embitel\Catalog\Observer;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class CategoryPrepareSaveObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->productCollection = $collectionFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $postData = $observer->getEvent()->getRequest();
        $categoryPostData = $postData->getPostValue();
        $category = $observer->getEvent()->getCategory();

        if (isset($categoryPostData['products_sku'])
            && is_string($categoryPostData['products_sku'])
        ) {

            $productsSku = explode(',', $categoryPostData['products_sku']);
            $productCollections = $this->productCollection->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('sku', $productsSku);

            if ($productCollections->getSize() > 0) {
                $productIds = [];
                foreach ($productCollections as $product) {
                    $productIds[$product->getId()] = '';
                }

                //To get existing category products
                $categoryProducts = $category->getProductCollection()
                    ->addAttributeToSelect('position');
                $existingCatProducts = [];
                if ($categoryProducts->getSize() > 0) {
                    foreach ($categoryProducts as $prod) {
                        $existingCatProducts[$prod->getId()] = $prod->getCatIndexPosition();
                    }
                }
                $finalProductIds = $productIds + $existingCatProducts;

                $category->setPostedProducts($finalProductIds);
            }
        }
    }
}
