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
use Embitel\CatalogGraphQl\Model\Resolver\DataProvider\MoreInfo as MoreInfoModel;

/**
 * @inheritdoc
 */
class MoreInfoResolver implements ResolverInterface
{
    /**
     * @var MoreInfoModel
     */
    private $moreInfoModel;

    /**
    * @var \Magento\Framework\Registry
    */
    protected $_registry;

    /**
     * @param MoreInfoModel $moreInfoModel
     */
    public function __construct(MoreInfoModel $moreInfoModel,
    \Magento\Framework\Registry $registry)
    {
        $this->moreInfoModel = $moreInfoModel;
        $this->_registry = $registry;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $excludeArgs = $this->_registry->registry('excludeAttr');
        $excludeAttr = [];
        if (isset($excludeArgs)) {
        $excludeAttr = $excludeArgs['code']['in'];
        }

        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /* @var $product ProductInterface */
        $product = $value['model'];
        $items = $this->moreInfoModel->getAdditionalData($product,$excludeAttr);
        return $items;
    }
}
