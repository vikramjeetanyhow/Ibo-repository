<?php
namespace Embitel\Quote\Plugin\Magento\Quote\Model;

use Magento\Customer\Api\AddressRepositoryInterface;

class BillingAddressManagement
{

    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        AddressRepositoryInterface $addressRepositoryInterface
    ) {
        $this->logger = $logger;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
    }

    public function beforeAssign(
        \Magento\Quote\Model\BillingAddressManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\AddressInterface $address,
        $useForShipping = false
    ) {

        try {
            $id = $address->getData('customer_address_id');
            $addressRepository = $this->addressRepositoryInterface->getById($id);
            $addressData = $addressRepository->__toArray();
            if(isset($addressData) && isset($addressData['custom_attributes']['landmark']) && ($addressData['custom_attributes']['landmark']['value'] != '')) {
                $landmark = $addressData['custom_attributes']['landmark']['value'];
            } else {
                $landmark = '';
            }
            $address->setLandmark($landmark);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}