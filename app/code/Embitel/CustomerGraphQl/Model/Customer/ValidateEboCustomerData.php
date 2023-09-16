<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Customer;

use Magento\CustomerGraphQl\Api\ValidateCustomerDataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * Customer data validation used during customer account creation and updating
 */
class ValidateEboCustomerData
{
    /**
     * @var ValidateCustomerDataInterface[]
     */
    private $validators = [];

    /**
     * ValidateCustomerData constructor.
     *
     * @param array $validators
     */
    public function __construct(
        $validators = []
    ) {
        $this->validators = $validators;
    }

    /**
     * Validate customer data
     *
     * @param array $customerData
     * @throws GraphQlInputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(array $customerData)
    {
        /** @var ValidateCustomerDataInterface $validator */
        foreach ($this->validators as $validator) {
            //print_r($validator);die;
            $validator->execute($customerData);
        }
    }
}
