<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Embitel\CatalogGraphQl\Model\Resolver\DataProvider\Promises as PromisesModel;


class PromisesResolver implements ResolverInterface
{

    /**
     * @var PromisesModel
     */
    private $promisesModel;

    /**
     * @param PromisesModel $promisesModel
     */
    public function __construct(PromisesModel $promisesModel)
    {
        $this->promisesModel = $promisesModel;
    }
    
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /* @var $product ProductInterface */
        $product = $value['model'];
        $promisesData = $this->promisesModel->getPromisesData($product);

        return $promisesData;
    }
}
