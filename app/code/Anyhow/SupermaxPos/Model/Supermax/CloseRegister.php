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

class CloseRegister implements \Anyhow\SupermaxPos\Api\Supermax\CloseRegisterInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->priceCurrencyInterface = $priceCurrencyInterface;
        $this->_storeManager = $storeManager;
        $this->timezone = $timezone;
    }

   /**
     * POST API
     * @api
     * 
     * @return string
     */
    public function closeRegister()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)){

                    $userId = $this->supermaxSession->getPosUserId();
                    
                    $storeId = '';
                    $connection = $this->resource->getConnection();

                    $userDataCollection = $this->supermaxUserCollection
                                                ->addFieldToFilter('pos_user_id', $userId);
                    $userData = $userDataCollection->getData();

                    if(!empty($userData)) {
                        $storeId = $userData[0]['store_view_id'];
                    }
                    $storeData = $this->_storeManager->getStore($storeId);
                    if(!empty($storeData)) {
                        $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                        $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                    }

                    $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
                    $supermaxRegisterTransactionTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
                    $currentRegisterId = (int)$this->helper->getCurrentRegister($userId);
                    $date = $this->timezone->date(new \DateTime())->format('Y-m-d h:i:s');
                    $note = $params['note'];
                    
                    foreach($params['postData'] as $key => $value){
                        $code = $key;
                        $expected = (float)$this->helper->convert($value['expected'], $storeCurrencyCode, $storeBaseCurrencyCode);
                        $counted = (float)$this->helper->convert($value['counted'], $storeCurrencyCode, $storeBaseCurrencyCode);
                        $difference = (float)$this->helper->convert($value['difference'], $storeCurrencyCode, $storeBaseCurrencyCode);
                        // update register transaction table
                        $connection->query("UPDATE $supermaxRegisterTransactionTable SET expected = $expected, total = $counted, difference = $difference WHERE pos_register_id = $currentRegisterId  AND code = '".$code."'"); 
                    }

                    // update register table
                    $connection->query("UPDATE $supermaxRegisterTable SET status = 0, close_note = '".$note."', date_close = '". $date ."' WHERE pos_register_id = $currentRegisterId"); 
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