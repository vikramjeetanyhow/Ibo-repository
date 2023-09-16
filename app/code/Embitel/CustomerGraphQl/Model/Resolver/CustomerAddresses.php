<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\CustomerGraphQl\Model\Customer\Address\ExtractCustomerAddressData;
use Embitel\Quote\Helper\Data;

/**
 * Customers addresses field resolver
 */
class CustomerAddresses implements ResolverInterface
{
    /**
     * @var ExtractCustomerAddressData
     */
    private $extractCustomerAddressData;

    /**
     * @param ExtractCustomerAddressData $extractCustomerAddressData
     */
    public function __construct(
        ExtractCustomerAddressData $extractCustomerAddressData,
        Data $helper
    ) {
        $this->extractCustomerAddressData = $extractCustomerAddressData;
        $this->helper = $helper;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var Customer $customer */
        $customer = $value['model'];

        $addressesData = [];
        $addresses = $customer->getAddresses();
        $postCodes = [];

        if (count($addresses)) {
            foreach ($addresses as $address) {
                $postCodes[] = $address->getPostCode();
                $addressesData[] = $this->extractCustomerAddressData->execute($address);
            }
        }
        
        $resultData = [];
        if(count($postCodes) > 0) {
            $postCodes = array_unique($postCodes);
            $postCodes = implode(',',$postCodes);  
            $pinCheck = $this->helper->pinCodeSeriveCheck($postCodes);
            $pinData = json_decode($pinCheck,true);
            
            if(!(isset($pinData['errors']))) {
                foreach ($pinData as $index => $v) {
                    if($v['is_serviceable']) {
                        $resultData[] = $v['post_code'];
                    }
                } 
            }
        } 
        
        foreach($addressesData as $key => $data) {
            if(in_array($data['postcode'],$resultData)) {
                $addressesData[$key]['is_serviceable'] = true;
            } else {
                $addressesData[$key]['is_serviceable'] = false;
            }
        }
    
        return $addressesData;
    }
}
