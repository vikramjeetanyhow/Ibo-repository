<?php

declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Transform single customer data from object to in array format
 */
class ExtractCustomerData extends \Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData
{
    /**
     * @var ServiceOutputProcessor
     */
    private $serviceOutputProcessor;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param ServiceOutputProcessor $serviceOutputProcessor
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ServiceOutputProcessor $serviceOutputProcessor,
        SerializerInterface $serializer,
        \Magento\Eav\Model\Config $eavConfig
    ) {
        $this->serviceOutputProcessor = $serviceOutputProcessor;
        $this->serializer = $serializer;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Curate default shipping and default billing keys
     *
     * @param array $arrayAddress
     * @return array
     */
    private function curateAddressData(array $arrayAddress): array
    {
        foreach ($arrayAddress as $key => $address) {
            if (!isset($address['default_shipping'])) {
                $arrayAddress[$key]['default_shipping'] = false;
            }
            if (!isset($address['default_billing'])) {
                $arrayAddress[$key]['default_billing'] = false;
            }
        }
        return $arrayAddress;
    }

    /**
     * Transform single customer data from object to in array format
     *
     * @param CustomerInterface $customer
     * @return array
     * @throws LocalizedException
     */
    public function execute(CustomerInterface $customer): array
    {
        $customerData = $this->serviceOutputProcessor->process(
            $customer,
            CustomerRepositoryInterface::class,
            'get'
        );
        $customerData['addresses'] = $this->curateAddressData($customerData['addresses']);
        if (isset($customerData['extension_attributes'])) {
            $customerData = array_merge($customerData, $customerData['extension_attributes']);
        }
        $customAttributes = [];
        if (isset($customerData['custom_attributes'])) {
            foreach ($customerData['custom_attributes'] as $attribute) {
                $isArray = false;
                if (is_array($attribute['value'])) {
                    $isArray = true;
                    foreach ($attribute['value'] as $attributeValue) {
                        if (is_array($attributeValue)) {
                            $customAttributes[$attribute['attribute_code']] = $this->serializer->serialize(
                                $attribute['value']
                            );
                            continue;
                        }
                        $customAttributes[$attribute['attribute_code']] = implode(',', $attribute['value']);
                        continue;
                    }
                }
                if ($isArray) {
                    continue;
                }
                $customAttributes[$attribute['attribute_code']] = $attribute['value'];
            }
        }

        $customerData = array_merge($customerData, $customAttributes);
        //Fields are deprecated and should not be exposed on storefront.
        $customerData['group_id'] = null;
        $customerData['id'] = null;

        if(isset($customerData['customer_secondary_email'])) {
            $customerData['customer_secondary_email'] = $customerData['customer_secondary_email'];
        } else {
            $customerData['customer_secondary_email'] = '';
        }

        if(isset($customerData['approval_status'])) {
            $attribute = $this->eavConfig->getAttribute('customer', 'approval_status');
            $customerData['approval_status'] = $attribute->getSource()->getOptionText($customerData['approval_status']);
        } else {
            $customerData['approval_status'] = '';
        }

        $customerData['customer_increment_id'] = (int)$customer->getId();

        $customerData['customer_insurance_number'] = isset($customerData['customer_insurance_number'])?$customerData['customer_insurance_number'] : '';
        $customerData['customer_insurance_contact_no'] = isset($customerData['insurance_contact_number'])?$customerData['insurance_contact_number'] : '';
        $customerData['customer_insurance_agency'] =  isset($customerData['insurance_agency'])?$customerData['insurance_agency'] : '';

        $customerData['model'] = $customer;

        //'dob' is deprecated, 'date_of_birth' is used instead.
        if (!empty($customerData['dob'])) {
            $customerData['date_of_birth'] = $customerData['dob'];
        }
        if(isset($customerData['taxvat']) && ($customerData['taxvat'] != 'UNREGISTERED') && ($customerData['taxvat'] != '')) {
            $customerData['taxvat'] = $customerData['taxvat'];
        } else {
            $customerData['taxvat'] = '';
        }

        return $customerData;
    }
}
