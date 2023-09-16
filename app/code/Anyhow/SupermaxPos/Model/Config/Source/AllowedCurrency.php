<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

/**
 * All product unique code options for system.xml file
 */
namespace Anyhow\SupermaxPos\Model\Config\Source;

class AllowedCurrency implements \Magento\Framework\Option\ArrayInterface
{

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency
    ) {       
        $this->storeManager = $storeManager;    
        $this->localeCurrency = $localeCurrency;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        
        $result = array();
        $currencyCodes = $this->storeManager->getStore()->getAvailableCurrencyCodes(); 
        if($currencyCodes){
            foreach ($currencyCodes as $code){
                $currencyRate = $this->storeManager->getStore()->getBaseCurrency()->getRate($code);
                if(empty($currencyRate)){
                    continue;
                }
                $result[]= array('value'=> $code,
                'label' => $this->localeCurrency->getCurrency($code)->getName());
            }
        }

        return $result;
    }
}