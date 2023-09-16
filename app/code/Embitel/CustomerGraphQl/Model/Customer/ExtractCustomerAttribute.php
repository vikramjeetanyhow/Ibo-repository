<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Customer;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Validates gender value
 */
class ExtractCustomerAttribute
{
    CONST COUNTRY_REGION = 'directory_country_region';
    /**
     * @var CustomerMetadataInterface
     */
    private $customerMetadata;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * ValidateGender constructor.
     *
     * @param CustomerMetadataInterface $customerMetadata
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        CustomerMetadataInterface $customerMetadata,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->customerMetadata = $customerMetadata;
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function get(array $attribute)
    {
        $attributeValue = [];
        if (!empty($attribute['label']) && !empty($attribute['key'])) {
            /** @var AttributeMetadata $genderData */
            $options = $this->customerMetadata->getAttributeMetadata($attribute['key'])->getOptions();
            $customerTypeArray = explode(',',$attribute['label']);
            foreach ($options as $optionData) {
                if (in_array($optionData->getLabel(), $customerTypeArray)) {
                    $attributeValue[] = $optionData->getValue();
                }
            }
        }
        return $attributeValue;
    }

    /**
     * @param string $region
     * @return string[]
     */
    public function getRegionCode(string $region): array
    {
        $regionCode = $this->collectionFactory->create()
            ->addRegionNameFilter($region)
            ->getFirstItem()
            ->toArray();
        return $regionCode;
    }

    /** 
     * Get All Regions of Country
    */
    public function getAllRegionsOfCountry($countryCode) 
    {
        $connection = $this->resourceConnection->getConnection();
        $allRegion = [];
        if(!empty($countryCode)){
            $themeTable = $connection->getTableName(self::COUNTRY_REGION);
            $query = "SELECT * FROM " . $themeTable . " WHERE `country_id` = '" . $countryCode . "'";
            $allRegion = $connection->fetchAll($query);
        }
        return $allRegion;
    }

    /** 
     * Get All Regions of Country
    */
    public function getRegionInfo($regionName) 
    {
        $regionName = strtolower($regionName);
        $countryCode = 'IN';
        $currerntRegion = [];
        $regionCollection = $this->getAllRegionsOfCountry($countryCode);
        if(!empty($regionCollection)){
            array_walk($regionCollection, function($val, $key) use(&$currerntRegion, $regionName){
                $defaultName = !empty($val['default_name']) ? strtolower($val['default_name']) : '';
                if($defaultName == $regionName) {
                    $currerntRegion = $val;
                }
            });
        }
        return $currerntRegion;
    }
}
