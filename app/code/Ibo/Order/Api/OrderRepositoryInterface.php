<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Order\Api;

/**
 * @api
 * @since 100.0.2
 */
interface OrderRepositoryInterface
{
    /**
     * GET for Post api
     * @param string $param
     * 
     * @return array
     */
    public function getOrderData();
}
