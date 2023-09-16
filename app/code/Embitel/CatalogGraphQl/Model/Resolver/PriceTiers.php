<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/** MDVA-37288_v2.patch applied */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CatalogCustomerGraphQl\Model\Resolver\PriceTiers as CorePriceTiers;

/**
 * Resolver for price_tiers
 */
class PriceTiers extends CorePriceTiers
{

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
            $this->tiers = $this->tiersFactory->create(['customerGroupId' => $this->customerGroupId]);
        }

        $product = $value['model'];

        if ($product->hasData('can_show_price') && $product->getData('can_show_price') === false) {
            return [];
        }

        $productId = (int)$product->getId();
        $this->tiers->addProductFilter($productId);

        return $this->valueFactory->create(
            function () use ($productId, $context) {
                $currencyCode = $context->getExtensionAttributes()->getStore()->getCurrentCurrencyCode();

                $productPrice = $this->tiers->getProductRegularPrice($productId) ?? 0.0;
                $tierPrices = $this->tiers->getProductTierPrices($productId) ?? [];
                return $this->formatAndFilterTierPrices($productPrice, $tierPrices, $currencyCode);
            }
        );
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
        string $currencyCode
    ): array {

        foreach ($tierPrices as $key => $tierPrice) {
            $tierPrice->setValue($this->priceCurrency->convertAndRound($tierPrice->getValue()));
            $this->formatTierPrices($productPrice, $currencyCode, $tierPrice);
            $this->filterTierPrices($tierPrices, $key, $tierPrice);
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
    private function formatTierPrices(float $productPrice, string $currencyCode, $tierPrice)
    {
        $percentValue = $tierPrice->getExtensionAttributes()->getPercentageValue();
        if ($percentValue && is_numeric($percentValue)) {
            $discount = $this->discount->getDiscountByPercent($productPrice, (float)$percentValue);
        } else {
            $discount = $this->discount->getDiscountByDifference($productPrice, (float)$tierPrice->getValue());
        }

        $this->formatAndFilterTierPrices[] = [
            "discount" => $discount,
            "quantity" => $tierPrice->getQty(),
            "final_price" => [
                "value" => $tierPrice->getValue(),
                "currency" => $currencyCode
            ]
        ];
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
