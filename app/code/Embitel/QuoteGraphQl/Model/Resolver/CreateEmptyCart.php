<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForGuest;

/**
 * @inheritdoc
 */
class CreateEmptyCart implements ResolverInterface
{
    /**
     * @var CreateEmptyCartForCustomer
     */
    private $createEmptyCartForCustomer;

    /**
     * @var CreateEmptyCartForGuest
     */
    private $createEmptyCartForGuest;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @param CreateEmptyCartForCustomer $createEmptyCartForCustomer
     * @param CreateEmptyCartForGuest $createEmptyCartForGuest
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        CreateEmptyCartForCustomer $createEmptyCartForCustomer,
        CreateEmptyCartForGuest $createEmptyCartForGuest,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Embitel\Quote\Helper\Data $helper,
        \Magento\Framework\App\RequestInterface $request,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->createEmptyCartForCustomer = $createEmptyCartForCustomer;
        $this->createEmptyCartForGuest = $createEmptyCartForGuest;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->_quoteFactory = $quoteFactory;
        $this->request = $request;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $customerId = $context->getUserId();
        $quoteId = "";
        $predefinedMaskedQuoteId = null;
        if (isset($args['input']['cart_id'])) {
            $predefinedMaskedQuoteId = $args['input']['cart_id'];
            $this->validateMaskedId($predefinedMaskedQuoteId);
        }
        $maskedQuoteId = (0 === $customerId || null === $customerId)
            ? $this->createEmptyCartForGuest->execute($predefinedMaskedQuoteId)
            : $this->createEmptyCartForCustomer->execute($customerId, $predefinedMaskedQuoteId);
        if($maskedQuoteId){
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedQuoteId);
        }
        if($quoteId){
            $quote = $this->_quoteFactory->create()->loadByIdWithoutStore($quoteId);
            $getDefaultShippingPincode = $this->helper->getDefaultShippingPostalCode();
            $quote->setPostalCode($getDefaultShippingPincode);

            if(null !== $this->request->getParam('source_id') && ($this->request->getParam('source_id') === 'online' || $this->request->getParam('source_id') === 'store')) {
                $source = $this->request->getParam('source_id');
                $quote->setChannel($source);
            }
            if(null !== $this->request->getHeader('sourceChannelInfo')) {
                $sourceChannelInfo = $this->request->getHeader('sourceChannelInfo');
                $quote->setChannelInfo($sourceChannelInfo);
            }
            $quote->save();
        }
        return $maskedQuoteId;
    }

    /**
     * Validate masked id
     *
     * @param string $maskedId
     * @throws GraphQlAlreadyExistsException
     * @throws GraphQlInputException
     */
    private function validateMaskedId(string $maskedId): void
    {
        if (mb_strlen($maskedId) != 32) {
            throw new GraphQlInputException(__('Cart ID length should to be 32 symbols.'));
        }

        if ($this->isQuoteWithSuchMaskedIdAlreadyExists($maskedId)) {
            throw new GraphQlAlreadyExistsException(__('Cart with ID "%1" already exists.', $maskedId));
        }
    }

    /**
     * Check is quote with such maskedId already exists
     *
     * @param string $maskedId
     * @return bool
     */
    private function isQuoteWithSuchMaskedIdAlreadyExists(string $maskedId): bool
    {
        try {
            $this->maskedQuoteIdToQuoteId->execute($maskedId);
            return true;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }
}
