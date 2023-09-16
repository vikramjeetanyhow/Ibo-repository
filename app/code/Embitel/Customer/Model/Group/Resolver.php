<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/** MDVA-33975_2.4.1.patch applied */
declare(strict_types=1);

namespace Embitel\Customer\Model\Group;

use Embitel\Customer\Model\ResourceModel\Group\Resolver as ResolverResource;

/**
 * Lightweight service for getting current customer group based on customer id
 */
class Resolver
{
    /**
     * @var ResolverResource
     */
    private $resolverResource;

    /**
     * @param ResolverResource $resolverResource
     */
    public function __construct(ResolverResource $resolverResource)
    {
        $this->resolverResource = $resolverResource;
    }

    /**
     * Return customer group id
     *
     * @param int $customerId
     * @return int|null
     */
    public function resolve(int $customerId) : ?int
    {
        return $this->resolverResource->resolve($customerId);
    }
}
