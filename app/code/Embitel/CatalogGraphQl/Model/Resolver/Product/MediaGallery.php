<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\Product;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * @inheritdoc
 *
 * Format a product's media gallery information to conform to GraphQL schema representation
 */
class MediaGallery implements ResolverInterface
{
    /**
     * @inheritdoc
     *
     * Format product's media gallery entry data to conform to GraphQL schema
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return array
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

        /** @var ProductInterface $product */
        $product = $value['model'];

        $mediaGalleryEntries = [];
        $label = '';
        $i = 1;
        foreach ($product->getMediaGalleryEntries() ?? [] as $key => $entry) {
            $key = ($entry->getData('position')!='') ? $entry->getData('position') : $key;
            $label = ($entry->getData('label')!='') ? $entry->getData('label') : $product->getName();
            $mediaGalleryEntries[$key] = $entry->getData();
            $mediaGalleryEntries[$key]['label'] = $label.$i;
            $mediaGalleryEntries[$key]['model'] = $product;
            if ($entry->getExtensionAttributes() && $entry->getExtensionAttributes()->getVideoContent()) {
                $mediaGalleryEntries[$key]['video_content']
                    = $entry->getExtensionAttributes()->getVideoContent()->getData();
            }
            $i ++;
        }
        ksort($mediaGalleryEntries);
        return $mediaGalleryEntries;
    }
}
