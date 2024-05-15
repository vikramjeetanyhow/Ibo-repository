<?php

namespace Embitel\ProductImport\Observer;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\ProductFactory as ProductResourceFactory;

class ProductSaveBefore implements ObserverInterface
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
        ProductResourceFactory $productResourceFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
        
    )
    {
        $this->productFactory = $productFactory;
        $this->productResourceFactory = $productResourceFactory;
        $this->productRepository = $productRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        $attribute = 'lot_control_parameters';
        if ($product->hasDataChanges() && $product->dataHasChangedFor($attribute)) {
            // Prevent saving changes
            $product->setDataChanges(false);
            // Add error message
            $message = __('The attribute "%1" is locked and cannot be edited.', $attribute);
            $product->addErrorInfo('catalog', 'error', $message);
        }

        return $this;
 
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
