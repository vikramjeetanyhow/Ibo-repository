<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Sitemap\Api;

/**
 * @api
 * @since 100.0.2
 */
interface CategoryRepositoryInterface
{
    /**
     * GET API
     * @api
     * 
     * @return array
     */
    public function get();

    /**
     * GET for Post api
     * @api
     * 
     * @return array
     */
    public function update();

        /**
     * GET API
     * @api
     * 
     * @return array
     */
    public function getBrand();

    /**
     * GET for Post api
     * @param string $param
     * 
     * @return array
     */
    public function updateBrand();
}
