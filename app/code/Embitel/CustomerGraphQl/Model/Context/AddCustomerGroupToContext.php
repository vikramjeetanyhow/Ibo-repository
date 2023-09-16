<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/** MDVA-33975_2.4.1.patch applied */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Context;

use Magento\Authorization\Model\UserContextInterface;
use Magento\GraphQl\Model\Query\ContextParametersInterface;
use Magento\GraphQl\Model\Query\ContextParametersProcessorInterface;
use Magento\Customer\Model\Group;
use Embitel\Customer\Model\Group\Resolver as CustomerGroupResolver;
use Magento\Customer\Api\GroupManagementInterface;


/**
 * @inheritdoc
 */
class AddCustomerGroupToContext implements ContextParametersProcessorInterface
{
    /**
     * @var CustomerGroupResolver
     */
    private $customerGroupResolver;

    /**
     * @var GetCustomerGroup
     */
    private $getCustomerGroup;

    /**
     * @var GroupManagementInterface
     */
    private $groupManagement;

    /**
     * Core event manager proxy
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager;

    /**
     * @param CustomerGroupResolver $customerGroupResolver
     */
    public function __construct(
        CustomerGroupResolver $customerGroupResolver,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        GroupManagementInterface $groupManagement
    ) {
        $this->customerGroupResolver = $customerGroupResolver;
        $this->groupManagement = $groupManagement;
        $this->_eventManager = $eventManager;
    }

    /**
     * @inheritdoc
     */
    public function execute(ContextParametersInterface $contextParameters): ContextParametersInterface
    {
        $customerGroupId = null;
        $extensionAttributes = $contextParameters->getExtensionAttributesData();
        if ($contextParameters->getUserType() === UserContextInterface::USER_TYPE_GUEST) {
            //$customerGroupId = Group::NOT_LOGGED_IN_ID;
            $customerGroupId = $this->groupManagement->getDefaultGroup()->getId();
            $this->_eventManager->dispatch('ibo_visitor');

        } elseif (!empty($extensionAttributes) && $extensionAttributes['is_customer'] === true) {
            $customerGroupId = $this->customerGroupResolver->resolve((int) $contextParameters->getUserId());
        }
        if ($customerGroupId !== null) {
            $contextParameters->addExtensionAttribute('customer_group_id', (int) $customerGroupId);
        }
        return $contextParameters;
    }
}
