<?php

namespace Embitel\Customer\Api;

interface GetCustomerTokenInterface
{
    /**
     * @param int $customerId
     * @return mixed[]
     */
    public function getCustomerToken(int $customerId): array;
}
