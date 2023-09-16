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
use Embitel\CatalogGraphQl\Model\Resolver\DataProvider\PdfDataProvider;
/**
 * @inheritdoc
 */
class PdfDetailResolver implements ResolverInterface
{
    /**
     * @var PdfDataProvider
     */
    private $pdfDataProvider;

    /**
     *
     * @param PdfDataProvider $pdfDataProvider
     */
    public function __construct(
        PdfDataProvider $pdfDataProvider
    ) {
        $this->pdfDataProvider = $pdfDataProvider;
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
        $pdfDetail = [];
        $product = $value['model'];

         try {
            if ($product->getId() !== null) {
                $pdfDetail = $this->pdfDataProvider->getPdfDataByProductId($product->getId(), $product->getStoreId());
            }
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
        return $pdfDetail;
    }
}