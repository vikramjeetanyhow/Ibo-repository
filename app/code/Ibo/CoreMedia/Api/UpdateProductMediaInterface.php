<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\CoreMedia\Api;

/**
 * @api
 * @since 100.0.2
 */
interface UpdateProductMediaInterface
{
    /**
     * 
     * @param string $esin
     * @param mixed $media
     * @return string
     */
    public function updateMedia($esin,$media);
}