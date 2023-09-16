<?php

namespace Embitel\Facade\Cron;

use Embitel\Facade\Model\ProductSync;

class ProductSyncCron
{
    /**
     * @var ProductSync
     */
    protected $productSync;

    /**
     * @param ProductSync $productSync
     */
    public function __construct(
        ProductSync $productSync
    ) {
        $this->productSync = $productSync;
    }

    /**
     * Sync products to Facade.
     */
    public function execute()
    {
        $this->productSync->syncProducts();
    }
}
