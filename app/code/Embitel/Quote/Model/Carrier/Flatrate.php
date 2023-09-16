<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Quote\Model\Carrier;

use Magento\OfflineShipping\Model\Carrier\Flatrate\ItemPriceCalculator;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface; 
use Magento\Shipping\Model\Rate\Result;
use Embitel\Quote\Helper\Data as HelperData;
use Magento\Quote\Model\QuoteFactory;

/**
 * Flat rate shipping model
 *
 * @api
 * @since 100.0.2
 */
class Flatrate extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'flatrate';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    protected $_deliveryMethod;

    protected $quoteFactory;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @var ItemPriceCalculator
     */
    private $itemPriceCalculator;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param ItemPriceCalculator $itemPriceCalculator
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\OfflineShipping\Model\Carrier\Flatrate\ItemPriceCalculator $itemPriceCalculator,
        HelperData $helper,
        QuoteFactory $quoteFactory,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->itemPriceCalculator = $itemPriceCalculator;
        $this->helper = $helper;
        $this->quoteFactory = $quoteFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Result|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $freeBoxes = $this->getFreeBoxesCount($request);
        $this->setFreeBoxes($freeBoxes);

        /** @var Result $result */
        $result = $this->_rateResultFactory->create();

        $quoteData = "";
        /** To get quote id */
        foreach ($request->getAllItems() as $item) {
            $quoteData = $item->getQuote(); 
            $quoteId = $item->getQuote()->getId();
            break;
        }
        if($quoteData) { 
            // $quoteData = $this->quoteFactory->create()->load($quoteId);
            // $promiseOptions = $quoteData->getPromise_options();
            // $shippingDetails = []; 
            // if(isset($promiseOptions) && $promiseOptions != '') {
            //     $promiseOptions = json_decode($quoteData->getPromise_options(),true);
            
            //     foreach($promiseOptions as $newkey=>$promiseOption){
            //         $shippingDetails['delivery_method'] = isset($promiseOptions[$newkey]['delivery_method']) ? $promiseOptions[$newkey]['delivery_method'] : '';
            //         if(isset($promiseOptions[$newkey]["promise_delivery_info"])){
            //             foreach($promiseOptions[$newkey]["promise_delivery_info"] as $newKey1 => $promiseDeliveryInfo){
            //                 $shippingDetails['cent_amount'] = $promiseDeliveryInfo['delivery_cost']['cent_amount'];
            //                 $shippingDetails['fraction'] = $promiseDeliveryInfo['delivery_cost']['fraction'];  
            //             }
            //         }
            //     }
            // }
            // print_r($shippingDetails); exit;
            
            //echo 'Shipping Amount : '.$shippingCost;
           // if ($shippingCost > 0) {
    
                // if(isset($shippingDetails['cent_amount']) && ($shippingDetails['cent_amount'] != 0)) {
                //     if(isset($shippingDetails['fraction']) && ($shippingDetails['fraction'] != 0)) {
                //         $shippingCost = $shippingDetails['cent_amount'] / $shippingDetails['fraction'];
                //     } else {
                //         $shippingCost = $shippingDetails['cent_amount'] / 100;
                //     }
                // } else {
                //     $shippingCost = isset($shippingDetails['cent_amount']) ? $shippingDetails['cent_amount']: 0;
                // }
                $shippingCost = (($quoteData->getPromiseShippingAmount() != null) && ($quoteData->getPromiseShippingAmount() != '')) ? $quoteData->getPromiseShippingAmount() : 0;
                $_deliveryMethod = 'HOME_DELIVERY';
                $method = $this->createResultMethodCustom($shippingCost,$_deliveryMethod);
                $result->append($method);
    
            // } else { 
                
            //     $error = $this->_rateErrorFactory->create();
            //     $errorMsg = $this->getConfigData('specificerrmsg');
            //     $error->setErrorMessage(
            //         $errorMsg ? $errorMsg : __(
            //             'Sorry, but we can\'t deliver to the destination country with this shipping module.'
            //         )
            //     );
            //     return $error;
            // }
        }
        
        return $result;
    }

    /**
     * Get count of free boxes
     *
     * @param RateRequest $request
     * @return int
     */
    private function getFreeBoxesCount(RateRequest $request)
    {
        $freeBoxes = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    $freeBoxes += $this->getFreeBoxesCountFromChildren($item);
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        return $freeBoxes;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * Returns shipping price
     *
     * @param RateRequest $request
     * @param int $freeBoxes
     * @return bool|float
     */
    private function getShippingPrice(RateRequest $request, $freeBoxes)
    {
        $shippingPrice = false;

        $configPrice = $this->getConfigData('price');
        if ($this->getConfigData('type') === 'O') {
            // per order
            $shippingPrice = $this->itemPriceCalculator->getShippingPricePerOrder($request, $configPrice, $freeBoxes);
        } elseif ($this->getConfigData('type') === 'I') {
            // per item
            $shippingPrice = $this->itemPriceCalculator->getShippingPricePerItem($request, $configPrice, $freeBoxes);
        }

        $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);

        if ($shippingPrice !== false && $request->getPackageQty() == $freeBoxes) {
            $shippingPrice = '0.00';
        }
        return $shippingPrice;
    }

    /**
     * Creates result method
     *
     * @param int|float $shippingPrice
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function createResultMethod($shippingPrice)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier('flatrate');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('flatrate');
        $method->setMethodTitle($this->getConfigData('name'));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        return $method;
    }

    private function createResultMethodCustom($shippingPrice,$deliveryMethod)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier('flatrate');
        $method->setCarrierTitle($deliveryMethod);

        $method->setMethod('flatrate');
        $method->setMethodTitle($deliveryMethod);

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        return $method;
    }

    /**
     * Returns free boxes count of children
     *
     * @param mixed $item
     * @return mixed
     */
    private function getFreeBoxesCountFromChildren($item)
    {
        $freeBoxes = 0;
        foreach ($item->getChildren() as $child) {
            if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                $freeBoxes += $item->getQty() * $child->getQty();
            }
        }
        return $freeBoxes;
    }
}
