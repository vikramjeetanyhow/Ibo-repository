<?php

namespace Ibo\RegionalPricing\Plugin;

use Magento\Catalog\Model\Product\Price\TierPriceFactory as CoreTierPriceFactory;
use Magento\Catalog\Api\Data\TierPriceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class TierPriceFactoryPlugin
{
    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    )
    {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Add price qty to unique fields
     *
     * @param array $objectArray
     * @return array
     */
    public function afterCreateSkeleton(CoreTierPriceFactory $tierPriceFactory, $result,TierPriceInterface $price, $id)
    {
        $defaultZone = $this->scopeConfig
                            ->getValue("regional_pricing/setting/default_zone",
                             \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                             $this->storeManager->getStore()->getStoreId()
                            );
        $customerZone = !empty($price->getExtensionAttributes()->getCustomerZone()) ? $price->getExtensionAttributes()->getCustomerZone() : $defaultZone;

        $definedPrice = !empty($price->getExtensionAttributes()->getIsDefinedPrice()) ? $price->getExtensionAttributes()->getIsDefinedPrice() : 'DEFINED';

        $result['customer_zone'] = $customerZone;
        $result['is_defined_price'] = $definedPrice;
        return $result;
    }

    /**
     * Create populated tier price DTO.
     *
     * @param array $rawPrice
     * @param string $sku
     * @return \Magento\Catalog\Api\Data\TierPriceInterface
     */
    public function afterCreate(CoreTierPriceFactory $tierPriceFactory, $result, array $rawPrice, $sku)
    {
        if(isset($rawPrice['customer_zone'])) {
            $customerZone = $rawPrice['customer_zone'];
            $result->setCustomerZone($customerZone);
        }
        if(isset($rawPrice['is_defined_price'])) {
            $definedPrice = $rawPrice['is_defined_price'];
            $result->setIsDefinedPrice($definedPrice);
        }
        return $result;
    }

}

