<?php

namespace Embitel\Catalog\Model;

use Embitel\Catalog\Api\IboCartInterface;

class IboCart implements IboCartInterface
{
    public function __construct(
        \Psr\Log\LoggerInterface $logger, 
        \Magento\QuoteGraphQl\Model\Cart\GetCartForUser $getCartForUser,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->logger = $logger;
        $this->getCartForUser = $getCartForUser;
        $this->quoteFactory = $quoteFactory;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
    }

    /**
     * Retrieve cart by cart id
     *
     * @param mixed $cart_id
     * @return array
     */
    public function fetchCart($cart_id)
    {
        $result = array();
        $error = "";
        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($cart_id);
            if($quoteId) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $itemData = array();
                $items = $quote->getAllItems();
                if(!empty($items)) {
                    foreach ($items as $key=>$item) {
                        $itemData[] = array(
                            "product" => ["sku" => $item->getSku()],
                            "quantity" => (int)$item->getQty()
                        );
                    }
                }
                $result = array(
                    "id" => $cart_id,
                    "promise_id" => $quote->getPromiseId(),
                    "customer_id" => $quote->getCustomerId(),
                    "items" => $itemData
                );
            }
        }  catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $data = array();
        if(!empty($error)) {
            $data['error'] = true;
            $data['message'] = $error;
        } else {
            $data['error'] = false;
            $data['cart'] = json_decode(json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE), true);
        }
        return [$data];
    }
}
