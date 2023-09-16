<?php
namespace Ibo\ProductUpdate\Model;

use Ibo\ProductUpdate\Api\ProductUpdateManagementInterface as ProductApiInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

class ProductUpdateManagement implements ProductApiInterface {

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductAction
     */
    private $productAction;

    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CollectionFactory $subclassCollection,
        ProductCollectionFactory $productCollectionFactory,
        ProductAction $action,
        ProductPushHelper $productPushHelper
    ) {
        $this->productRepository = $productRepository;
        $this->productAction = $action;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->subclassCol = $subclassCollection;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Updates the specified products in item array.
     *
     * @api
     * @param string $sku
     * @param float $weight
     * @param float $length
     * @param float $width
     * @param float $height
     * @return boolean
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function updateProduct($sku,$weight,$length,$width,$height,$ean) {
        
        if (!empty($sku) || !empty($weight) || !empty($length) || !empty($width) || !empty($height))
        {

            $error = false;
            $oldProductData = [];
            $categoryIds = [];
            $courierType = 0;
            $flag = true;
                $this->writeLog('Start sku => '.$sku);
                try {
                    $productObject = $this->productRepository->get($sku);
                        if($productObject->getSku())
                        {
                                $productId[] = $productObject->getId();
                                $oldProductData[$sku] = ['package_length_in_cm' => $productObject->getPackageLengthInCm(),
                                                         'package_height_in_cm' => $productObject->getPackageHeightInCm(),
                                                         'package_width_in_cm'  => $productObject->getPackageWidthInCm(),
                                                         'package_weight_in_kg' => $productObject->getPackageWeightInKg(),
                                                         'courier_type' => $productObject->getCourierType(),
                                                         'ean' => $productObject->getEan()
                                                        ];
                                $this->writeLog(print_r(['OldproductData' => $oldProductData],true));
                            try {
                                $categoryIds = $productObject->getCategoryIds();
                                $collection = $this->subclassCol->create();
                                if($collection->getSize() > 0 && count($categoryIds) > 0){
                                    $products_subclassids = array_unique(array_column($collection->getData(), 'subclass_id'));
                                    foreach ($categoryIds as $key => $catId) {
                                        if(in_array($catId, $products_subclassids)){
                                            $flag = false;
                                        }
                                    }
                                }
                                $courierType = ($flag) ? $this->calculateCourierFlag($length,$height,$width,$weight): 'F';
                                $updatedProductData = [ 'package_length_in_cm' => $length,
                                                        'package_height_in_cm' => $height,
                                                        'package_width_in_cm'  => $width,
                                                        'package_weight_in_kg' => $weight,
                                                        'courier_type' => $courierType,
                                                        'ean' => !empty($ean) ? $ean: $productObject->getEan(),
                                                        'two_step_publish_cron' => 0
                                                        ];
                                if (!empty($ean)) {
                                    $this->removeEanIfExist($ean);
                                }
                                $this->productAction->updateAttributes($productId, $updatedProductData, 0);
                                $this->productPushHelper->updateCatalogServicePushData($productObject->getId());
                                $this->writeLog(print_r(['updatedProductData' => $updatedProductData],true));
                            } catch (\Exception $e) {
                                $messages[] = $sku.' =>'.' Cannot save product.';
                                $messages[] = $e->getMessage();
                                $error = true;
                            }
                        }else{
                         $this->writeLog('Product does not exist with sku => '.$sku);
                        }
                    } catch (\Magento\Framework\Exception\LocalizedException $e) {
                             $messages[] = $sku.' =>'.$e->getMessage();
                             $error = true;
                    }
                $this->writeLog('End sku => '.$sku);


            if ($error) {
                 $this->writeLog(implode(" || ",$messages));
                 return false;
            }
        }else{
            return "Please verify request data, One of the field missing in request sku, length, height, width, weight.";
        }
        return true;
    }

    public function calculateCourierFlag($length,$height,$width,$weight)
    {
        $dimension = (($length * $height * $width) / 5000 * 1.2);
        $this->writeLog('Product dimension => '.$dimension);
        if($dimension < 10 && $weight < 10 && $length < 100 && $height < 100 && $width < 100)
        {
            $courierType = 'C';
            $this->writeLog('Product dimension fulfil => '.$courierType);
        }elseif(($dimension >= 10 && $dimension < 200) && ($weight >= 10 && $weight < 200) && $length < 100 && $height < 100 && $width < 100)
        {
            $courierType = 'P';
            $this->writeLog('Product dimension fulfil => '.$courierType);
        }else {
            $courierType = 'F';
            $this->writeLog('Product dimension fulfil => '.$courierType);
        }

        return $courierType;
    }

    /**
     * While updating EAN to any product, check if same EAN is already exist then remove from existing products.
     *
     * @param type $ean
     */
    public function removeEanIfExist($ean)
    {
        $products = $this->productCollectionFactory->create()
            ->addFieldToSelect('entity_id')
            ->addFieldToFilter('type_id', 'simple')
            ->addAttributeToFilter('ean', $ean);
        if ($products->getSize() > 0) {
            $productIds = [];
            foreach ($products as $product) {
                $productIds[] = $product->getId();
            }
            $this->productAction->updateAttributes($productIds, ['ean' => ''], 0);
            foreach ($productIds as $productId) {
                $this->productPushHelper->updateCatalogServicePushData($productId);
            }
            $this->writeLog("Removed EAN for product ids:");
            $this->writeLog(print_r($productIds, true));
        }
    }

    /* log for an API */
    public function writeLog($log)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/wms_productupdate.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($log);
    }
}
