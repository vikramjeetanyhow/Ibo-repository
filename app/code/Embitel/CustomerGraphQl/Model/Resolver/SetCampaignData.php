<?php

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;
use Embitel\Sms\Model\Customer\MobileCustomer;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Integration\Model\Oauth\Token;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeCollection;

class SetCampaignData implements ResolverInterface
{

    /**
     * @var \Embitel\CustomerGraphQl\Model\Customer\ValidateMobile
     */
    protected $validateMobile;

    /**
     * @var MobileCustomer
     */
    protected $mobileCustomer;

    /**
     *
     * @param ValidateMobile $validateMobile
     */
    public function __construct(
        ValidateMobile $validateMobile,
        MobileCustomer $mobileCustomer,
        groupRepository $groupRepository,
        Token $token,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        AttributeCollection $attributeCollection,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        //\Magento\CustomerBalance\Model\BalanceFactory $balanceFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Customer $customerModel
    ) {
        $this->validateMobile = $validateMobile;
        $this->mobileCustomer = $mobileCustomer;
        $this->groupRepository = $groupRepository;
        $this->token = $token;
        $this->addressRepository = $addressRepository;
        $this->attributeCollection = $attributeCollection;
        $this->customerRepository = $customerRepository;
        //$this->_balanceFactory = $balanceFactory;
        $this->_storeManager = $storeManager;
        $this->_customerModel = $customerModel;
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

        if (!isset($args['m2m_token'])) {
            throw new GraphQlInputException(__('M2M token value should be specified'));
        }

        $bearerToken = $args['m2m_token'];
        $accessData = $this->token->loadByToken($bearerToken);
        $customerData = [];

    if($accessData->getId() && ($accessData->getUserType() == 1)) {

        if(isset($args['customerId'])) {
            $customerModel = $this->_customerModel->load($args['customerId']);
            $customer = $customerModel->getDataModel();
        }

        if(($customer != '') && ($customer->getId())) {

            if(isset($args['campaign_id']) && (trim($args['campaign_id']) !='')) {
                $customer->setCustomAttribute('campaign_id',$args['campaign_id']);
            }
            if(isset($args['referral_id']) && (trim($args['referral_id']) !='')) {
                $customer->setCustomAttribute('referral_id',$args['referral_id']);
            }
            if(isset($args['personalised_coupon_code']) && (trim($args['personalised_coupon_code']) !='')) {
                $customer->setCustomAttribute('personalised_coupon_code',$args['personalised_coupon_code']);
            }

            $websiteId = $this->_storeManager->getStore($customer->getStoreId())->getWebsiteId();
            // $balanceModel = $this->_balanceFactory->create()->setCustomerId(
            //     $customer->getId()
            // )->setWebsiteId(
            //     $websiteId
            // )->loadByCustomer();

            // if($balanceModel->getAmount() < 1) {
            //     $balanceModel->setAmountDelta(1);
            //     $balanceModel->setAmount(1);
            //     $balanceModel->save();
            // }

            $customerModel->updateData($customer);
            $customerModel->save();

            $customerData['status'] = true;
            $customerData['message'] = 'Customer is successfully updated with given data';

        } else {
            $customerData['status'] = false;
            $customerData['message'] = 'The customer Id not found';
        }
    } else {
        $customerData['status'] = false;
        $customerData['message'] = 'The m2m token is not valid';
    }

        return $customerData;
    }

    private function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCustomerAttributeValue($customer, $attributeCode)
    {
        $customerStatus =$customer->getCustomAttribute($attributeCode);
        $optionValues = [];
        if (!empty($customerStatus)) {
            $this->attributeCollection->setIdFilter(explode(',', $customer->getCustomAttribute($attributeCode)->getValue()))
            ->setStoreFilter();
            $options = $this->attributeCollection->toOptionArray();
            if (!empty($options)) {
                array_walk($options, function ($value, $key) use (&$optionValues) {
                    $optionValues[] = $value['label'];
                });
            }
        }
        return implode(',', $optionValues);
    }

}
