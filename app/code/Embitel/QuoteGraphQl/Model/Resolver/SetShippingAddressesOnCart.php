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
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Mutation resolver for setting shipping addresses for shopping cart
 */
class SetShippingAddressesOnCart implements ResolverInterface
{
    /**
     * @var GetCartForUser
     */
    private $getCartForUser;

    /**
     * @var SetShippingAddressesOnCartInterface
     */
    private $setShippingAddressesOnCart;

    /**
     * @var CheckCartCheckoutAllowance
     */
    private $checkCartCheckoutAllowance;

    /**
     * @var EventManager
     */
    private $_eventManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

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
        EventManager $eventManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->setShippingAddressesOnCart = $setShippingAddressesOnCart;
        $this->checkCartCheckoutAllowance = $checkCartCheckoutAllowance;
        $this->setBillingAddressOnCart = $setBillingAddressOnCart; 
        $this->customerFactory = $customerFactory; 
        $this->eavConfig = $eavConfig;
        $this->_eventManager = $eventManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['input']['cart_id'];

        if (empty($args['input']['shipping_addresses'])) {
            throw new GraphQlInputException(__('Required parameter "shipping_addresses" is missing'));
        }
        $shippingAddresses = $args['input']['shipping_addresses'];

        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);
        $this->checkCartCheckoutAllowance->execute($cart);
        $this->setShippingAddressesOnCart->execute($context, $cart, $shippingAddresses);
        // reload updated cart
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId); 
        if($cart->getShippingAddress()->getPostcode()) { 
            $postalCode = $cart->getShippingAddress()->getPostcode();
            $cart->setPostalCode($postalCode)->save();

            $isRegionalPricingEnable = $this->scopeConfig->getValue("regional_pricing/setting/active");
            if($isRegionalPricingEnable == 1) {
                $this->_eventManager->dispatch('item_price_by_postcode', ['quote' => $cart]);
            }
        }
        //Set default billing address for customers
        if($context->getUserId()) { 
             $customer = $this->customerFactory->create()->load($context->getUserId()); 
             $optionlabel = "";
             if($customer->getTaxvat()) { 
                //Set default billing address for verified GST customers
                if(null!== ($customer->getData('approval_status'))){ 
                    $approvalStatus = $customer->getData('approval_status');  
                    $attribute = $this->eavConfig->getAttribute('customer', "approval_status"); 
                    $optionlabel = $attribute->getSource()->getOptionText($approvalStatus); 
                } 
                if(isset($optionlabel) && strtolower($optionlabel) == "approved") { 
                    //Approved so set alway default billing in cart
                    $billingAddressId = $customer->getDefaultBilling(); 
                    if($billingAddressId > 0) { 
                        $billingAddress['customer_address_id'] = (int)$billingAddressId;
                        $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                    } else {  
                        //set billing same as shipping
                        if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                            $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                            $billingAddress['customer_address_id'] = (int)$billingId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        }
                    } 
                }else{
                    if(!$cart->getBillingAddress()->getFirstName()  && !$cart->getBillingAddress()->getCustomerAddressId()) { 
                        //If cart has no billing fetch default billing and set in cart
                        $billingAddressId = $customer->getDefaultBilling(); 
                        if($billingAddressId){ 
                            if($billingAddressId > 0) { 
                                $billingAddress['customer_address_id'] = (int)$billingAddressId;
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            } else {  
                                //set billing same as shipping
                                if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                    $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                    $billingAddress['customer_address_id'] = (int)$billingId; 
                                    $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                                }
                            } 
                        }else{ 
                            //set billing same as shipping
                            if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                $billingAddress['customer_address_id'] = (int)$billingId; 
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            }
                        }
                    }
                } 
             }else{ 
                //Set default billing address for customer if not set 
                if(!$cart->getBillingAddress()->getFirstName()  && !$cart->getBillingAddress()->getCustomerAddressId()) { //If cart has no billing fetch default billing and set in cart
                    $billingAddressId = $customer->getDefaultBilling(); 
                    if($billingAddressId){ 
                        if($billingAddressId > 0) { 
                            //Get default billing address and set in cart
                            $billingAddress['customer_address_id'] = (int)$billingAddressId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        } else {  
                            //set billing same as shipping
                            if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                                $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                                $billingAddress['customer_address_id'] = (int)$billingId; 
                                $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                            }
                        } 
                    }else{ 
                        //set billing same as shipping
                        if(isset($args['input']['shipping_addresses'][0]['customer_address_id'])) { 
                            $billingId = $args['input']['shipping_addresses'][0]['customer_address_id']; 
                            $billingAddress['customer_address_id'] = (int)$billingId; 
                            $this->setBillingAddressOnCart->execute($context, $cart, $billingAddress);
                        }
                    }
                }
             }
        }
        
        return [
            'cart' => [
                'model' => $cart,
            ],
        ];
    }
}
