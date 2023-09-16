<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class Currency implements \Anyhow\SupermaxPos\Api\Supermax\CurrencyInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->supermaxUser = $supermaxUser;
        $this->storeManager = $storeManager;
        $this->currency = $currency;
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function getCurrency()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();
            
            if($tokenFlag) {
                $storeCurrencySymbol = '';
                $storeCurrencyCode = '';
                $storeCurrencyRate = '';
                $storeBaseCurrencyCode = '';
                $storeDefaultCurrencyCode = '';
                $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                $storeViewData = $storeView->getData();
                
                if(!empty($storeViewData)) {
                    $storeViewId = $storeViewData[0]['store_view_id'];
                    $storeData = $this->storeManager->getStore($storeViewId);

                    if(!empty($storeData)) {
                        $storeCurrencyCode = $storeData->getCurrentCurrencyCode();

                        if(!empty($storeCurrencyCode)) {
                            $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
                        }
                        
                        $storeCurrencyRate = $storeData->getCurrentCurrencyRate();
                        $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                        $storeDefaultCurrencyCode = $storeData->getDefaultCurrencyCode();
                    }
                    

                    $result = array(
                        'currency_symbol' => html_entity_decode($storeCurrencySymbol),
                        'currency_code' => html_entity_decode($storeCurrencyCode),
                        'currency_rate' => (float)$storeCurrencyRate,
                        'store_base_currency_code' => html_entity_decode($storeBaseCurrencyCode),
                        'store_default_currency_code' => html_entity_decode($storeDefaultCurrencyCode)
                    );

                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}