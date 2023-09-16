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
use Magento\Wishlist\Model\Wishlist\Config as WishlistConfig;
use Magento\Wishlist\Model\Wishlist;

/**
 * @inheritdoc
 */
class InWishlistResolver implements ResolverInterface
{
    /**
     * @var WishlistConfig
     */
    private $wishlistConfig;

    /**
     * @var wishlist
     */
    private $wishlist;

    /**
     * @param WishlistHelper $helper
     * @param WishlistConfig $wishlistConfig
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        Wishlist $wishlist,
        WishlistConfig $wishlistConfig
    ) {
        $this->wishlistConfig = $wishlistConfig;
        $this->wishlist = $wishlist;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!$this->wishlistConfig->isEnabled()) {
            throw new GraphQlInputException(__('The wishlist configuration is currently disabled.'));
        }

        $customerId = $context->getUserId();
        $in_wishlist = false;
        if (null === $customerId || 0 === $customerId) {
            return $in_wishlist;
        }

        /* @var $product ProductInterface */
        $product = $value['model'];

        /** @var WishlistCollection $collection */
        $collection = $this->wishlist->loadByCustomerId($customerId)
        ->getItemCollection()->addFieldToSelect('product_id');

        if ($collection->getSize() > 0) {
            $productIds = array_column($collection->getData(), 'product_id');
            $in_wishlist = in_array($product->getId(), $productIds)?true:false;
        }
       
        return $in_wishlist;
    }
}
