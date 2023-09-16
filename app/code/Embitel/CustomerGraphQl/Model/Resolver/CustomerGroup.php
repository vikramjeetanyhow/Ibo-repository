<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Embitel\CatalogGraphQl\Model\Resolver\Customer\GetCustomerGroup;

/**
 * Customer Group Code field resolver
 */
class CustomerGroup implements ResolverInterface
{
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        GetCustomerGroup $getCustomerGroup
    ) {
        $this->groupRepository = $groupRepository;
        $this->getCustomerGroup = $getCustomerGroup;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var CustomerInterface $customer */
        $customer = $value['model'];
        $groupId = $this->getCustomerGroup->execute((int)$customer->getId());
        $groupCode = $this->getGroupCode($groupId);       

        return $groupCode;
    }

    private function getGroupCode($groupId) 
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    } 
}
