<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Quotation\Api;

/**
 * @api
 * @since 100.0.2
 */
interface ProductRepositoryInterface
{
    /**
     * Product Create
     *
     * @param mixed $product
     * @return array
     */
    public function save($product);

    /**
     * Product Update
     *
     * @param mixed $product
     * @return boolean
     */
    public function update($product);
}
