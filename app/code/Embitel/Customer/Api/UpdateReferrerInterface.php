<?php
/**
 * @package: Embitel_Customer
 * @Author: Embitel Technologies
 *
 */
namespace Embitel\Customer\Api;

interface UpdateReferrerInterface
{
    /**
     * GET for Post api
     * @param string $customer_id
     * @param string $referrer_customer_id
     * @param string $referrer_date
     * @return array
     */
    public function update($customer_id, $referrer_customer_id, $referrer_date);
}
