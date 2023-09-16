<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Customer\ValidateEboCustomerData;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Model\Data\AttributeMetadata;
use Magento\CustomerGraphQl\Api\ValidateCustomerDataInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * Validates gender value
 */
class ValidateApprovalStatus implements ValidateCustomerDataInterface
{
    /**
     * @var CustomerMetadataInterface
     */
    private $customerMetadata;

    /**
     * ValidateGender constructor.
     *
     * @param CustomerMetadataInterface $customerMetadata
     */
    public function __construct(CustomerMetadataInterface $customerMetadata)
    {
        $this->customerMetadata = $customerMetadata;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $customerData): void
    {
        if (isset($customerData['approval_status']) && $customerData['approval_status']) {
            /** @var AttributeMetadata $genderData */
            $options = $this->customerMetadata->getAttributeMetadata('approval_status')->getOptions();

            $isValid = false;
            foreach ($options as $optionData) {
                if ($optionData->getLabel() && $optionData->getLabel() == $customerData['approval_status']) {
                    $isValid = true;
                }
            }

            if (!$isValid) {
                throw new GraphQlInputException(
                    __('"%1" is not a valid approval_status value.', $customerData['approval_status'])
                );
            }
        }
    }
}
