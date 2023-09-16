<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Embitel\Quote\Helper\Data;
use Magento\Framework\HTTP\Client\Curl;

/**
 * @inheritdoc
 */
class CartItemFulfillableQty implements ResolverInterface
{

    /**
     * @var Totals
     */
    protected $curl;

    protected $logger;


    public function __construct(
          Curl $curl
    ) {
         $this->curl = $curl;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var Item $cartItem */
        $cartItem = $value['model'];

		$returnData = array();
		$returnData['fulfill_error'] = false;

        if($cartItem->getAdditionalData()){
            $additionalData=unserialize($cartItem->getAdditionalData());
            if(!empty($additionalData['qty'])){
                if(!empty($additionalData['error'])) {
                    $returnData['fulfill_error'] = true;
                }
                $returnData['fulfillable_quantity']=$additionalData['quantity_number'];
                $returnData['quantity_message'] = ($additionalData['quantity_number'] > 0) ?"Only ".$additionalData['quantity_number']." is available.":"Out of Stock.";
            } else {
                if ($cartItem->getQty() > $additionalData['quantity_number']) {
                    $returnData['fulfill_error'] = true;
                    $returnData['fulfillable_quantity'] = $additionalData['quantity_number'];
                    $returnData['quantity_message'] = ($additionalData['quantity_number'] > 0) ? "Only " . $additionalData['quantity_number'] . " is available." : "Out of Stock.";
                }
            }
        }
        return $returnData;

    }
  public function addLog($logData){
        if ($this->canWriteLog()) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog()
    {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/promise-engine-api.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }

}
