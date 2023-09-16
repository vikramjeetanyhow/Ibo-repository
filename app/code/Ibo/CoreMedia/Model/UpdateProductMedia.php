<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\CoreMedia\Model;

use Magento\Catalog\Model\ProductFactory;
use Ibo\CoreMedia\Api\UpdateProductMediaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

class UpdateProductMedia implements UpdateProductMediaInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,        
        ProductAction $action,
        ProductPushHelper $productPushHelper
    ) {
        $this->productFactory = $productFactory;
        $this->scopeConfig = $scopeConfig;
        $this->productAction = $action;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * @param string $esin
     * @param mixed $media
     * @return string
     */
    public function updateMedia($esin,$media)
    { 
        $this->addLog("================Request ".$esin." Start=============");
        $data = $productId = [];
        $data['status'] = false;
        $data['message'] = '';

        if(!empty(trim($esin))) {
            $product = $this->productFactory->create()->loadByAttribute('esin', trim($esin));            
            $mediaServiceurl = trim($this->scopeConfig->getValue("core_media/service/get_media_api"));
            if($product) {                
                $productId[] = $product->getId();
                $primaryImageurl = '';
                $additionalImageUrl = [];
                $mediaType = '';
                $updatedProductData = [];
                if(is_array($media)) {
                    foreach($media as $mediaData) {
                        if(trim($mediaData['media_type']) == 'IMAGE') {
                            $mediaType = 'images';
                        }
                        if(trim($mediaData['media_type']) == 'VIDEO') {
                            $mediaType = 'videos';
                        }
                        if(trim($mediaData['media_type']) == 'DOCUMENT') {
                            $mediaType = 'documents';
                        }

                        $imageName = $this->removeSpecialChar($product->getName());

                        if($mediaData['is_primary_for_store']) {
                            $primaryImageurl = $mediaServiceurl.'/v1/products/'.$mediaType.'/'.trim($mediaData['media_id']).'/'.$imageName.'-'.$mediaData['position'].'.'.trim($mediaData['media_extension']);
                            //$primaryImageurl = $mediaData['url'];
                        } else {
                            $additionalImageUrl[] = $mediaServiceurl.'/v1/products/'.$mediaType.'/'.trim($mediaData['media_id']).'/'.$imageName.'-'.$mediaData['position'].'.'.trim($mediaData['media_extension']);
                        }
                    }
                    if($primaryImageurl != '') {
                        $this->addLog('PrimaryImageUrl :'.$primaryImageurl);
                        $updatedProductData['base_image_custom'] = $primaryImageurl;                        
                    } else {
                        $updatedProductData['base_image_custom'] = '';
                    }
                    if(count($additionalImageUrl) > 0) {
                        $galleryImages = implode(',',$additionalImageUrl);
                        $this->addLog('GalleryImages :'.$galleryImages);
                        $updatedProductData['media_gallery_custom'] = $galleryImages;
                    } else {
                        $updatedProductData['media_gallery_custom'] = '';
                    }                  
                    $updatedProductData['two_step_publish_cron'] = 0;

                    //if(($primaryImageurl != '') || (count($additionalImageUrl) > 0)){ 
                        $this->productAction->updateAttributes($productId, $updatedProductData, 0);
                        $data['status'] = true;
                        $data['message'] = 'Product is updated'; 
                        $this->productPushHelper->updateCatalogServicePushData($product->getId());                      
                    // } else {
                    //     $data['message'] = 'Media Url Data is missing';
                    // }

                } else {
                    $data['message'] = 'Media is not in correct format';
                }
                
            } else {
                $data['message'] = 'Esin is Invalid';
            }
        } else {
            $data['message'] = 'Esin field value is missing';
        }

        $return['response'] = $data;
        if($data['status']){
            $this->addLog('Success Response ISIN '.$esin.' :'.json_encode($return));
        }else{
            $this->addLog('Failure Response ISIN '.$esin.' :'.json_encode($return));
        }
        $this->addLog("================Request ".$esin." End=============");
        
        return $return;

    }

    public function addLog($logData, $filename = "update_product_media_service.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        
        return $logEnable;
    }

    protected function removeSpecialChar($str){
     
        // Using preg_replace() function
        // to replace the word
        $res = preg_replace('/[^a-zA-Z0-9 ]/s','',$str);
        $data = strtolower(str_replace(' ','-', trim($res)));
        // Returning the result
        return $data;
    }
}