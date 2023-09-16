<?php

namespace Embitel\ProductImport\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Embitel\ProductImport\Model\ProductTitleRegenerate;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
class ProductTitleUpdateCron
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
     * @var ProductTitleRegenerate
     */
    protected $productTitleRegenerate;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param ProductTitleRegenerate $productTitleRegenerate
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        ProductTitleRegenerate $productTitleRegenerate
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->productTitleRegenerate = $productTitleRegenerate;
    }

    /**
     * Product title update for the last one hour.
     *
     * @return void
     */
    public function execute()
    {
        $this->getProducts();
    }

    /**
     * Product title update for everday night.
     *
     * @return void
     */
    public function fullExecute() {
        $this->completeProductUpdate();
    }

    public function getProducts()
    {
        //If cron is not active then do not proceed.
        if (!$this->productTitleRegenerate->isCronActive()) {
            return;
        }

        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        //Get last hour time.. 
        //Current time is 2:30 then from: 01:00:00, to:01:59:59
        $fromTime = date('Y-m-d H', strtotime("-1 hour", strtotime($current)));
        $from = $fromTime . ":00:00";
        $to = date('Y-m-d H:i:s', strtotime($current));
        //$from = date('Y-m-d H:i:s', strtotime("-10 minute", strtotime($current)));
        //$to = date('Y-m-d H:i:s');

        //Get simple products which are updated as per above time logic
        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('updated_at', ['gteq' => $from])
            ->addAttributeToFilter('updated_at', ['lteq' => $to]);

        if ($products->getSize() > 0) {
            $this->productTitleRegenerate->updateProducts($products, true);
        }
    }

    public function completeProductUpdate()
    {
        //If cron is not active then do not proceed.
        if (!$this->productTitleRegenerate->isFullProductCronActive()) {
            return;
        }
        $products = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple');

        if ($products->getSize() > 0) {
            $this->productTitleRegenerate->updateProducts($products, true);
        }
    }
}
