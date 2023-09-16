<?php

namespace Embitel\ProductImport\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Embitel\ProductImport\Model\ProductEnable;

class ProductEnableCron
{
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'ebo/ebo_product_enable/cron_updated_hours';
    
    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;
    
    /**
     * @var ProductStatus
     */
    protected $productStatus;

    /**
     * @var ProductEnable
     */
    protected $productEnable;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param ProductEnable $productEnable
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        ProductStatus $productStatus,
        ProductEnable $productEnable
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->productStatus = $productStatus;
        $this->productEnable = $productEnable;
    }

    /**
     * Enable products for the last one hour.
     *
     * @return void
     */
    public function execute()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/product_enable_cron.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(__FILE__);
        
        //If cron is not active then do not proceed.
        if (!$this->productEnable->isCronActive()) {
            return;
        }

        //Get current GMT time.
        /*$dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $logger->info("current ".$current);
        
        //Get last hour time.. 
        //Current time is 2:30 then from: 01:00:00, to:01:59:59
        $updatedInHours = 1;
        
        $configHours = (int) $this->productEnable->getConfig(self::CRON_UPDATED_HOURS);
        if (!empty($configHours)) {
            $updatedInHours = $configHours;
        }
        
        $logger->info("updatedInHours ".$updatedInHours);
        $fromTime = date('Y-m-d H', strtotime("-".$updatedInHours." hour", strtotime($current)));
        $from = $fromTime . ":00:00";
        $to = date('Y-m-d H:i:s', strtotime($current));
        $logger->info("from ".$from);
        $logger->info("to ".$to);*/

        //Get simple products which are updated as per above time logic
//        $products = $this->productCollection->create()
//            ->addAttributeToFilter('type_id', 'simple')
//            ->addAttributeToFilter('updated_at', ['gteq' => $from])
//            ->addAttributeToFilter('updated_at', ['lteq' => $to])
//            ->addAttributeToFilter('manual_disable', ['neq' => 1]);
        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter([
                ['attribute' => 'two_step_status_cron', 'null' => true],
                ['attribute' => 'two_step_status_cron', 'eq' => 0]
            ])
            ->addAttributeToFilter([
                ['attribute' => 'manual_disable', 'null' => true],
                ['attribute' => 'manual_disable', 'eq' => 0]
            ]);
            //->addAttributeToFilter('manual_disable', ['neq' => 1]);
        
        //Check whether to filter products by status attribute
        $isEnabled = $this->productEnable->isEnableFilter();
        if (isset($isEnabled) && $isEnabled == 1) {
            $products->addAttributeToFilter('status', ['eq' => ProductStatus::STATUS_ENABLED]);
        }
        if (isset($isEnabled) && $isEnabled == 2) {
            $products->addAttributeToFilter('status', ['eq' => ProductStatus::STATUS_DISABLED]);
        }
        
        $logger->info($products->getSelect()->__toString());
        $logger->info("products->getSize ".$products->getSize());
        if ($products->getSize() > 0) {
            $this->productEnable->enableProducts($products, true);
        }
    }
}
