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
interface IboCartInterface
{
    /**
     * Retrieve cart by cart id
     *
     * @param mixed $cart_id
     * @return array
     */
    public function fetchCart($cart_id);
}
