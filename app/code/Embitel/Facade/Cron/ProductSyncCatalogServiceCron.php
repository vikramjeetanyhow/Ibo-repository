<?php

namespace Embitel\Facade\Cron;

use Embitel\Facade\Model\ProductServiceSync;

class ProductSyncCatalogServiceCron
{
    /**
     * @var ProductSync
     */
    protected $productSync;

    /**
     * @param ProductSync $productSync
     */
    public function __construct(
        ProductServiceSync $productSync
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
