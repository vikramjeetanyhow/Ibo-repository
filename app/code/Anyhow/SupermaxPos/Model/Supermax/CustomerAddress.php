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

class CustomerAddress implements \Anyhow\SupermaxPos\Api\Supermax\CustomerAddressInterface
{
    public function __construct(
        \Magento\Customer\Model\Customer $customer,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Directory\Model\Country $country,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->customer = $customer;
        $this->country = $country;
        $this->helper = $helper;
        $this->addressRepository = $addressRepository;
    }

    /**
    * GET API
    * @api
    * @param int $customerId
    * @return string
    */
    public function getCustomerAddress($customerId)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $addresses = array();
                if(!empty($customerId)) {
                    $customerObj = $this->customer->load($customerId);
                    
                    if(!empty($customerObj)){
                        $addressCollection = $customerObj->getAddresses(); 

                        if(!empty($addressCollection)) {
                            foreach($addressCollection as $address) {
                                $customerAddress[] = $address->toarray();
                            }

                            foreach( $customerAddress as $customerAddres ) {
                                $addresses[] = array(
                                    'address_id' => (int)$customerAddres['entity_id'],
                                    'firstname' => html_entity_decode($customerAddres['firstname']),
                                    'lastname' => html_entity_decode($customerAddres['lastname']),
                                    'company' => html_entity_decode($customerAddres['company']),
                                    'street' => html_entity_decode($customerAddres['street']),
                                    'city' => html_entity_decode($customerAddres['city']),
                                    'region_id' => (int)$customerAddres['region_id'],
                                    'region_name' => html_entity_decode($customerAddres['region']),
                                    'country_id' => html_entity_decode($customerAddres['country_id']),
                                    'country_name' => html_entity_decode($this->country->load($customerAddres['country_id'])->getName()),
                                    'postcode' => html_entity_decode($customerAddres['postcode']),
                                    'landmark' => $this->getCustomerLandmark($customerAddres['entity_id']),
                                    'telephone' => html_entity_decode($customerAddres['telephone'])
                                );
                            }
                        }
                        $result = array( 'addresses' => $addresses );
                    } 
                } else {
                    $error = true;
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

    private function getCustomerLandmark($addressId) {
        $landmark = "";
        $address = $this->addressRepository->getById($addressId);
        if(!empty($address->getCustomAttribute('landmark'))) {
            $landmark = $address->getCustomAttribute('landmark')->getValue();
        }
    
        return $landmark;
    }
}