<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\Customer\Model\Validator;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Validator\Name as ValidateName;

/**
 * Customer name fields validator.
 */
class Name extends ValidateName
{
    /**
     * Validate name fields.
     *
     * @param Customer $customer
     * @return bool
     */
    public function isValid($customer)
    {
        return count($this->_messages) == 0;
    }
}
