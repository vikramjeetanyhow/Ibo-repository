<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Checkout\Model;

class Cart extends \Magento\Checkout\Model\Cart
{
    /**
     * Add product to shopping cart (quote)
     *
     * @param int|Product $productInfo
     * @param \Magento\Framework\DataObject|int|array $requestInfo
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addProduct($productInfo, $requestInfo = null)
    {
        $isPromotion = "";
        if(isset($requestInfo) && isset($requestInfo['is_promotion'])){
            $isPromotion = $requestInfo['is_promotion'];
        }

        $product = $this->_getProduct($productInfo);
        $productId = $product->getId();

        if ($productId) {
            $request = $this->getQtyRequest($product, $requestInfo);
            try {
                $this->_eventManager->dispatch(
                    'checkout_cart_product_add_before',
                    ['info' => $requestInfo, 'product' => $product]
                );
                $result = $this->getQuote()->addProduct($product, $request);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->_checkoutSession->setUseNotice(false);
                $result = $e->getMessage();
            }
            /**
             * String we can get if prepare process has error
             */
            if (is_string($result) && $isPromotion == "") {
                if ($product->hasOptionsValidationFail()) {
                    $redirectUrl = $product->getUrlModel()->getUrl(
                        $product,
                        ['_query' => ['startcustomization' => 1]]
                    );
                } else {
                    $redirectUrl = $product->getProductUrl();
                }
                $this->_checkoutSession->setRedirectUrl($redirectUrl);
                if ($this->_checkoutSession->getUseNotice() === null) {
                    $this->_checkoutSession->setUseNotice(true);
                }
                throw new \Magento\Framework\Exception\LocalizedException(__($result));
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('The product does not exist.'));
        }

        $this->_eventManager->dispatch(
            'checkout_cart_product_add_after',
            ['quote_item' => $result, 'product' => $product]
        );
        $this->_checkoutSession->setLastAddedProductId($productId);
        return $this;
    }
}
