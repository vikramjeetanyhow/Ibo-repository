<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Embitel\CatalogGraphQl\Model\Resolver\Customer\GetCustomerGroup;
use Magento\CatalogCustomerGraphQl\Model\Resolver\Product\Price\Tiers;
use Magento\CatalogCustomerGraphQl\Model\Resolver\Product\Price\TiersFactory;
use Embitel\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Embitel\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product\TierPriceFactory;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Tax\Model\Config as TaxConfig;
use Psr\Log\LoggerInterface;

/**
 * Resolver for price_tiers
 */
class EboPriceTiers implements ResolverInterface
{
    /**
     * @var TiersFactory
     */
    private $tiersFactory;

    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var GetCustomerGroup
     */
    private $getCustomerGroup;

    /**
     * @var int
     */
    private $customerGroupId;

    /**
     * @var Tiers
     */
    private $tiers;

    /**
     * @var Discount
     */
    private $discount;

    /**
     * @var PriceProviderPool
     */
    private $priceProviderPool;

    private $taxHelper;

    private $productObj;

    /**
     * @var array
     */
    private $formatAndFilterTierPrices = [];

    /**
     * @var array
     */
    private $tierPricesQty = [];

    /**
     * @var StoreManager
     */
    protected $storeManager;

    /**
     * @var TaxConfig
     */
    protected $taxConfig;

