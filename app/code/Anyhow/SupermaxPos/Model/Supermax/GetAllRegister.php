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

class GetAllRegister implements \Anyhow\SupermaxPos\Api\Supermax\GetAllRegisterInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Locale\CurrencyInterface $currency
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->_storeManager = $storeManager;
        $this->supermaxUserCollection = $supermaxUserCollection;
        $this->currency = $currency;
    }

    /**
     * GET for Post api
     * @api
     * @param string $startdate
     * @param string $enddate
     * @return string
     */
    public function getAllRegister($startdate, $enddate)
    {
        $result = array();
        $error = false;
        // try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {

                $connection = $this->resource->getConnection();             
                $userId =$this->supermaxSession->getPosUserId();

                if(!empty($startdate)){
                    $filterStartDate = date('Y-m-d H:i:s', strtotime($startdate));
                } else {
                    $filterStartDate = date('Y-m-d H:i:s');
                }

                if(!empty($enddate)){
                    $filterEndDate = date('Y-m-d H:i:s', strtotime($enddate));
                } else {
                    $filterEndDate = date('Y-m-d H:i:s');
                }

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

                $allRegistersData = $this->getAllRegistersData($userId, $storeName, $storeCurrencyCode, $storeBaseCurrencyCode, $storeCurrencySymbol, $filterStartDate, $filterEndDate);

                if(!empty($allRegistersData)){
                    $result = $allRegistersData;
                }
               
            } else {
                $error = true;
            }
        // } catch (\Exception $e) {
        //     $error = true;
        // }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }


    public function getAllRegistersData($userId, $storeName, $storeCurrencyCode, $storeBaseCurrencyCode, $storeCurrencySymbol, $filterStartDate, $filterEndDate){
        $connection = $this->resource->getConnection();
        $userTable = $this->resource->getTableName('ah_supermax_pos_user');
        $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $result = array();
        $resultData = array();

        // get user name data and store name data
        $userName = '';
        $getUserDetails = $connection->query("SELECT * FROM $userTable WHERE pos_user_id = $userId")->fetchAll();
        if(!empty($getUserDetails)){
            foreach($getUserDetails as $getUserDetail){
                $firstName = $getUserDetail['firstname'];
                $lastName = $getUserDetail['lastname'];
                $userName = html_entity_decode($firstName.' '.$lastName);
                $storeName = html_entity_decode($storeName);
            }
        }
        $result['user_name'] = $userName;
        $result['store_name'] = $storeName;

        $query = $connection->query("SELECT * FROM $supermaxRegisterTable WHERE pos_user_id = '" . (int)$userId. "' AND status = 0 AND Date(date_close)>= '$filterStartDate' AND Date(date_close)<= '$filterEndDate' ORDER BY pos_register_id DESC ")->fetchAll();
        if(!empty($query)){
            foreach($query as $que){
                $posRegisterId = $que['pos_register_id'];
                $resultData[$posRegisterId] = array(
                    'register_id' => (int)$posRegisterId,
                    'name' => html_entity_decode($que['name']),
                    'user_id' => (int)$userId,
                    'status' => (bool)$que['status'],
                    'close_note' => html_entity_decode($que['close_note']),
                    'date_open' => html_entity_decode($que['date_open']),
                    'date_close' => html_entity_decode($que['date_close'])
                );        

                $getRegistertotals = $connection->query("SELECT SUM(expected) as expected_total, SUM(total) as total_total, SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = $posRegisterId")->fetchAll();
                
                if(!empty($getRegistertotals)){
                    foreach($getRegistertotals as $getRegistertotal){
                        $expected = (float)$this->helper->convert((float)$getRegistertotal['expected_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $total = (float)$this->helper->convert((float)$getRegistertotal['total_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $difference = (float)$this->helper->convert((float)$getRegistertotal['difference_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $resultData[$posRegisterId]['expected'] = $expected;
                        $resultData[$posRegisterId]['expected_formated'] = html_entity_decode($storeCurrencySymbol.$expected);
                        $resultData[$posRegisterId]['total'] = $total;
                        $resultData[$posRegisterId]['total_formated'] = html_entity_decode($storeCurrencySymbol.$total);
                        $resultData[$posRegisterId]['difference'] = $difference;
                        $resultData[$posRegisterId]['difference_formated'] = html_entity_decode($storeCurrencySymbol.$difference);
                    }
                }
                
                $getRegisterTransactions = $connection->query("SELECT * FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId . "' ORDER BY pos_register_transaction_id ASC")->fetchAll();
                $resultData[$posRegisterId]['transactions'] = array();
                $getTransactionData = array();
                if(!empty($getRegisterTransactions)){
                    
                    foreach ($getRegisterTransactions as $key => $value) {
                        $totalexpected = 0;
                        $getTransactionDetailData = array();
                        $getRegisterTransaction_details = $connection->query("SELECT * FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" .$posRegisterId. "' and code = '" .$value['code']. "' ORDER BY date_added ASC")->fetchAll();

                        if(!empty($getRegisterTransaction_details)){
                            foreach ($getRegisterTransaction_details as $key1 => $value1) {
                                $getTransactionDetailData[$key1]['register_id'] = (int)$value1['pos_register_id'];
                                $getTransactionDetailData[$key1]['register_transaction_id'] = (int)$value1['pos_register_transaction_id'];
                                $getTransactionDetailData[$key1]['register_transaction_detail_id'] = (int)$value1['pos_register_transaction_detail_id'];
                                $getTransactionDetailData[$key1]['code'] = html_entity_decode($value1['code']);
                                $getTransactionDetailData[$key1]['title'] = html_entity_decode($value1['title']);
                                $getTransactionDetailData[$key1]['description'] = html_entity_decode($value1['description']);
                                $getTransactionDetailData[$key1]['date_added'] = html_entity_decode($value1['date_added']);

                                $getTransactionDetailData[$key1]['amount'] = (float)$this->helper->convert((float)$value1['amount'], $storeBaseCurrencyCode, $storeCurrencyCode);
                                $getTransactionDetailData[$key1]['amount_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionDetailData[$key1]['amount']);

                                if($userId) {
                                    $getTransactionDetailData[$key1]['user_name'] = html_entity_decode($userName);
                                } else {
                                    $getTransactionDetailData[$key1]['user_name'] = '';
                                }

                            }
                        }
                        $getTransactionData[$key]['register_transaction_id'] = (int)$value['pos_register_transaction_id'];
                        $getTransactionData[$key]['register_id'] = (int)$value['pos_register_id'];
                        $getTransactionData[$key]['code'] = html_entity_decode($value['code']);
                        
                        $getTransactionData[$key]['expected'] = (float)$this->helper->convert((float)$value['expected'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $getTransactionData[$key]['expected_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['expected']);

                        $getTransactionData[$key]['total'] = (float)$this->helper->convert((float)$value['total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $getTransactionData[$key]['total_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['total']);

                        $getTransactionData[$key]['difference'] = (float)$this->helper->convert((float)$value['difference'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $getTransactionData[$key]['difference_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['difference']);

                        $getTransactionData[$key]['transaction_detail'] = $getTransactionDetailData;
                        $resultData[$posRegisterId]['transactions'][$value['code']] = $getTransactionData[$key];
                    }   
                }
            }
        }
        $result['registers'] = $resultData;
        return $result;
    }


}