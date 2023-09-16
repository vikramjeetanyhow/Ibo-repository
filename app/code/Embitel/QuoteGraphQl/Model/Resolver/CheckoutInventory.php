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
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Embitel\Quote\Helper\Data;
//use Embitel\QuoteGraphQl\Model\Resolver\DataProvider\PromotionList;

class CheckoutInventory implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    private $helper;
    
    /**
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        Data $helper
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->helper = $helper;
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
        if ((!isset($args['cart_id']) || $args['cart_id'] == "") || (!isset($args['pincode']) || $args['pincode'] =="")) {
            throw new GraphQlInputException(__('cart_id and pincode should be specified'));
        }
        
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $maskedCartId = $args['cart_id'];
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $status = 0;
        
        if($cart->getId()){
            $response = $this->helper->processQuantityCheck($cart,$args['pincode']); 
            if($response == 'success') {
                $items = $cart->getAllItems();
                $status = 1; 
                foreach ($items as $item) { 
                    if($item->getEboInventoryFlag() == 0) { 
                        $status = 0;
                        break;
                    }                
                } 
            } else {
                $status = 0;
            }
        }else{
            throw new GraphQlInputException(__('Cart is not available.'));
        }
        if($status == 0){
            $pageData['status'] = 0;
            $pageData['message'] = "Items are not deliverables to ".$args['pincode'].". Please try changing the address";   
        }else{
            $pageData['status'] = 1;
            $pageData['message'] = "Items are deliverables to ".$args['pincode'].".";   
        }

        return $pageData;
    }

}
