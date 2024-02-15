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

  /**
     * Retrieve all the categories ids
     *
     * @param mixed $ibo_category_ids
     * @return string
     */
    public function getMerchandiseCategoriesIds($ibo_category_ids);

    /**
     * magetno root id for brand catalog data push
     * 
     * @param string[] $brandCategoryIds
     * @return string
     */
    public function getBrandCategoryPushApi($brandCategoryIds);
}
