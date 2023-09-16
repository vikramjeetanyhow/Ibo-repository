<?php

namespace Emipro\Apichange\Model\Catalog;

class ProductRepository extends \Magento\Catalog\Model\ProductRepository
{
    protected function initializeProductData(array $productData, $createNew)
    {
        $_websiteIds = array();
        if (!$createNew) {
            $product = $this->get($productData['sku']);
            $_websiteIds = $product->getWebsiteIds();
        }

        $product = parent::initializeProductData($productData, $createNew);

        // If product is new, we will store all the values on global scope
        if ($createNew) {
            $productId = $product->getId();
            if (!$productId) {
                $product->setStoreId(0);
                $product->setWebsiteIds(array());
            }
        } else {

            $product->setWebsiteIds($_websiteIds);
            if (!isset($productData['media_gallery_entries'])) {
                $attid = $product->getAttributeSetId();
                $product->reset();
                $product->setAttributeSetId($attid);
                foreach ($productData as $key => $value) {
                    $product->setData($key, $value);
                }
            }
        }
        return $product;
    }
	
	/**
     * @inheritdoc
     */
    public function get($sku, $editMode = false, $storeId = null, $forceReload = false)
    {
        $product = parent::get($sku, $editMode = false, $storeId = null, $forceReload = false);
        $product->setl10nInHsnCode($product->getHsnCode());
        $brandID = '';
        if($product->getData('brand_Id') > 0){
            $brandID = $product->getData('brand_Id');
        }
        $product->setBrandId($brandID);
        $brandNamebyID = $product->getAttributeText('brand_Id');
        $product->setBrandName($brandNamebyID);

        $saleUOMName = '';
        if ($product->getData('sale_uom') > 0) {
            $saleUOMName = $product->getAttributeText('sale_uom');
        }
        $product->setSaleUom($saleUOMName);

        return $product;
    }
}
