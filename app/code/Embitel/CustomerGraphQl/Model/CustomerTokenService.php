<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\CustomerGraphQl\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Model\CredentialsValidator;
use Magento\Integration\Model\Oauth\Token as Token;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Integration\Model\ResourceModel\Oauth\Token\CollectionFactory as TokenCollectionFactory;
use Magento\Integration\Model\Oauth\Token\RequestThrottler;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Event\ManagerInterface;

/**
 * @inheritdoc
 */
class CustomerTokenService extends \Magento\Integration\Model\CustomerTokenService
{
    /**
     * Token Model
     *
     * @var TokenModelFactory
     */
    private $tokenModelFactory;

    /**
     * @var Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * Customer Account Service
     *
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var \Magento\Integration\Model\CredentialsValidator
     */
    private $validatorHelper;

    /**
     * Token Collection Factory
     *
     * @var TokenCollectionFactory
     */
    private $tokenModelCollectionFactory;

    /**
     * @var RequestThrottler
     */
    private $requestThrottler;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * Initialize service
     *
     * @param TokenModelFactory $tokenModelFactory
     * @param AccountManagementInterface $accountManagement
     * @param TokenCollectionFactory $tokenModelCollectionFactory
     * @param \Magento\Integration\Model\CredentialsValidator $validatorHelper
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        TokenModelFactory $tokenModelFactory,
        AccountManagementInterface $accountManagement,
        TokenCollectionFactory $tokenModelCollectionFactory,
        CredentialsValidator $validatorHelper,
        ManagerInterface $eventManager = null,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct(
            $tokenModelFactory,
            $accountManagement,
            $tokenModelCollectionFactory,
            $validatorHelper,
            $eventManager
        );
        $this->_coreRegistry = $coreRegistry;
        $this->tokenModelFactory = $tokenModelFactory;
        $this->accountManagement = $accountManagement;
        $this->validatorHelper = $validatorHelper;
        $this->eventManager = $eventManager ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ManagerInterface::class);
        $this->_customerSession = $customerSession;
    }

    /**
     * @inheritdoc
     */
    public function createCustomerAccessToken($username, $password)
    {
        $this->validatorHelper->validate($username, $password);
        $this->getCustomRequestThrottler()->throttle($username, RequestThrottler::USER_TYPE_CUSTOMER);
        try {
            $customerDataObject = $this->accountManagement->authenticate($username, $password);
        } catch (\Exception $e) {
            $this->getCustomRequestThrottler()->logAuthenticationFailure($username, RequestThrottler::USER_TYPE_CUSTOMER);
            throw new AuthenticationException(
                __(
                    'The account sign-in was incorrect or your account is disabled temporarily. '
                    . 'Please wait and try again later.'
                )
            );
        }
        $this->_customerSession->setCustomerId($customerDataObject->getId());
        $this->eventManager->dispatch('customer_login', ['customer' => $customerDataObject]);
        $this->getCustomRequestThrottler()->resetAuthenticationFailuresCount($username, RequestThrottler::USER_TYPE_CUSTOMER);
        return $this->tokenModelFactory->create()->createCustomerToken($customerDataObject->getId())->getToken();
    }

    /**
     * Get request throttler instance
     *
     * @return RequestThrottler
     * @deprecated 100.0.4
     */
    private function getCustomRequestThrottler()
    {
        if (!$this->requestThrottler instanceof RequestThrottler) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(RequestThrottler::class);
        }
        return $this->requestThrottler;
    }
}
