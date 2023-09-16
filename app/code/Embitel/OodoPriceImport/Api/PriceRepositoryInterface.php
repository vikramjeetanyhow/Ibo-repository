<?php
namespace Embitel\OodoPriceImport\Api;

use Embitel\OodoPriceImport\Api\Data\OodoPriceDataInterface;

/**
 * @api
 */
interface PriceRepositoryInterface
{
    /**
     * Create Price Record
     *
     * @param mixed $price
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function save($price);
}
