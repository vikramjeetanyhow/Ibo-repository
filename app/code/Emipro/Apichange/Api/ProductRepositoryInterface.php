<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Emipro\Apichange\Api;

/**
 * @api
 * @since 100.0.2
 */
interface ProductRepositoryInterface
{


    /**
     * Get info about product by product SKU
     *
     * @param string $sku
     * @param bool $editMode
     * @param int|null $storeId
     * @param bool $forceReload
     * @return \Emipro\Apichange\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($sku, $editMode = false, $storeId = null, $forceReload = false);


}
