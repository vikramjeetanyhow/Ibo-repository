<?php

namespace Ibo\SelSpecification\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Ibo\SelSpecification\Model\ProductSelSpecification;

class SelSpecificationCron
{
    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

    /**
     * @var ProductSelSpecification
     */
    protected $productSelSpecification;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param ProductSelSpecification $productSelSpecification
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        ProductSelSpecification $productSelSpecification
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->productSelSpecification = $productSelSpecification;
    }

    /**
     * Enable products for the last one hour.
     *
     * @return void
     */
    public function execute()
    {
        //If cron is not active then do not proceed.
        if (!$this->productSelSpecification->isCronActive()) {
            return;
        }

        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();

        //Get last hour time.. 
        //Current time is 2:30 then from: 12:00:00, to:02:30:00
        $updatedInHours = 2;

        //Read hours of last updated products from configuration
        $configHours = (int) $this->productSelSpecification->getProductUpdatedInLastHours();
        if (!empty($configHours)) {
            $updatedInHours = $configHours;
        }

        $fromTime = date('Y-m-d H', strtotime("-".$updatedInHours." hour", strtotime($current)));
        $from = $fromTime . ":00:00";
        $to = date('Y-m-d H:i:s', strtotime($current));

        $this->productSelSpecification->log("Product SEL Specification Cron START.");
        $this->productSelSpecification->log("from ".$from);
        $this->productSelSpecification->log("to ".$to);

        //Get simple products which are updated as per above time logic
        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('updated_at', ['gteq' => $from])
            ->addAttributeToFilter('updated_at', ['lteq' => $to]);

        //Apply batch size if configured from backend.
        $batchSize = (int) $this->productSelSpecification->getProductBatchSize();
        if ($batchSize > 0) {
            $products->setPageSize($batchSize);
        }

        $count = $products->getSize();
        $this->productSelSpecification->log("Total Products: " . $count);

        if ($count > 0) {
            foreach ($products as $product) {
                $this->productSelSpecification->updateProduct($product->getId(), 'id');
            }
        }
        $this->productSelSpecification->log("Product SEL Specification Cron END.");
    }
}
