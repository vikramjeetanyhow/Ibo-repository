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

class CashManagement implements \Anyhow\SupermaxPos\Api\Supermax\CashManagementInterface
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
    public function cashManagement()
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

                    $title = $params['title'];
                    $amount = (float)$this->helper->convert($params['amount'], $storeCurrencyCode, $storeBaseCurrencyCode);
                    $note = $params['note'];
                    $date = $this->timezone->date(new \DateTime())->format('Y-m-d h:i:s');

                    $currentRegisterId = (int)$this->helper->getCurrentRegister($userId);
                    $registerTransactionId = (int)$this->getRegisterTransaction($currentRegisterId, $code = 'cash');
                    
                    $supermaxRegisterTransactionDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
                    
                    // update register transaction Detail table
                    $connection->query("INSERT INTO $supermaxRegisterTransactionDetailTable SET pos_register_transaction_id = $registerTransactionId, pos_register_id = $currentRegisterId, code = 'cash', title = '".$title."', description = '".$note."', amount = '".$amount."', date_added = '".$date."' "); 

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

    public function getRegisterTransaction($registerId, $code = '') {
        $posRegisterTransactionId = '';
        $connection = $this->resource->getConnection();
        $supermaxRegisterTransactionTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        if ($code) {
            $query = $connection->query("SELECT * FROM $supermaxRegisterTransactionTable WHERE pos_register_id = '" . (int)$registerId . "' and code = '" .$code. "'");
        } else {
            $query = $connection->query("SELECT * FROM $supermaxRegisterTransactionTable WHERE pos_register_id = '" . (int)$registerId . "'");
        }
        if(!empty($query)){
            foreach($query as $posRegisterTransactionData){
                $posRegisterTransactionId = (int)$posRegisterTransactionData['pos_register_transaction_id'];
            }
        }
        return $posRegisterTransactionId;
    }
}