<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Embitel\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Store\Api\Data\StoreInterface;
use Embitel\Catalog\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Psr\Log\LoggerInterface;

/**
 * Format product's pricing information for price_range field
 */
class EboPriceRange implements ResolverInterface
{
    /**
     * @var Discount
     */
    private $discount;

    /**
     * @var PriceProviderPool
     */
    private $priceProviderPool;   
   
    /**
     * @var TaxConfig
     */
    protected $taxConfig;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     * @param TaxConfig $taxConfig
     * @param StoreManager $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(PriceProviderPool $priceProviderPool, Discount $discount, TaxHelper $taxHelper, TaxConfig $taxConfig, StoreManager $storeManager, LoggerInterface $logger)
    {
        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
        $this->taxHelper = $taxHelper;
        $this->_taxConfig = $taxConfig;
        $this->_storeManager = $storeManager;
        $this->logger = $logger;
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        /** @var Product $product */
        $product = $value['model'];
        $product->unsetData('minimal_price');

        $requestedFields = $info->getFieldSelection(10);
        $returnArray = [];

        if (isset($requestedFields['price_without_tax']['minimum_price'])) {
            $returnArray['price_without_tax']['minimum_price'] =  $this->canShowPrice($product) ?
                $this->getMinimumProductPrice($product, $store) : $this->formatEmptyResult();
        }
        if (isset($requestedFields['price_without_tax']['maximum_price'])) {
            $returnArray['price_without_tax']['maximum_price'] =  $this->canShowPrice($product) ?
                $this->getMaximumProductPrice($product, $store) : $this->formatEmptyResult();
        }
        if (isset($requestedFields['price_with_tax']['minimum_price'])) {
            $returnArray['price_with_tax']['minimum_price'] =  $this->canShowPrice($product) ?
                $this->getMinimumProductPriceWithTax($product, $store) : $this->formatEmptyResult();
        }
        if (isset($requestedFields['price_with_tax']['maximum_price'])) {
            $returnArray['price_with_tax']['maximum_price'] =  $this->canShowPrice($product) ?
                $this->getMaximumProductPriceWithTax($product, $store) : $this->formatEmptyResult();
        }
        return $returnArray;
    }

    private function getUnitPrice($product,$finalPrice)
    {
        $attributeCode = $product->getPerUnitPriceDivisor();

        if($product->getPerUnitPriceApplicable() == 1 || (!empty($product->getPerUnitPriceApplicable()) && strtoupper($product->getPerUnitPriceApplicable())== 'YES'))
        {
            if($product->getData($attributeCode)){
               return ceil($this->taxHelper->taxPriceCurrency($finalPrice / $product->getData($attributeCode))).' / '.$product->getPerUnitPriceUnit();
            }
            return null;
        }

        return null;
    }

    /**
     * Get formatted minimum product price
     *
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMinimumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $priceIncludesTax = $this->_taxConfig->priceIncludesTax($storeId);

        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMinimalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMinimalFinalPrice($product)->getValue();

        if($priceIncludesTax == 1) { 
           $finalPrice =  $this->getPriceWithoutTax($product, $finalPrice, $product->getTaxClassId());        
        }

        $unitPrice = $this->getUnitPrice($product,$finalPrice);
        $minPriceArray = $this->formatPrice((float) $regularPrice, (float) $finalPrice, $store, $unitPrice);
        $minPriceArray['model'] = $product;
        return $minPriceArray;
    }

    private function getMinimumProductPriceWithTax(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMinimalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMinimalFinalPrice($product)->getValue();
        $regularPriceWithTax = $this->getTaxPrice($product,$regularPrice);
        $finalPricWithTax  = $this->getTaxPrice($product,$finalPrice);
        $unitPrice = $this->getUnitPrice($product,$finalPricWithTax);
        $minPriceArraywithTax = $this->formatPrice((float) $regularPriceWithTax, (float) $finalPricWithTax, $store, $unitPrice);
        $minPriceArraywithTax['model'] = $product;
        return $minPriceArraywithTax;
    }

    /**
     * Get formatted maximum product price
     *
     * @param SaleableInterface $product
     * @param StoreInterface $store
     * @return array
     */
    private function getMaximumProductPrice(SaleableInterface $product, StoreInterface $store): array
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $priceIncludesTax = $this->_taxConfig->priceIncludesTax($storeId);

        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMaximalFinalPrice($product)->getValue();
        if($priceIncludesTax == 1) { 
           $finalPrice =  $this->getPriceWithoutTax($product, $finalPrice, $product->getTaxClassId());        
        }
        $unitPrice = $this->getUnitPrice($product,$finalPrice);
        $maxPriceArray = $this->formatPrice((float) $regularPrice, (float) $finalPrice, $store, $unitPrice);
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    private function getMaximumProductPriceWithTax(SaleableInterface $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());
        $regularPrice = $priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMaximalFinalPrice($product)->getValue();
        $regularPriceWithTax = $this->getTaxprice($product,$regularPrice);
        $finalPricWithTax  = $this->getTaxPrice($product,$finalPrice);
        $unitPrice = $this->getUnitPrice($product,$finalPricWithTax);
        $maxPriceArray = $this->formatPrice((float) $regularPriceWithTax, (float) $finalPricWithTax, $store, $unitPrice);
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    private function getTaxPrice($product,$origPrice)
    {
        $price = $this->taxHelper->getTaxPrice($product, $origPrice, true);
        return $price;
    }

    /**
     * Format price for GraphQl output
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @param StoreInterface $store
     * @return array
     */
    private function formatPrice(float $regularPrice, float $finalPrice, StoreInterface $store, $unitPrice): array
    {
        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $store->getCurrentCurrencyCode()
            ],
            'discount' => $this->discount->getDiscountByDifference($regularPrice, $finalPrice),
            'unit_price' => $unitPrice
        ];
    }

    /**
     * Check if the product is allowed to show price
     *
     * @param ProductInterface $product
     * @return bool
     */
    private function canShowPrice($product): bool
    {
        if ($product->hasData('can_show_price') && $product->getData('can_show_price') === false) {
            return false;
        }

        return true;
    }

    /**
     * Format empty result
     *
     * @return array
     */
    private function formatEmptyResult(): array
    {
        return [
            'regular_price' => [
                'value' => null,
                'currency' => null
            ],
            'final_price' => [
                'value' => null,
                'currency' => null
            ],
            'discount' => null,
            'unit_price' => null
        ];
    }

    private function getPriceWithoutTax($product, $finalPrice, $taxClassId) {

        $taxRate = $product->getAttributeText('tax_class_id');

        if(!is_numeric($taxRate)) {
            $itemInfo = 'OfferId:'.$product->getSku().' ProductId: '.$product->getId().' TaxRate: '.$taxRate;
            $this->logger->critical('EboPriceRange: This item have non numaric tax rate whose ' , ['exception' => $itemInfo]);
            return $finalPrice;
        }
        $finalPrice = $finalPrice * 100 / (100 + $taxRate);
        return (float)number_format((float)$finalPrice, 2, '.', '');
    }
}
