<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Validator\EmailAddress as EmailValidator;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\EmailNotificationInterface;

/**
 * Class Resolver for Resend Confirmation Email
 */
class ResendConfirmationEmail implements ResolverInterface
{
    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AccountManagementInterface
     */
    private $customerAccountManagement;

    /**
     * @var EmailValidator
     */
    private $emailValidator;

    /**
     * @var EmailNotificationInterface
     */
    private $emailNotification;

    /**
     * RequestPasswordResetEmail constructor.
     *
     * @param AuthenticationInterface     $authentication
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface  $customerAccountManagement
     * @param StoreManagerInterface $storeManager
     * @param EmailValidator              $emailValidator
     */
    public function __construct(
        AuthenticationInterface $authentication,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $customerAccountManagement,
        StoreManagerInterface $storeManager,        
        EmailValidator $emailValidator
    ) {
        $this->authentication = $authentication;
        $this->customerRepository = $customerRepository;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->emailValidator = $emailValidator;
        $this->storeManager = $storeManager;        
    }

    /**
     * Send confirmation email request
     *
     * @param Field             $field
     * @param ContextInterface  $context
     * @param ResolveInfo       $info
     * @param array|null        $value
     * @param array|null        $args
     *
     * @return bool|Value|mixed
     *
     * @throws GraphQlInputException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['email'])) {
            throw new GraphQlInputException(__('You must specify an email address.'));
        }

        if (!$this->emailValidator->isValid($args['email'])) {
            throw new GraphQlInputException(__('The email address has an invalid format.'));
        }

        try {
            $customer = $this->customerRepository->get($args['email']);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__('Cannot resend the customer\'s confirmation email'), $e);
        }

        if (true === $this->authentication->isLocked($customer->getId())) {
            throw new GraphQlInputException(__('The account is locked'));
        }

        try {         
            $this->getEmailNotification()->newAccount($customer,
             AccountManagement::NEW_ACCOUNT_EMAIL_REGISTERED_NO_PASSWORD,
             '', $customer->getStoreId());
            return true;           
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__('Cannot resend the customer\'s confirmation email'), $e);
        }
    }

    /**
     * Get email notification
     *
     * @return EmailNotificationInterface
     * @deprecated 100.1.0
     */
    private function getEmailNotification()
    {
        if (!($this->emailNotification instanceof EmailNotificationInterface)) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(
                EmailNotificationInterface::class
            );
        } else {
            return $this->emailNotification;
        }
    }
}
