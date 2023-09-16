<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\CustomerGraphQl\Model\Customer\Address\ExtractCustomerAddressData;
use Magento\CustomerGraphQl\Model\Customer\Address\GetCustomerAddress;
use Magento\CustomerGraphQl\Model\Customer\Address\UpdateCustomerAddress as UpdateCustomerAddressModel;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Embitel\Quote\Helper\Data;

/**
 * Customers address update, used for GraphQL request processing
 */
class UpdateCustomerAddress implements ResolverInterface
{
    /**
     * @var GetCustomerAddress
     */
    private $getCustomerAddress;

    /**
     * @var UpdateCustomerAddressModel
     */
    private $updateCustomerAddress;

    /**
     * @var ExtractCustomerAddressData
     */
    private $extractCustomerAddressData;

    /**
     * @param GetCustomerAddress $getCustomerAddress
     * @param UpdateCustomerAddressModel $updateCustomerAddress
     * @param ExtractCustomerAddressData $extractCustomerAddressData
     */
    public function __construct(
        GetCustomerAddress $getCustomerAddress,
        UpdateCustomerAddressModel $updateCustomerAddress,
        ExtractCustomerAddressData $extractCustomerAddressData,
        Data $helper
    ) {
        $this->getCustomerAddress = $getCustomerAddress;
        $this->updateCustomerAddress = $updateCustomerAddress;
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

        if (empty($args['id'])) {
            throw new GraphQlInputException(__('Address "id" value must be specified'));
        }

        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value must be specified'));
        }

        $address = $this->getCustomerAddress->execute((int)$args['id'], $context->getUserId());
        $this->updateCustomerAddress->execute($address, $args['input']);

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

        return $customerAddress;
    }
}
