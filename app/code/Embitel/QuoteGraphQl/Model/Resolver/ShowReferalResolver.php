<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
/**
 * @inheritdoc
 */
class ShowReferalResolver implements ResolverInterface
{
    private $group;

    public function __construct(
        \Magento\Customer\Model\Group $group, 
        ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Customer $customer
    ) {
        $this->customerModel = $customer;
        $this->group = $group; 
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $returnVal = false;
        $currentUserId = $context->getUserId(); 
        $storeValue = $this->_scopeConfig->getValue("checkout/cart/show_referal_section_for");
        if($currentUserId){
            $customer = $this->customerModel->load($currentUserId);
            $customerGroupId = $customer->getGroupId();
        }else{
            $cusGrpCode = 'NOT LOGGED IN';
            $groupObj = $this->group;
            $existingGroup = $groupObj->load($cusGrpCode, 'customer_group_code');
            $customerGroupId = $existingGroup->getCustomerGroupId();
        } 
        $storeValArr = explode(",",$storeValue);
        if(in_array($customerGroupId,$storeValArr)) { 
            $returnVal = true;
        }

        return $returnVal;
    }
}