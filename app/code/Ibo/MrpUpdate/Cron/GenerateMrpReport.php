<?php

namespace Ibo\MrpUpdate\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Ibo\MrpUpdate\Model\MrpReport;

class GenerateMrpReport
{
    /**
     * Cron updated hours
     */
    const CRON_UPDATED_HOURS = 'mrpprice_update/settings/consolidated_report_hours';
    
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
     * @var MrpReport
     */
    protected $mrpReport;

    /**
     * @param CollectionFactory $productCollection
     * @param DateTimeFactory $dateTime
     * @param MrpReport $mrpReport
     */
    public function __construct(
        CollectionFactory $productCollection,
        DateTimeFactory $dateTime,
        ProductStatus $productStatus,
        MrpReport $mrpReport
    ) {
        $this->productCollection = $productCollection;
        $this->dateTime = $dateTime;
        $this->productStatus = $productStatus;
        $this->mrpReport = $mrpReport;
    }

    /**
     * Generate MRP change over consolidated report for the last updated products.
     *
     * @return void
     */
    public function execute()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/mrp_report_cron.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(__FILE__);
        
        //If cron is not active then do not proceed.
        if (!$this->mrpReport->isCronActive()) {
            return;
        }

        //Get current GMT time.
        $dateModel = $this->dateTime->create();
        $current = $dateModel->gmtDate();
        $logger->info("current ".$current);
        
        //Get last hour time.. 
        //Current time is 2:30 then from: 01:00:00, to:01:59:59
        $updatedInHours = 24;
        
        $configHours = (int) $this->mrpReport->getConfig(self::CRON_UPDATED_HOURS);
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
            ->addAttributeToFilter('mrp_updated_at', ['gteq' => $from])
            ->addAttributeToFilter('mrp_updated_at', ['lteq' => $to])
            ->addAttributeToSelect(
                    [
                        'sku', 
                        'name', 
                        'status', 
                        'old_mrp', 
                        'mrp_changeover_message', 
                        'mrp', 
                        'mrp_updated_at', 
                        'department', 
                        'class', 
                        'subclass'
                    ]
                );
        
        $logger->info($products->getSelect()->__toString());
        $logger->info("products->getSize ".$products->getSize());
        if ($products->getSize() > 0) {
            $this->mrpReport->generateReport($products, true);
        }
    }
}
