<?php

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Integration\Model\Oauth\Token;
use Magento\Quote\Api\CartRepositoryInterface;

class GetCartByQuoteId implements ResolverInterface
{

    public function __construct(
        Token $token,
        CartRepositoryInterface $cartRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) { 
        $this->token = $token;  
        $this->cartRepository = $cartRepository;
        $this->quote = $quoteFactory;
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
        /* check if quote id is passed in graphql and validate the same */
        if (!isset($args['quoteId'])) {
            throw new GraphQlInputException(__('quoteId value should be specified'));
        }

        if (!isset($args['m2m_token'])) {
            throw new GraphQlInputException(__('M2M token value should be specified'));
        }

        $bearerToken = $args['m2m_token'];
        $accessData = $this->token->loadByToken($bearerToken);
        $cartId = $args['quoteId'];
              
        $cartData = [];

        if($accessData->getId() && ($accessData->getUserType() == 1)) { 
            
            $quoteData = $this->quote->create()->load($cartId);
            $quoteData = $quoteData->getData();
            if($quoteData) {
                $cartData['entity_id'] = $quoteData['entity_id'];
                $cartData['items_count'] = $quoteData['items_count'];
                $cartData['items_qty'] = $quoteData['items_qty'];
                $cartData['base_currency_code'] = $quoteData['base_currency_code'];
                $cartData['grand_total'] = $quoteData['grand_total'];
                $cartData['base_grand_total'] = $quoteData['base_grand_total'];
                $cartData['reserved_order_id'] = $quoteData['reserved_order_id'];
                $cartData['subtotal'] = $quoteData['subtotal'];
                $cartData['base_subtotal'] = $quoteData['base_subtotal'];
                $cartData['subtotal_with_discount'] = $quoteData['subtotal_with_discount'];
                $cartData['base_subtotal_with_discount'] = $quoteData['base_subtotal_with_discount'];
            } else {
                throw new GraphQlInputException(
                    __('Could not find a cart with ID "%quote_id"', ['quote_id' => $cartId])
                );
            }

        } else {
            throw new GraphQlInputException(
                __('Token is not valid')
            );
        }

        return $cartData;

    }
    
}