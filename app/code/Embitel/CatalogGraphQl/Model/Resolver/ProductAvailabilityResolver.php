<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Api\Data\TierPriceInterface;
use Magento\Catalog\Api\TierPriceStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class ProductAvailabilityResolver implements ResolverInterface
{

    /**
     * @var TierPriceStorageInterface
     */
    private $tierPrice;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TierPriceStorageInterface $tierPrice,
        LoggerInterface $logger
    ) {
        $this->tierPrice = $tierPrice;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /* @var $product ProductInterface */
        $product = $value['model'];
        $sku[] = $product->getSku();
        $basePrice = $product->getPrice();
        $mrp = $product->getMrp();
        if($mrp == '') {
            $mrp = $product->getPrice();
        }
        if($mrp < $basePrice) { 
            return 0;
        }
        $productAvailabilityStatus = $this->getTierPrice($sku, $basePrice, $mrp);
        return $productAvailabilityStatus;
    }

    /**
     * tier price result
     *
     * @param array $sku
     * @return TierPriceInterface[]
     */
    public function getTierPrice(array $sku, $basePrice, $mrp)
    {
        $result = [];
        $availabilityStatus = 1;
        try {
            $result = $this->tierPrice->get($sku);
            if (count($result)) {
                foreach ($result as $item) {
                    $tierData = $item->getData();
                    $tierPrice = $tierData['price'];
                    if($mrp < $tierPrice) {
                        return 0;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return $availabilityStatus;
    }
}
