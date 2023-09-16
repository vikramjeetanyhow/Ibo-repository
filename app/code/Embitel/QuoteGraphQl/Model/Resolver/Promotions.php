<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Embitel\QuoteGraphQl\Model\Resolver\DataProvider\PromotionList;

/**
 * CMS page field resolver, used for GraphQL request processing
 */
class Promotions implements ResolverInterface
{
   /**
     * @var GetCartForUser
     */
    private $promotionListProvider;

    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        PromotionList $promotionListProvider
    ) {
        $this->promotionListProvider = $promotionListProvider;
        
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        $quote = $value['model'];
        $currentUserId = $context->getUserId();
        $eligibleData = $this->promotionListProvider->getPromoList($quote,$currentUserId);
        return $eligibleData;
    }

}
