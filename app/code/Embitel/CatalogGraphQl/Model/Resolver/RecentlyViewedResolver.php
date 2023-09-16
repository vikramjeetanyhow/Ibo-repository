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
use Embitel\CatalogGraphQl\Model\RecentlyViewed;

/**
 * @inheritdoc
 */
class RecentlyViewedResolver implements ResolverInterface
{
    /**
     * @var RecentlyViewed
     */
    private $recentlyViewed;

    /**
     * @param RecentlyViewed $recentlyViewed
     */
    public function __construct(RecentlyViewed $recentlyViewed)
    {
        $this->recentlyViewed = $recentlyViewed;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['id'])) {
            throw new GraphQlInputException(__('Product Id cannot be null'));
        }
        $customerId = null;
       
        if ($context->getExtensionAttributes()->getIsCustomer()) {
            $customerId = $context->getUserId();
        }

        try {
            $recentAdded = $this->recentlyViewed->addItem($customerId,$args['id']);
        } catch (LocalizedException $e) {
           throw new LocalizedException(__($e->getMessage()));  
        }
        return $recentAdded;
    }
}
