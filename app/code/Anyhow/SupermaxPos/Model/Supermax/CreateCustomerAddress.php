<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class CreateCustomerAddress implements \Anyhow\SupermaxPos\Api\Supermax\CreateCustomerAddressInterface
{
    public function __construct(
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->addressRepository = $addressRepository;
        $this->addressDataFactory = $addressDataFactory;
        $this->helper = $helper;
    }

     /**
     * GET API
     * @api
     * 
     * @return string
     */
 
    public function createCustomerAddress()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();

                if(!empty($params)){
                    $customerId = trim($params['customer_id'] ?? null);
                    $firstname = trim($params['firstname'] ?? null);
                    $lastname = trim($params['lastname'] ?? null);
                    $street = trim($params['street'] ?? "");
                    $street2 = trim($params['street2'] ?? "");
                    $street3 = !empty($params['street3']) ? trim($params['street3'] ?? "") : "";
                    $landmark = trim($params['landmark'] ?? "");
                    $city = trim($params['city'] ?? null);
                    $regionId = trim($params['region_id'] ?? null);
                    $countryId = trim($params['country_id'] ?? null);
                    $postCode = trim($params['postcode'] ?? null);
                    $telephone = trim($params['telephone'] ?? null);
                    $streetAddress = [$street, $street2, $street3];

                    if(!empty($customerId)){

                        $address = $this->addressDataFactory->create();
                        $address->setFirstname($firstname)
                            ->setLastname($lastname)
                            ->setCountryId($countryId)
                            ->setRegionId($regionId)
                            ->setCity($city)
                            ->setPostcode($postCode)
                            ->setCustomerId($customerId)
                            ->setStreet($streetAddress)
                            ->setTelephone($telephone);
                        // if(isset($params['setPaymentAddress']) && $params['setPaymentAddress']) {
                        //     $address->setIsDefaultBilling('1');
                        // }
                        // if(isset($params['setShippingAddress']) && $params['setShippingAddress']) {
                        //     $address->setIsDefaultShipping('1');
                        // }

                        $address->setCustomAttribute('landmark', $landmark);
                    
                        $address = $this->addressRepository->save($address);
                        $result = array(
                            'address_id' => (int)$address->getId(),
                            'customer_id' => (int)$address->getCustomerId(),
                            'firstname' => $address->getFirstname(),
                            'lastname' => $address->getLastname(),
                            'country_id' => $address->getCountryId(),
                            'region_id' => (int)$address->getRegionId(),
                            'city' => $address->getCity(),
                            'postcode' => $address->getPostcode(),
                            'landmark' => $address->getCustomAttribute('landmark'),
                            'street' => implode(",", $address->getStreet()),
                            'phone' => $address->getTelephone()
                        );
                    }
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}