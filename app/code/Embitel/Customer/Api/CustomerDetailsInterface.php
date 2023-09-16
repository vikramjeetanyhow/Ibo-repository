<?php
/**
 * @package: Embitel_Customer
 * @Author: Embitel Technologies
 *
 */
namespace Embitel\Customer\Api;

interface CustomerDetailsInterface
{
    /**
     *
     * @param int $customerId
     * @return json
     */
    public function getCustomerById(int $customerId);
}
