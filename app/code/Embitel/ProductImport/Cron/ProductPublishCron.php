<?php

namespace Embitel\ProductImport\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Embitel\ProductImport\Model\ProductPublish;

class ProductPublishCron
{
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'ebo/ebo_product_publish/cron_updated_hours';
    
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
     * @var ProductPublish
     */
    protected $productPublish;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param ProductPublish $productPublish
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        ProductStatus $productStatus,
        ProductPublish $productPublish
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->productStatus = $productStatus;
        $this->productPublish = $productPublish;
    }

    /**
     * Publish products for the last one hour.
     *
     * @return void
     */
    public function execute()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/product_publish_cron.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(__FILE__);
        
        //If cron is not active then do not proceed.
        if (!$this->productPublish->isCronActive()) {
            return;
        }

        //Get current GMT time.
        /*$dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $logger->info("current ".$current);
        
        //Get last hour time.. 
        //Current time is 2:30 then from: 01:00:00, to:01:59:59
        $updatedInHours = 1;
        
        $configHours = (int) $this->productPublish->getConfig(self::CRON_UPDATED_HOURS);
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
//            ->addAttributeToFilter('manual_unpublish', ['neq' => 1]);

        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter([
                ['attribute' => 'two_step_publish_cron', 'null' => true],
                ['attribute' => 'two_step_publish_cron', 'eq' => 0]
            ])
            ->addAttributeToFilter([
                ['attribute' => 'manual_unpublish', 'null' => true],
                ['attribute' => 'manual_unpublish', 'eq' => 0]
            ]);

        //Check whether to filter products by is_published attribute
        $isPublished = $this->productPublish->isPublishFilter();
        if (isset($isPublished) && $isPublished == 1) {
            $products->addAttributeToFilter('is_published', ['eq' => 1]);
        }
        if (isset($isPublished) && $isPublished == 2) {
            $products->addAttributeToFilter('is_published', ['eq' => 0]);
        }
        
        $logger->info($products->getSelect()->__toString());
        $logger->info("products->getSize ".$products->getSize());
        if ($products->getSize() > 0) {
            $this->productPublish->publishProducts($products, true);
        }
    }
}
