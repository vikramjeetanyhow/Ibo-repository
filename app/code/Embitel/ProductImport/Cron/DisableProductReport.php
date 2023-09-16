<?php

namespace Embitel\ProductImport\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Embitel\ProductImport\Model\DisableProduct;

class DisableProductReport
{
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'ebo/ebo_product_enable/consolidated_report_hours';
    
    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

    /**
     * @var DisableProduct
     */
    protected $disableProduct;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param DisableProduct $disableProduct
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        DisableProduct $disableProduct
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->disableProduct = $disableProduct;
    }

    /**
     * Generate product change over consolidated report for the last updated products.
     *
     * @return void
     */
    public function execute()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/disabled_product_report_cron.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(__FILE__);
        
        //If cron is not active then do not proceed.
        if (!$this->disableProduct->isCronActive()) {
            return;
        }

        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $logger->info("current ".$current);
        
        //Get last hour time.. 
        //Current time is 2:30 then from: 01:00:00, to:01:59:59
        $updatedInHours = 24;
        
        $configHours = (int) $this->disableProduct->getConfig(self::CRON_UPDATED_HOURS);
        if (!empty($configHours)) {
            $updatedInHours = $configHours;
        }
        
        $logger->info("updatedInHours ".$updatedInHours);
        $fromTime = date('Y-m-d H', strtotime("-".$updatedInHours." hour", strtotime($current)));
        $from = $fromTime . ":00:00";
        $to = date('Y-m-d H:i:s', strtotime($current));
        $logger->info("from ".$from);
        $logger->info("to ".$to);

        //Get simple products which are updated as per above time logic
        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('disabled_at', ['gteq' => $from])
            ->addAttributeToFilter('disabled_at', ['lteq' => $to])
            ->addAttributeToSelect(
                    [
                        'sku', 
                        'name', 
                        'status', 
                        'enable_failure_report', 
                        'manual_disable', 
                        'manual_disable_reason'
                    ]
                );
        
        $logger->info($products->getSelect()->__toString());
        $logger->info("products->getSize ".$products->getSize());
        if ($products->getSize() > 0) {
            $this->disableProduct->generateReport($products, true);
        }
    }
}
