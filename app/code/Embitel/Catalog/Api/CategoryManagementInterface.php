<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Catalog\Api;

/**
 * @api
 * @since 100.0.2
 */
interface CategoryManagementInterface
{
    /**
     * Retrieve list of categories
     *
     * @param int $rootCategoryId
     * @param int $depth
     * @throws \Magento\Framework\Exception\NoSuchEntityException If ID is not found
     * @return \Magento\Catalog\Api\Data\CategoryTreeInterface containing Tree objects
     */
    public function getTree($rootCategoryId = null, $depth = null);    

    /**
     * Provide the number of category count
     *
     * @return int
     */
    public function getCount();
}
