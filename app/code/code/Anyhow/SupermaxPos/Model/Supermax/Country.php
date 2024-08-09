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

use Magento\Framework\DataObject;

class Country extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\CountryInterface
{    
    public function __construct( 
        \Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformationAcquirer,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->countryInformationAcquirer = $countryInformationAcquirer;
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function getAllCountries()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                // check connection update data.
                $connectionId = $this->supermaxSession->getPosConnectionId();
                $code = 'country';
                $conncetionUpdateData = $this->helper->checkConnectionUpdate($connectionId, $code);
                $countriesData = array();
                
                // if(!empty($conncetionUpdateData) && is_null($conncetionUpdateData[0]['update'])){
                    $countries = $this->countryInformationAcquirer->getCountriesInfo();

                    if(!empty($countries)) {
                        foreach ($countries as $country) {
                            $regions = array();

                            if ($availableRegions = $country->getAvailableRegions()) {
                                foreach ($availableRegions as $region) {
                                    $regions[] = array(
                                        'region_id'   => (int)$region->getId(),
                                        'region_code' => html_entity_decode($region->getCode()),
                                        'region_name' => html_entity_decode($region->getName())
                                    );
                                }
                            }
                            
                            $countriesData[] = array(
                                'country_code'   => html_entity_decode($country->getTwoLetterAbbreviation()),
                                'country_name'   => html_entity_decode($country->getFullNameLocale()),
                                'country_regions' => $regions
                            );

                        }
                    }
                // }
                $result = array('countries' => $countriesData);
            } else {
                $error = true;
            }
        }catch (\Exception $e) {
            $error = true;
        }
        $data = array( 'error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }
}