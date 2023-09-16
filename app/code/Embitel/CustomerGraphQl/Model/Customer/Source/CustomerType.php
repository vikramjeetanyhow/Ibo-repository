<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\CustomerGraphQl\Model\Customer\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Api\CustomerMetadataInterface;

/**
 * Option Source for customer type.
 */
class CustomerType implements OptionSourceInterface
{

    /**
     * @var CustomerMetadataInterface
     */
    private $customerMetadata;

    /**
     * constructor.
     *
     * @param CustomerMetadataInterface $customerMetadata
     */
    public function __construct(
        CustomerMetadataInterface $customerMetadata
    ) {
        $this->customerMetadata = $customerMetadata;
    }
    
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->getOptions() as $value => $label) {
            $options[] = [
                'label' => $label,
                'value' => $value
            ];
        }

        return $options;
    }

    /**
     * Get option label by value.
     *
     * @param int $value
     * @return string|null
     */
    public function getLabel($value)
    {
        $options = $this->getOptions();

        return $options[$value] ?? null;
    }

    /**
     * Get customer type options.
     *
     * @return array
     */
    private function getOptions()
    {
        $return = [];
        $options = $this->customerMetadata->getAttributeMetadata('customer_type')->getOptions();
        foreach ($options as $optionData) {
            $return[$optionData->getValue()] = $optionData->getLabel();
        }
        return $return;
    }
}
