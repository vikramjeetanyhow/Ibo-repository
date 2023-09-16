<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\Customer;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;

/**
 * Get customer group
 */
class GetCustomerGroup
{
    /**
     * @var GroupManagementInterface
     */
    private $groupManagement;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @param GroupManagementInterface $groupManagement
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        GroupManagementInterface $groupManagement,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->groupManagement = $groupManagement;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get customer group by id
     *
     * @param int|null $customerId
     * @return int
     * @throws GraphQlNoSuchEntityException
     */
    public function execute(?int $customerId): int
    {
        if (!$customerId) {
            /* changed from not logged in to b2b customer i.e default customer group 
            to match ebo requirement */
            //$customerGroupId = GroupManagement::NOT_LOGGED_IN_ID;
            $customerGroupId = $this->groupManagement->getDefaultGroup()->getId();
        } else {
            try {
                $customer = $this->customerRepository->getById($customerId);
            } catch (NoSuchEntityException $e) {
                throw new GraphQlNoSuchEntityException(
                    __('Customer with id "%customer_id" does not exist.', ['customer_id' => $customerId]),
                    $e
                );
            }
            $customerGroupId = $customer->getGroupId();
        }
        return (int)$customerGroupId;
    }
}
