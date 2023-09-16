<?php
namespace Embitel\TaxMaster\Api;

/**
 * @api
 */
interface TaxUpdateRepositoryInterface
{
    /**
     * Update Tax Class By HSN Code
     *
     * @param string $hsnCode
     * @param string $taxClass
     * @return boolean
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save($hsnCode, $taxClass);
}
