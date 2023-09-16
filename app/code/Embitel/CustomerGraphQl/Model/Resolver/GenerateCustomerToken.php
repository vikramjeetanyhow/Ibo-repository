<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Embitel\Customer\Model\MobileCustomer;

/**
 * Customers Token resolver, used for GraphQL request processing.
 */
class GenerateCustomerToken implements ResolverInterface
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @param CustomerTokenServiceInterface $customerTokenService
     */
    public function __construct(
        CustomerTokenServiceInterface $customerTokenService,
        MobileCustomer $mobileCustomer
    ) {
        $this->customerTokenService = $customerTokenService;
        $this->mobileCustomer = $mobileCustomer;
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
        if (empty($args['userid'])) {
            throw new GraphQlInputException(__('Specify the "Email/Mobile Number" value.'));
        }

        if (empty($args['secret'])) {
            throw new GraphQlInputException(__('Specify the "Password/OTP" value.'));
        }

        try {
            if (filter_var($args['userid'], FILTER_VALIDATE_EMAIL)) {
                $token = $this->customerTokenService->createCustomerAccessToken($args['userid'], $args['secret']);
            } else {
                $token = $this->mobileCustomer->createCustomerAccessToken($args['userid']);
            }
            return ['token' => $token];
        } catch (AuthenticationException $e) {
            throw new GraphQlAuthenticationException(__($e->getMessage()), $e);
        } catch (LocalizedException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
    }
}
