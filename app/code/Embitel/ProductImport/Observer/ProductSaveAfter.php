<?php

namespace Embitel\ProductImport\Observer;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\ProductFactory as ProductResourceFactory;

class ProductSaveAfter implements ObserverInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductResourceFactory
     */
    protected $productResourceFactory;

    /**
     * @param ProductFactory $productFactory
     * @param ProductResourceFactory $productResourceFactory
     * @param Api $facadeApi
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductResourceFactory $productResourceFactory
    )
    {
        $this->productFactory = $productFactory;
        $this->productResourceFactory = $productResourceFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        try {
            if ($product->getTypeId() == 'simple' && $product->getTwoStepStatusCron() != 0) {
                $this->updateProductStatusCronFlag($product->getId());
            }
            if ($product->getTypeId() == 'simple' && $product->getTwoStepPublishCron() != 0) {
                $this->updateProductPublishCronFlag($product->getId());
            }
        } catch (Exception $ex) {
            $this->log(__METHOD__);
            $this->log("Error on product save: " . $ex->getMessage());
        }
    }

    /**
     * Update product status cron count.
     *
     * @param type $productId
     */
    protected function updateProductStatusCronFlag($productId)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setData('two_step_status_cron', 0);
        $product->setData('store_id', 0);
        $product->getResource()->saveAttribute($product, 'two_step_status_cron');
    }

    /**
     * Update product publish cron count.
     *
     * @param type $productId
     */
    protected function updateProductPublishCronFlag($productId)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setData('two_step_publish_cron', 0);
        $product->setData('store_id', 0);
        $product->getResource()->saveAttribute($product, 'two_step_publish_cron');
    }
    
    /**
     * Log to file.
     *
     * @param type $message
     */
    public function log($message)
    {
        $logFileName = BP . '/var/log/2step_cron_flag.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
    }
}
