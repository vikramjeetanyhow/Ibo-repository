<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\CustomerGraphQl\Model\Customer\Address\CreateCustomerAddress as CreateCustomerAddressModel;
use Magento\CustomerGraphQl\Model\Customer\Address\ExtractCustomerAddressData;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Embitel\Quote\Helper\Data;

/**
 * Customers address create, used for GraphQL request processing
 */
class CreateCustomerAddress implements ResolverInterface
{
    /**
     * @var CreateCustomerAddressModel
     */
    private $createCustomerAddress;

    /**
     * @var ExtractCustomerAddressData
     */
    private $extractCustomerAddressData;

    /**
     * @param CreateCustomerAddressModel $createCustomerAddress
     * @param ExtractCustomerAddressData $extractCustomerAddressData
     */
    public function __construct(
        CreateCustomerAddressModel $createCustomerAddress,
        ExtractCustomerAddressData $extractCustomerAddressData,
        Data $helper
    ) {
        $this->createCustomerAddress = $createCustomerAddress;
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
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }

        $address = $this->createCustomerAddress->execute($context->getUserId(), $args['input']);

        $customerAddress = $this->extractCustomerAddressData->execute($address);

        $pinCheck = $this->helper->pinCodeSeriveCheck($customerAddress['postcode']);
        $pinData = json_decode($pinCheck,true);
        if(!(isset($pinData['errors']))) {
            foreach ($pinData as $index => $v) {
                if($v['is_serviceable']) {
                    $customerAddress['is_serviceable'] = true;
                } else {
                    $customerAddress['is_serviceable'] = false;
                }
            } 
        }
       //  $customerAddress['postcode']; exit;

        return $customerAddress;
    }
}
