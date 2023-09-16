<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\QuoteGraphQl\Model\Cart\SetBillingAddressOnCart as SetBillingAddressOnCartModel;
use Magento\QuoteGraphQl\Model\Cart\SetShippingAddressesOnCartInterface;
use Magento\QuoteGraphQl\Model\Cart\CheckCartCheckoutAllowance;
use Embitel\Quote\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteRepository;
use Magento\QuoteGraphQl\Model\Cart\AssignShippingAddressToCart;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
/**
 * Mutation resolver for setting shipping addresses for shopping cart
 */
class SetShippingChargeResolver implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var Embitel\Quote\Helper\Data
     */
    private $helper;

    /**
     * @var SetShippingAddressesOnCartInterface
     */
    private $setShippingAddressesOnCart;

    /**
     * @var CheckCartCheckoutAllowance
     */
    private $checkCartCheckoutAllowance;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var AssignShippingAddressToCart
     */
    private $assignShippingAddress;

    protected $resource;

    /**
     * @param GetCartForUser $getCartForUser
     * @param SetShippingAddressesOnCartInterface $setShippingAddressesOnCart
     * @param CheckCartCheckoutAllowance $checkCartCheckoutAllowance
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        SetShippingAddressesOnCartInterface $setShippingAddressesOnCart, 
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        SetBillingAddressOnCartModel $setBillingAddressOnCart,
        \Magento\Eav\Model\Config $eavConfig,
        CheckCartCheckoutAllowance $checkCartCheckoutAllowance,
        Data $helper,
        \Magento\Framework\App\ResourceConnection $resource,
        QuoteRepository $quoteRepository,
        AssignShippingAddressToCart $assignShippingAddress,
        TimezoneInterface $timezoneInterface
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->setShippingAddressesOnCart = $setShippingAddressesOnCart;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;
        $this->setBillingAddressOnCart = $setBillingAddressOnCart; 
        $this->customerFactory = $customerFactory; 
        $this->eavConfig = $eavConfig;
        $this->helper = $helper;
        $this->_resource = $resource;
        $this->quoteRepository = $quoteRepository;
        $this->assignShippingAddress = $assignShippingAddress;
        $this->timezoneInterface = $timezoneInterface;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['cart_id'];

        if (trim($args['shipping_amount']) == '') {
            throw new GraphQlInputException(__('Required parameter "shipping_amount" is missing'));
        }

        $status = true;
        $message = 'Shipping charge updated';

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $this->checkCartCheckoutAllowance->execute($cart);

        $cart->setPromiseShippingAmount(trim($args['shipping_amount']));
        $cart->save();

        // reload updated cart
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId); 
        $updatedTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $shippingAddress = $cart->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->setShippingMethod('flatrate_flatrate'); //shipping method 
        $cart->setShippingReceivedAt($updatedTime);
        $cart->setShippingUpdateAt($updatedTime);
        $cart->save();  
                 
        return [
            'status' => $status,
            'message' => $message
        ];
    }

}
