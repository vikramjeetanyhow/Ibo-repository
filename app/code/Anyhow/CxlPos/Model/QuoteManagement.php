<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento CXL Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

namespace Anyhow\CxlPos\Model;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;


class QuoteManagement implements \Anyhow\CxlPos\Api\CartManagementInterface
{


    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Api\GuestCartManagementInterface $guestCart,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository = null
    ) {
        $this->storeManager = $storeManager;
        $this->quoteRepository = $quoteRepository;
        $this->guestCart = $guestCart;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->customerRepository = $customerRepository;
        $this->quoteFactory = $quoteFactory;
        $this->addressRepository = $addressRepository ?: ObjectManager::getInstance()
            ->get(\Magento\Customer\Api\AddressRepositoryInterface::class);

    }

    /**
     * @inheritdoc
     */
    public function createEmptyCartForCustomer($customerId)
    {
        $result = [];

        try {

            $storeId = $this->storeManager->getStore()->getStoreId();

            if(!empty($customerId)){
                $quote = $this->createCustomerCarts($customerId, $storeId);
                $this->_prepareCustomerQuote($quote);
                $this->quoteRepository->save($quote);
                $result["quote_id"] = (int)$quote->getId();
            }else{
                $maskedHashId = $this->guestCart->createEmptyCart();
                $result["quote_id"] = $this->maskedQuoteIdToQuoteId->execute($maskedHashId);
            }
            $error = false;  
        } catch (\Exception $e) {
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    
    /**
     * Creates a cart for the currently logged-in customer.
     *
     * @param int $customerId
     * @param int $storeId
     * @return \Magento\Quote\Model\Quote Cart object.
     * @throws CouldNotSaveException The cart could not be created.
     */
    public function createCustomerCarts($customerId, $storeId)
    {
        try {
            $quote = $this->quoteRepository->getActiveForCustomer($customerId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $customer = $this->customerRepository->getById($customerId);
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quoteFactory->create();
            $quote->setStoreId($storeId);
            $quote->setCustomer($customer);
            $quote->setCustomerIsGuest(0);
        }
        return $quote;
    }

       /**
     * Prepare address for customer quote.
     *
     * @param Quote $quote
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function _prepareCustomerQuote($quote)
    {
        /** @var Quote $quote */
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->customerRepository->getById($quote->getCustomerId());
        $hasDefaultBilling = (bool)$customer->getDefaultBilling();
        $hasDefaultShipping = (bool)$customer->getDefaultShipping();

        if ($shipping && !$shipping->getSameAsBilling()
            && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            if ($shipping->getQuoteId()) {
                $shippingAddress = $shipping->exportCustomerAddress();
            } else {
                $defaultShipping = $this->customerRepository->getById($customer->getId())->getDefaultShipping();
                if ($defaultShipping) {
                    try {
                        $shippingAddress = $this->addressRepository->getById($defaultShipping);
                    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
                    } catch (LocalizedException $e) {
                        // no address
                    }
                }
            }
            if (isset($shippingAddress)) {
                if (!$hasDefaultShipping) {
                    //Make provided address as default shipping address
                    $shippingAddress->setIsDefaultShipping(true);
                    $hasDefaultShipping = true;
                    if (!$hasDefaultBilling && !$billing->getSaveInAddressBook()) {
                        $shippingAddress->setIsDefaultBilling(true);
                        $hasDefaultBilling = true;
                    }
                }
                //save here new customer address
                $shippingAddress->setCustomerId($quote->getCustomerId());
                $this->addressRepository->save($shippingAddress);
                $quote->addCustomerAddress($shippingAddress);
                $shipping->setCustomerAddressData($shippingAddress);
                $this->addressesToSync[] = $shippingAddress->getId();
                $shipping->setCustomerAddressId($shippingAddress->getId());
            }
        }
    }


}


