<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento CXL POS
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/
 
namespace Anyhow\CxlPos\Model;
 
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\RegionInformationInterface;
use Magento\Directory\Model\RegionFactory;
 
class CountryStateRepository implements \Anyhow\CxlPos\Api\CountryStateRepositoryInterface
{
    /**
     * @var RegionFactory
     */
    protected $regionFactory;
    protected $helper;
 
    /**
     * @var CountryInformationAcquirerInterface
     */
    protected $countryInformationAcquirer;
 
    public function __construct(
        RegionFactory $regionFactory,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        \Anyhow\CxlPos\Helper\Data $helper
    ) {
        $this->regionFactory = $regionFactory;
        $this->countryInformationAcquirer = $countryInformationAcquirer;
        $this->helper = $helper;
 
    }
 
    public function getCountriesAndStates()
    {
        $json = array(
            "error" => false
        );
        try {
            if ($this->helper->getConfig('anyhowcxlpos/general/enable')) {
            $countries = $this->countryInformationAcquirer->getCountriesInfo();
            $result = [];
   
            foreach ($countries as $country) {
                $regions = [];
                $regionCollection = $this->regionFactory->create()->getCollection()->addCountryFilter($country->getId());
                foreach ($regionCollection as $region) {
                    /** @var RegionInformationInterface $regionInformation */
                    $regionInformation = $this->regionFactory->create()->load($region->getId());
                    $regions[] = [
                        'id' => $regionInformation->getId(),
                        'code' => $regionInformation->getCode(),
                        'title' => $regionInformation->getName(),
                    ];
                }
   
                $result[] = [
                    'code' => $country->getId(),
                    'title' => $country->getFullNameLocale(),
                    'regions' => $regions,
                ];
            }
            $json['result'] = $result;
     } else {
        $json['error'] = true;
    }
    } catch (\Exception $e) {
        $json['error'] = true;
    }  
   
    if($json['error']) {
        $json['message'] = "Something went wrong";
    }
 
    echo (json_encode($json));
    exit;
    }
}