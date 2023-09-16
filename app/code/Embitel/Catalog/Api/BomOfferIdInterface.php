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
interface BomOfferIdInterface
{
    /**
     * Retrieve list of BOM offer ids
     *
     * @param mixed $bom_skus
     * @return string
     */
    public function getList($bom_skus);
}