     /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ValueFactory $valueFactory
     * @param TiersFactory $tiersFactory
     * @param GetCustomerGroup $getCustomerGroup
     * @param Discount $discount
     * @param PriceProviderPool $priceProviderPool
     * @param StoreManager $storeManager
     * @param TaxConfig $taxConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        ValueFactory $valueFactory,
        TiersFactory $tiersFactory,
        GetCustomerGroup $getCustomerGroup,
        Discount $discount,
        PriceProviderPool $priceProviderPool,
        TaxHelper $taxHelper,
        TierPriceFactory $tierPriceFactory,
        StoreManager $storeManager,
        TaxConfig $taxConfig,
        LoggerInterface $logger,
        HttpContext $httpContext
    ) {
        $this->valueFactory = $valueFactory;
        $this->tiersFactory = $tiersFactory;
        $this->getCustomerGroup = $getCustomerGroup;
        $this->discount = $discount;
        $this->priceProviderPool = $priceProviderPool;
        $this->taxHelper = $taxHelper;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->_storeManager = $storeManager;
        $this->_taxConfig = $taxConfig;
        $this->logger = $logger;
        $this->httpContext = $httpContext;
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

        if (empty($this->tiers)) {
            $this->customerGroupId = $this->getCustomerGroup->execute($context->getUserId());

            /* If customer group id pass in pdp request body*/
            $iboCustomerGroupId = $this->httpContext->getValue('ibo_customer_group_id');
            if(!empty($iboCustomerGroupId)) {
                $this->customerGroupId = $iboCustomerGroupId;
            }
            $this->tiers = $this->tiersFactory->create(['customerGroupId' => $this->customerGroupId]);
        }

        $product = $value['model'];
        //$this->productObj = $value['model'];
        if ($product->hasData('can_show_price') && $product->getData('can_show_price') === false) {
            return [];
        }

        $productId = (int)$product->getId();
        $this->tiers->addProductFilter($productId);

        return $this->valueFactory->create(
            function () use ($productId, $context, $product) {
                $currencyCode = $context->getExtensionAttributes()->getStore()->getCurrentCurrencyCode();

                $productPrice = $this->tiers->getProductRegularPrice($productId) ?? 0.0;
                $tierPrices = $this->tiers->getProductTierPrices($productId) ?? [];
                return $this->formatAndFilterTierPrices($productPrice, $tierPrices, $currencyCode, $product);
            }
        );
    }

    private function getTaxPrice($product,$origPrice)
    {
        $price = $this->taxHelper->getTaxPrice($product, $origPrice, true);
        return $price;
    }

    /**
     * Format and filter tier prices for output
     *
     * @param float $productPrice
     * @param ProductTierPriceInterface[] $tierPrices
     * @param string $currencyCode
     * @return array
     */
    private function formatAndFilterTierPrices(
        float $productPrice,
        array $tierPrices,
        string $currencyCode,
        $product
    ): array {
        /* unsetting array key because it appends the previous product tier values */
        // unset($this->formatAndFilterTierPrices['price_with_tax']);
        // unset($this->formatAndFilterTierPrices['price_without_tax']);

        if(isset($tierPrices) && !empty($tierPrices)){
            $firstTier = $tierPrices[0];
            if ($firstTier->getQty() > 1)
            {
                $newTierPrice = $this->tierPriceFactory->create();
                $newTierPrice->setValue($productPrice);
                $newTierPrice->setQty(1);
                array_unshift($tierPrices, $newTierPrice);
            }
        }

        $lastTier = array_key_last($tierPrices);
        $tierCounter = 0;
        $this->formatAndFilterTierPrices = [];
        $this->tierPricesQty = [];
        foreach ($tierPrices as $key => $tierPrice) {

    		if ($key == $lastTier) {
    			$maxQty = null;
    		} else {
    			$maxQty = $tierPrices[$tierCounter+1]['qty'] - 1;
    		}

            $tierPrice->setValue($this->taxHelper->taxPriceCurrency($tierPrice->getValue()));
            $this->formatTierPrices($productPrice, $currencyCode, $tierPrice, $product,$maxQty);
            $productTaxPrice = $this->getTaxPrice($product,$productPrice);
            $this->formatTierPricesWithTax($productTaxPrice, $currencyCode, $tierPrice, $product,$maxQty);
            $this->filterTierPrices($tierPrices, $key, $tierPrice);
            $tierCounter ++;
        }

        return $this->formatAndFilterTierPrices;
    }

    /**
     * Format tier prices for output
     *
     * @param float $productPrice
     * @param string $currencyCode
     * @param ProductTierPriceInterface $tierPrice
     */
    private function formatTierPrices(float $productPrice, string $currencyCode, $tierPrice, $product,$maxQty = null)
    {
        $mrp = (float)$product->getMrp();
        if (isset($mrp) && $mrp > 0) {
            $discountPrice = $mrp;
        } else {
            $discountPrice = $productPrice;
        }
        $percentValue = $tierPrice->getExtensionAttributes()->getPercentageValue();
        if ($percentValue && is_numeric($percentValue)) {
            $discount = $this->discount->getDiscountByPercent($discountPrice, (float)$percentValue);
        } else {
            $discount = $this->discount->getDiscountByDifference($discountPrice, (float)$tierPrice->getValue());
        }

        $tierFinalPrice = $tierPrice->getValue();

        $storeId = $this->_storeManager->getStore()->getId();
        $priceIncludesTax = $this->_taxConfig->priceIncludesTax($storeId);

        if($priceIncludesTax == 1) {
            $taxRate = $product->getAttributeText('tax_class_id');

             if(!is_numeric($taxRate)) {
                $itemInfo = 'OfferId:'.$product->getSku().' ProductId: '.$product->getId().' TaxRate: '.$taxRate;
                $this->logger->critical('EboPriceTiers: This item have non numaric tax rate whose ' , ['exception' => $itemInfo]);
            } else {
                $tierFinalPrice = $tierPrice->getValue() * 100 / (100 + $taxRate);
            }
        }

        $this->formatAndFilterTierPrices['price_without_tax'][] = [
            "discount" => $discount,
            "quantity" => $tierPrice->getQty(),
            "max_quantity" => $maxQty,
            "final_price" => [
                "value" => (float)number_format((float)$tierFinalPrice, 2, '.', ''),
                "currency" => $currencyCode
            ],
            'unit_price' => $this->getUnitPrice($product, $tierFinalPrice)
        ];
    }

    /**
     * Format tier prices for output
     *
     * @param float $productPrice
     * @param string $currencyCode
     * @param ProductTierPriceInterface $tierPrice
     */
    private function formatTierPricesWithTax(float $productPrice, string $currencyCode, $tierPrice, $product, $maxQty = null)
    {
        $mrp = (float)$product->getMrp();
        $finalPrice = $this->getTaxPrice($product,$tierPrice->getValue());
        if (isset($mrp) && $mrp > 0) {
            $discountPrice = $mrp;
        } else {
            $discountPrice = $productPrice;
        }
        $percentValue = $tierPrice->getExtensionAttributes()->getPercentageValue();
        if ($percentValue && is_numeric($percentValue)) {
            $discount = $this->discount->getDiscountByPercent($discountPrice, (float)$finalPrice);
        } else {
            $discount = $this->discount->getDiscountByDifference($discountPrice, (float)$finalPrice);
        }

        $this->formatAndFilterTierPrices['price_with_tax'][] = [
            "discount" => $discount,
            "quantity" => $tierPrice->getQty(),
            "max_quantity" => $maxQty,
            "final_price" => [
                "value" => $finalPrice ,
                "currency" => $currencyCode
            ],
            'unit_price' => $this->getUnitPrice($product,$finalPrice)
        ];
    }

    private function getUnitPrice($product,$finalPrice)
    {
        $attributeCode = $product->getPerUnitPriceDivisor();

        if($product->getPerUnitPriceApplicable() == 1 || (!empty($product->getPerUnitPriceApplicable()) && strtoupper($product->getPerUnitPriceApplicable())== 'YES'))
        {
            if($product->getData($attributeCode)){
               $finalUnitPrice = ceil($this->taxHelper->taxPriceCurrency($finalPrice / $product->getData($attributeCode))).' / '.$product->getPerUnitPriceUnit();
               return $finalUnitPrice;
            }
            return null;
        }
        return null;
    }

    /**
     * Filter the lowest price for each quantity
     *
     * @param array $tierPrices
     * @param int $key
     * @param ProductTierPriceInterface $tierPriceItem
     */
    private function filterTierPrices(
        array $tierPrices,
        int $key,
        ProductTierPriceInterface $tierPriceItem
    ) {
        $qty = $tierPriceItem->getQty();
        if (isset($this->tierPricesQty[$qty])) {
            $priceQty = $this->tierPricesQty[$qty];
            if ((float)$tierPriceItem->getValue() < (float)$tierPrices[$priceQty]->getValue()) {
                unset($this->formatAndFilterTierPrices[$priceQty]);
                $this->tierPricesQty[$priceQty] = $key;
            } else {
                unset($this->formatAndFilterTierPrices[$key]);
            }
        } else {
            $this->tierPricesQty[$qty] = $key;
        }
    }
}
