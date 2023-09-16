<?php
namespace Embitel\Quote\Plugin\Magento\Quote\Model;

use Magento\Customer\Api\AddressRepositoryInterface;

class ShippingAddressManagement
{
    protected $logger;

    protected $addressRepositoryInterface;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        AddressRepositoryInterface $addressRepositoryInterface
    ) {
        $this->logger = $logger;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
    }

    public function beforeAssign(
        \Magento\Quote\Model\ShippingAddressManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\AddressInterface $address
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