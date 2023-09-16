<?php

namespace Ibo\RegionalPricing\Model\Config\Source;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Region implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
    * @var \Magento\Framework\App\Config\ScopeConfigInterface
    */
    protected $scopeConfig;

    const IBO_REGIONS_LIST = 'regional_pricing/setting/zones';

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serializer)
    {
      $this->scopeConfig = $scopeConfig;
      $this->serializer = $serializer;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $regionList = $this->scopeConfig
                           ->getValue(self::IBO_REGIONS_LIST, $storeScope);
        $regions = [];
        if(!isset($regionList)) {
           return $regions;
        }
        if (!is_array($regionList)) {
            $regionList = $this->serializer->unserialize($regionList);
        }

        foreach ($regionList as $key => $region) {
            $regions[] = [
                            'value' => strtolower($region['ibo_zone']),
                            'label' => $region['ibo_zone']
                        ];
        }
        return $regions;
    }
}
