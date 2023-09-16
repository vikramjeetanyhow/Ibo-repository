<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Embitel\Banner\Model\BannerFactory;

/**
 * Banner Products Data provider
 */
class BannerProducts
{

    public function __construct(
        BannerFactory $bannerFactory
    ) {
         $this->bannerFactory = $bannerFactory;
    }
    public function getProductsData($bannerId)
    {
        $productSku = [];
        $collection = $this->bannerFactory->create()->getCollection()
                        ->addFieldToFilter('banner_id', $bannerId)
                        ->addFieldToSelect('products_sku');  
                       
         if($collection->getSize() > 0) {        
            $products_sku = array_column($collection->getData(), 'products_sku');
            $dataSku = reset($products_sku);
            $productSku = ($dataSku!='')? (explode(",",$dataSku)) : $dataSku;        
         }         
        return $productSku;
    }
}
