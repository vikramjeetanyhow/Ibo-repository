<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\CustomerGraphQl\Model\Customer\CreateCustomerAccount;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Newsletter\Model\Config;
use Magento\Store\Model\ScopeInterface;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;

/**
 * Create customer account resolver
 */
class CreateCustomer implements ResolverInterface
{

    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;

    /**
     * @var CreateCustomerAccount
     */
    private $createCustomerAccount;

    /**
     * @var Config
     */
    private $newsLetterConfig;

    /**
     * @var ValidateMobile
     */
    protected $validateMobile;

    /**
     *
     * @param ExtractCustomerData $extractCustomerData
     * @param CreateCustomerAccount $createCustomerAccount
     * @param Config $newsLetterConfig
     * @param ValidateMobile $validateMobile
     */
    public function __construct(
        ExtractCustomerData $extractCustomerData,
        CreateCustomerAccount $createCustomerAccount,
        Config $newsLetterConfig,
        ValidateMobile $validateMobile
    ) {
        $this->newsLetterConfig = $newsLetterConfig;
        $this->extractCustomerData = $extractCustomerData;
        $this->createCustomerAccount = $createCustomerAccount;
        $this->validateMobile = $validateMobile;
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
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }

        if (isset($args['input']['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['input']['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }


        if (isset($args['input']['mobilenumber']) && !isset($args['input']['email'])) {
             $mobilenumber = str_replace("+91", "", $args['input']['mobilenumber']);
             $args['input']['email'] = $mobilenumber.'@'.$mobilenumber.'.com';
        }

        /* check if firstname is not passed in graphql and set value for the same */
        if (!isset($args['input']['firstname'])) {
             $args['input']['firstname'] = '-';
        }

        /* check if lastname is not passed in graphql and set value for the same */
        if (!isset($args['input']['lastname'])) {
             $args['input']['lastname'] = '-';
        }


        if (isset($args['input']['mobilenumber']) && !$this->validateMobile->isMobileExist($args['input']['mobilenumber'])) {
            throw new GraphQlAlreadyExistsException(
                __('The Mobile number is already in use by another customer')
            );
        }

        $customer = $this->createCustomerAccount->execute(
            $args['input'],
            $context->getExtensionAttributes()->getStore()
        );

        $data = $this->extractCustomerData->execute($customer);
        return ['customer' => $data];
    }
}
