<?php


namespace Ibo\ProductUpdate\Api;

/**
 * @api
 */
interface ProductEanManagementInterface
{
    /**
     * Updates the specified products in item array.
     *
     * @api
     * @param string $sku
     * @param string $ean          
     * @return boolean|string
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function updateProductAttributes($sku,$ean);
}