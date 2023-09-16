<?php
namespace Ibo\ProductUpdate\Model;

use Ibo\ProductUpdate\Api\ProductEanManagementInterface as ProductApiInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

class ProductEanManagement implements ProductApiInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ProductPushHelper
     */
    private $productPushHelper;

    /**
     * @var ProductAction
     */
    private $productAction;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param ProductPushHelper $productPushHelper
     * @param ProductAction $action
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        ProductPushHelper $productPushHelper,
        ProductAction $action
    ) {
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAction = $action;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Updates the specified product from the request payload.
     *
     * @api
     * @param string $sku
     * @param string $ean
     * @return boolean|string
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function updateProductAttributes($sku, $ean) {
        if (!empty($sku) || !empty($ean)) {
            $error = false;
            $oldProductData = [];
            $flag = true;
            if (isset($sku) && $sku !='' && isset($ean) && $ean !='') {
                $this->writeLog('Start sku => '.$sku);
                try {
                    $productObject = $this->productRepository->get($sku);
                        if ($productObject->getSku()) {      
                            $productId[] = $productObject->getId();
                            $oldProductData[$sku] = ['ean' => $productObject->getPackageLengthInCm()];
                            $this->writeLog(print_r(['OldproductData' => $oldProductData],true));
                            try {
                                $this->removeEanIfExist($ean);
                                $updatedProductData = [ 'ean' => $ean];
                                $this->productAction->updateAttributes($productId,$updatedProductData, 0);
                                $this->productPushHelper->updateCatalogServicePushData($productObject->getId());
                                $this->writeLog(print_r(['updatedProductData' => $updatedProductData],true));
                            } catch (\Exception $e) {                                
                                $messages[] = $sku.' =>'.' Cannot save product.';
                                $messages[] = $e->getMessage();
                                $error = true;
                                return $e->getMessage();
                            }
                        } else {                        
                            $this->writeLog('Product does not exist with sku => '.$sku);
                        }
                    } catch (\Magento\Framework\Exception\LocalizedException $e) {
                        $messages[] = $sku.' =>'.$e->getMessage();
                        $error = true;
                        return $e->getMessage();
                    }
                    $this->writeLog('End sku => '.$sku);
                } else {
                    return "Please verify request data, One of the field missing in request sku, ean.";
                }
            if ($error) {                 
                 $this->writeLog(implode(" || ",$messages));
                 return false;
            }
        }
        return true;
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
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ean_productupdate.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);        
        $logger->info($log);
    }
}