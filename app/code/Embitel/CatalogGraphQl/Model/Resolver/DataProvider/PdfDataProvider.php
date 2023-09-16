<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * PDF Data provider
 */
class PdfDataProvider
{

    public function __construct(
        \Magento\Catalog\Model\Product $productCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->productCollection = $productCollection;
        $this->_storeManager = $storeManager;
    }
    public function getPdfDataByProductId($productId, $storeId)
    {
        $product = $this->productCollection->load($productId);
        $pdfPath = $product->getResource()->getAttributeRawValue($product->getId(),'pdf_path',$storeId); 
        $pdfLabel = $product->getResource()->getAttributeRawValue($product->getId(),'pdf_label',$storeId); 
        
        $pdfData = [];

        if(!empty($pdfPath) && !empty($pdfLabel)) {
            $expPath = explode(',', $pdfPath);
            $expLabel = explode(',', $pdfLabel);

            foreach($expPath as $key => $pdfPathValue) {
                $pdfData[] = [
                    'pdf_label' => trim($expLabel[$key]),
                    'pdf_path' => trim($pdfPathValue)
                ]; 
            }
        }
        return $pdfData;
     }
}