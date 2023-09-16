<?php

namespace Embitel\Facade\Cron;

use Embitel\Facade\Model\ProductSync;

class ProductSyncOdooCron
{
    private ProductSync $productSync;

    /**
     * @param ProductSync $productSync
     */
    public function __construct(ProductSync $productSync)
    {
        $this->productSync = $productSync;
    }

    public function execute() {
        $this->productSync->syncOdooProducts();
        $this->productSync->updateOdooProducts();
    }

}