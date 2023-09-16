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

class GetLastCloseRegister implements \Anyhow\SupermaxPos\Api\Supermax\GetLastCloseRegisterInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
    }

   /**
     * POST API
     * @api
     * 
     * @return string
     */
    public function getLastCloseRegister()
    {
        $result = array();
        $error = false;

        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $connection = $this->resource->getConnection();
                $userId = $this->supermaxSession->getPosUserId();
                $storeId = '';
                $storeName = '';
                $userDataCollection = $this->supermaxUserCollection
                ->addFieldToFilter('pos_user_id', $userId);
                $userData = $userDataCollection->getData();

                if(!empty($userData)) {
                    $storeId = $userData[0]['store_view_id'];
                }
                // Store Data
                $storeData = $this->_storeManager->getStore($storeId);
                if(!empty($storeData)) {
                    $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                    $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                }

                $userOutletData = $this->helper->joinUserOutletData($userId);
                if(!empty($userOutletData)){
                    $storeName = $userOutletData['outlet_name'];
                }
                if(!empty($storeCurrencyCode)) {
                    $storeCurrencySymbol = $this->currency->getCurrency($storeCurrencyCode)->getSymbol();
                }

                $registerData = $this->helper->getTotalRegisterData($userId, $status = 0, $storeId, $storeName, $storeCurrencyCode, $storeBaseCurrencyCode, $storeCurrencySymbol, $flag = false);
                if(!empty($registerData)){
                    $result = $registerData;
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