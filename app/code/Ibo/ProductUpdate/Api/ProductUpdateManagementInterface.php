<?php


namespace Ibo\ProductUpdate\Api;

/**
 * @api
 */
interface ProductUpdateManagementInterface
{
    /**
     * Updates the specified products in item array.
     *
     * @api
     * @param string $sku
     * @param float $weight
     * @param float $length
     * @param float $width
     * @param float $height
     * @param string $ean
     * @return boolean
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function updateProduct($sku,$weight,$length,$width,$height,$ean);
}
