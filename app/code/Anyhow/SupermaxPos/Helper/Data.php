<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Helper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Authorization\Model\UserContextInterface;
class Data
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\RequestInterface $request,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosApi\Collection $supermaxPosApiCollection,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxConnection\Collection $supermaxConnectionCollection, 
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxConnectionUpdate\Collection $connectionUpdate,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $attributeCollection,
        \Magento\Customer\Api\CustomerMetadataInterface $customerMetadata,
        \Magento\Backend\Model\Auth\Session $authSession
    ){
        $this->scopeConfig = $scopeConfig;
        $this->request = $request;
        $this->supermaxPosApiCollection = $supermaxPosApiCollection;
        $this->resource = $resourceConnection;
        $this->supermaxConnectionCollection = $supermaxConnectionCollection;
        $this->priceHelper = $priceHelper;
        $this->_storeManagerInterface = $storeManagerInterface;
        $this->connectionUpdate = $connectionUpdate;
        $this->supermaxSession = $supermaxSession;
        $this->orderRepository = $orderRepository;
        $this->priceCurrencyInterface = $priceCurrencyInterface;
        $this->_filesystem = $filesystem;
        $this->attributeCollection = $attributeCollection;
        $this->customerMetadata = $customerMetadata;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->authSession = $authSession;
    }

    //  To get store configuration page data.
    public function getConfig($config_path, $storeId = null)
    {
        return $this->scopeConfig->getValue(
                $config_path,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    // To set HTTP Headers
    public function setHeaders()
    {
        header("Access-Control-Allow-Headers: access-token, Content-Type, conn");
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: * ");
    }
    
    // To check wheter a user is authorized or not.
    public function userAutherization()
    {
        $tokenFlag = false;
        $headers = $this->getAllPosHeaders();
        if ($headers && (isset($headers['conn']) || isset($headers['Conn']))) {
            $conn = (isset($headers['conn']) ? $headers['conn'] : $headers['Conn']);
            $posConnection = $this->connectionUpdate($conn);
            $posConnectionCode = $posConnection['connection_code'];
            $posConnectionId = $posConnection['connection_id'];
            $this->supermaxSession->setPosConnectionCode($posConnectionCode);
            $this->supermaxSession->setPosConnectionId($posConnectionId);
        }
        if ($headers && (isset($headers['access-token']) || isset($headers['Access-Token']))) {
            $token = (isset($headers['access-token']) ? $headers['access-token'] : $headers['Access-Token']);
            $tokenData = $this->isAccessTokenValid($token);
            if(!empty($tokenData)) {
                $supermaxUserId = $tokenData['pos_user_id'];
                $this->supermaxSession->setPosUserId($supermaxUserId);
                $userAndOutletStatus = $this->joinUserAndOutletData($supermaxUserId);
                $posStatus = (bool)$this->getPosStatus();
                $userStatus = (bool)$userAndOutletStatus['user_status'];
                $outletStatus = (bool)$userAndOutletStatus['outlet_status'];
                // Check User and Outlet status
                if($posStatus && $userStatus && $outletStatus){
                    $tokenFlag = true;
                }
            }
        } 
        return $tokenFlag;
    }


    // Product price currency helper function
    public function formatPrice($price)
    {
        return $this->priceHelper->currency($price, true, false);
    }

    //  To set Connection_id or to update connection Date.
    public function connectionUpdate($conn)
    {
        $connectioncollection = $this->supermaxConnectionCollection->addFieldToFilter('connection_code', $conn);
        $connection= $this->resource->getConnection();
        $supermaxConnectionTable = $this->resource->getTableName('ah_supermax_pos_connection');
        $supermaxConnectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
        $date = date("Y-m-d H:i:s");
        $collectionData = $connectioncollection->getData();
        if(!empty($collectionData)) {
            $posConnectionId = $collectionData[0]['pos_connection_id'];
            $posConnectionCode = $collectionData[0]['connection_code'];
            $where = $connection->quoteInto('pos_connection_id = ?', $posConnectionId);
            $query = $connection->update($supermaxConnectionTable,
                ['connection_date' => $date], $where);         
        } else {
            $posConnectionCode = $conn;
            // Insert data in connection table.
            $query = $connection->insert($supermaxConnectionTable,
                ['connection_code' => $posConnectionCode, 'connection_date' => $date]);   
            $posConnectionId = $connection->lastInsertId();
            
            // Insert data in connection update table.
            // $codes = array(
            //     'product',
            //     'country',
            //     'customer'
            // );
            // foreach ($codes as $key => $code) {
            //     $connection->insert($supermaxConnectionUpdateTable,
            //     ['pos_connection_id' => (int)$posConnectionId, 'code' => $code, 'date_added' => $date]); 
            // }     
        }
        $connectionData = array('connection_id' => $posConnectionId, 'connection_code' => $posConnectionCode);
        return $connectionData;
    }

    public function getParams(){
        $postData = file_get_contents('php://input');
        $params = json_decode($postData, true);
        //$params = $this->request->getParams();
        return $params;
    }

    public function connectionUpdateForOrderItems($orderId){
        $connection = $this->resource->getConnection();
        $connectionTable = $this->resource->getTableName('ah_supermax_pos_connection');
        $connectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
        $orderItemDatas = $this->orderRepository->get($orderId);
        // if(!empty($orderItemDatas->getAllVisibleItems())){
        //     foreach($orderItemDatas->getAllVisibleItems() as $orderItem){
        //         $productId = $orderItem->getProductId();
        //         if(!empty($productId)){
        //             $connectionId = array();
        //             $posConnectiondata = $connection->query("SELECT * FROM  $connectionTable");  
        //             $code = 'product';
        //             $date = date("Y-m-d H:i:s");
        //             if(!empty($posConnectiondata)){
        //                 foreach($posConnectiondata as $posCondata){
        //                     $connectionId = $posCondata['pos_connection_id'];
        //                     $connection->insert($connectionUpdateTable,
        //                         ['pos_connection_id'=> $connectionId, 'code'=> $code, 'update'=> $productId, 'date_added'=> $date]
        //                     );   
        //                 }
        //             }
        //             // write in event file
        //             $this->updateEventFile($productId, $code);
        //         }
        //     }
        // }
    }

    public function connectionUpdateEvent($updateId, $code, $flag = 'codeUpdate'){
        // $connection = $this->resource->getConnection();
        // $connectionTable = $this->resource->getTableName('ah_supermax_pos_connection');
        // $connectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
        // $connectionId = array();
        // $posConnectiondata = $connection->query("SELECT * FROM  $connectionTable"); 
        // //$posConnectiondata = $this->supermaxConnectionCollection->getData(); 
        // $date = date("Y-m-d H:i:s");
        // if(!empty($posConnectiondata)){
        //     foreach($posConnectiondata as $posCondata){
        //         $connectionId = $posCondata['pos_connection_id'];
        //         $connection->insert($connectionUpdateTable,
        //                     ['pos_connection_id'=> $connectionId, 'code'=> $code, 'update'=> $updateId, 'date_added'=> $date]
        //                 ); 
        //     }
        // }

        // // write in event file
        // if($flag == 'productDelete'){
        //     $this->updateEventFile($updateId, 'delete_products');
        // }elseif($flag == 'customerDelete'){
        //     $this->updateEventFile($updateId, 'delete_customers');
        // }else{
        //     $this->updateEventFile($updateId, $code);
        // }
    }

    // check connection update.
    public function checkConnectionUpdate($connectionId, $code){
        $connectionUpdateCollection = $this->connectionUpdate->addFieldToFilter('pos_connection_id', $connectionId)->addFieldToFilter('code', $code);
        $collectionUpdateData = $connectionUpdateCollection->getData();
        return $collectionUpdateData;
    }

    // delete connection update.
    public function deleteConnectionUpdate($connectionId, $code, $update){
        // $connection= $this->resource->getConnection();
        // $connectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
        // $sql = "DELETE FROM $connectionUpdateTable WHERE pos_connection_id = '" .$connectionId. "' AND code = '" .$code. "'";

        // if (is_null($update)) {
        //     $sql .= " AND $connectionUpdateTable.update is NULL";
        // } else {
        //     $update = (int)$update;
        //     $sql .= " AND pos_connection_update_id = $update";
        // }
        // $connection->query($sql);
    }

    //Get Pos Status
    public function getPosStatus(){
        $config_path = 'ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status';
        $status = $this->getConfig($config_path, $storeId = null);
        return $status;
    }

    // To get data from user and outlet tables.
    public function joinUserAndOutletData($userId)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['user_status'=> 'status']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['outlet_status'=> 'status', 'allowed_ips']
        )->where("spu.pos_user_id = $userId");
        $userData = $connection->query($select)->fetch();
        return $userData;
    }

    // Convert base currency price to current currency price.
    public function convertBaseToCurrentPrice($amount = 0, $store = null, $currency = null)
    {
        // Get Current store id if storeId is not passed
        if ($store == null) {
            $store = $this->_storeManager->getStore()->getStoreId(); 
        }
        $rate = $this->priceCurrencyInterface->convert($amount, $store, $currency); 

        return $rate;
    }

    public function convert($amount = 0, $fromCurrencyCode, $toCurrencyCode){

        $toCurrencyRate = (float)$this->_storeManagerInterface->getStore()->getBaseCurrency()->getRate($toCurrencyCode);
        $fromCurrencyRate = (float)$this->_storeManagerInterface->getStore()->getBaseCurrency()->getRate($fromCurrencyCode);
        $convertedAmount = (float)$amount * ($toCurrencyRate / $fromCurrencyRate);
        return $convertedAmount;
        
    }
    // Convert currenct currency price to base currency price.
    public function convertCurrentToBasePrice($amount = 0, $store = null)
    {        
        if($amount == 0){
            return 0;
        }
        if($amount < 0 ){
            $amount = abs($amount);
        }
        // Get Current store id if storeId is not passed
        if ($store == null) {
            $store = $this->_storeManager->getStore()->getStoreId(); 
        }
        
        $rate = (float)$this->priceCurrencyInterface->convert($amount, $store)/$amount;
        $convertedAmount = (float)$amount / $rate;
        if($amount < 0 ){
            return '-'.$convertedAmount;
        }
        return $convertedAmount;
    }

    // To get data from register tables by registerId.
    public function joinRegisterData($connection, $registerId)
    {
        $select = $connection->select();
        $select->from(
            ['spr' => $this->resource->getTableName('ah_supermax_pos_register')]
        )->joinLeft(
            ['sprt' => $this->resource->getTableName('ah_supermax_pos_register_transaction')],
            "spr.pos_register_id = sprt.pos_register_id",
            ['pos_register_transaction_id', 'code', 'expected', 'total', 'difference']
        )->joinLeft(
            ['sprtd' => $this->resource->getTableName('ah_supermax_pos_register_transaction_detail')],
            "spr.pos_register_id= sprtd.pos_register_id",
            ['pos_register_transaction_detail_id', 'title', 'description', 'amount', 'date_added']
        )->where("spr.pos_register_id = $registerId");
        
        return $select;
    }

    // To get data from register tables by status and userId.
    public function joinGetRegisterData($connection, $status, $userId)
    {
        $select = $connection->select();
        $select->from(
            ['spr' => $this->resource->getTableName('ah_supermax_pos_register')]
        )->joinLeft(
            ['sprt' => $this->resource->getTableName('ah_supermax_pos_register_transaction')],
            "spr.pos_register_id = sprt.pos_register_id",
            ['pos_register_transaction_id', 'code', 'expected', 'total', 'difference']
        )->joinLeft(
            ['sprtd' => $this->resource->getTableName('ah_supermax_pos_register_transaction_detail')],
            "spr.pos_register_id= sprtd.pos_register_id",
            ['pos_register_transaction_detail_id', 'title', 'description', 'amount', 'date_added']
        )->where("spr.status = $status AND spr.pos_user_id = $userId")->Order('spr.pos_register_id DESC')->limit(1);
        
        return $select;
    }

    // To get current register Id
    public function getCurrentRegister($userId) {
        $posRegisterId = null;
        $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
        $connection = $this->resource->getConnection();
        $query = $connection->query("SELECT * FROM $supermaxRegisterTable WHERE pos_user_id = $userId and status = 1 ")->fetchAll();
        if(!empty($query)){
            foreach($query as $posRegisterData){
                $posRegisterId = $posRegisterData['pos_register_id'];
            }
        }
        return $posRegisterId;
    }

    public function getTotalRegisterData($userId, $status, $storeId, $storeName, $storeCurrencyCode,$storeBaseCurrencyCode, $storeCurrencySymbol, $flag){
        $posRegisterId = null;
        $connection = $this->resource->getConnection();
        $userTable = $this->resource->getTableName('ah_supermax_pos_user');
        $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $resultData = array();
        $query = $connection->query("SELECT * FROM $supermaxRegisterTable WHERE pos_user_id = '" . (int)$userId. "' and status = $status ORDER BY pos_register_id DESC LIMIT 0, 1")->fetchAll();
        if(!empty($query)){
            foreach($query as $que){
                $posRegisterId = $que['pos_register_id'];
                $name = $que['name'];
                $status = $que['status'];
                $closeNote = $que['close_note'];
                $dateOpen = $que['date_open'];
                $dateClose = $que['date_close'];
            }
            $resultData['register_id'] = (int)$posRegisterId;
            $resultData['name'] = html_entity_decode($name);
            $resultData['user_id'] = (int)$userId;
            $resultData['status'] = (bool)$status;
            $resultData['close_note'] = html_entity_decode($closeNote);
            $resultData['date_open'] = html_entity_decode($dateOpen);
            $resultData['date_close'] = html_entity_decode($dateClose);
        }

        if($posRegisterId) {

            if($flag){
                $getRegistertotals = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" .$posRegisterId. "'")->fetchAll();
            
                if(!empty($getRegistertotals)){
                    foreach($getRegistertotals as $getRegistertotal){
                        $expected = (float)$this->convert((float)$getRegistertotal['expected_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $resultData['expected'] = $expected;
                        $resultData['expected_formated'] = html_entity_decode($storeCurrencySymbol.$expected);
                    }
                }

            }else{
                $getRegistertotals = $connection->query("SELECT SUM(expected) as expected_total, SUM(total) as total_total, SUM(difference) as difference_total FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId. "'")->fetchAll();
                
                if(!empty($getRegistertotals)){
                    foreach($getRegistertotals as $getRegistertotal){
                        $expected = (float)$this->convert((float)$getRegistertotal['expected_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $total = (float)$this->convert((float)$getRegistertotal['total_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $difference = (float)$this->convert((float)$getRegistertotal['difference_total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $resultData['expected'] = $expected;
                        $resultData['expected_formated'] = html_entity_decode($storeCurrencySymbol.$expected);
                        $resultData['total'] = $total;
                        $resultData['total_formated'] = html_entity_decode($storeCurrencySymbol.$total);
                        $resultData['difference'] = $difference;
                        $resultData['difference_formated'] = html_entity_decode($storeCurrencySymbol.$difference);
                    }
                }
            }
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
            $resultData['user_name'] = $userName;
            $resultData['store_name'] = $storeName;


            $getRegisterTransactions = $connection->query("SELECT * FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$posRegisterId . "' ORDER BY pos_register_transaction_id ASC")->fetchAll();
            $resultData['transactions'] = array();
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

                            $getTransactionDetailData[$key1]['amount'] = (float)$this->convert((float)$value1['amount'], $storeBaseCurrencyCode, $storeCurrencyCode);
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
                    if($flag){
                        $getRegistertotals = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" .$posRegisterId. "' AND code = '".$value['code']."'")->fetchAll();
            
                        if(!empty($getRegistertotals)){
                            foreach($getRegistertotals as $getRegistertotal){
                                $totalexpected += $getRegistertotal['expected_total'];
                                $getTransactionData[$key]['expected'] = (float)$this->convert((float)$totalexpected, $storeBaseCurrencyCode, $storeCurrencyCode);
                                $getTransactionData[$key]['expected_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['expected']);
                            }
                        }
                    }else{
                        $getTransactionData[$key]['expected'] = (float)$this->convert((float)$value['expected'], $storeBaseCurrencyCode, $storeCurrencyCode);
                        $getTransactionData[$key]['expected_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['expected']);
                    }

                    $getTransactionData[$key]['total'] = (float)$this->convert((float)$value['total'], $storeBaseCurrencyCode, $storeCurrencyCode);
                    $getTransactionData[$key]['total_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['total']);

                    $getTransactionData[$key]['difference'] = (float)$this->convert((float)$value['difference'], $storeBaseCurrencyCode, $storeCurrencyCode);
                    $getTransactionData[$key]['difference_formated'] = html_entity_decode($storeCurrencySymbol.$getTransactionData[$key]['difference']);

                    $getTransactionData[$key]['transaction_detail'] = $getTransactionDetailData;
                    $resultData['transactions'][$value['code']] = $getTransactionData[$key];
                }   
            }
        }
        return $resultData;
    }

    public function addRegisterTransaction($userId, $code = '', $title = '', $description = '', $amount = 0, $storeCurrencyCode, $storeBaseCurrencyCode, $storeId, $storeCurrencySymbol, $cashPaid, $cashReturned) {
        $registerId = null;
        $connection = $this->resource->getConnection();
        $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');

        $registers = $connection->query("SELECT * FROM $supermaxRegisterTable WHERE pos_user_id = '" .$userId. "' and status = '1'")->fetchAll();
        if(!empty($registers)){
            foreach($registers as $register){
                $registerId = (int)$register['pos_register_id'];
            }
        }
        if($registerId){
            $registerTransactionId = null;
            $register_transaction = $connection->query("SELECT * FROM $supermaxRegisterTransTable WHERE pos_register_id = '" .$registerId. "' and code = '" .$code. "'")->fetchAll();
            
            if(!empty($register_transaction)) {
                foreach($register_transaction as $registerTransaction){
                    $registerTransactionId = (int)$registerTransaction['pos_register_transaction_id'];
                }
            } 
            if(empty($registerTransactionId)) {
                $connection->query("INSERT INTO $supermaxRegisterTransTable SET pos_register_id = '" .$registerId. "', code = '" .$code. "'");
                $registerTransactionId = (int)$connection->lastInsertId();
            }
    
            $date = date("Y-m-d H:i:s");
            if($code == 'cash' || $code == 'offline'){
                if($cashPaid > 0) {
                    $cashPaid = (float)$this->convert((float)$cashPaid, $storeCurrencyCode, $storeBaseCurrencyCode);
                } else {
                    $cashPaid = (float)$this->convert((float)$amount, $storeCurrencyCode, $storeBaseCurrencyCode);
                }
                $connection->query("INSERT INTO $supermaxRegisterTransDetailTable SET pos_register_transaction_id = '" . $registerTransactionId. "', pos_register_id = '" .$registerId. "', code = '" .$code. "', title = '" .$title. "', description = '" .$description. "', amount = '" .$cashPaid. "', date_added = '" .$date."'");
                
                // if cash returned is not zero.
                if($cashReturned > 0) {
                    $cashReturned = -$cashReturned;
                    $cashReturned = (float)$this->convert((float)$cashReturned, $storeCurrencyCode, $storeBaseCurrencyCode);
                    $connection->query("INSERT INTO $supermaxRegisterTransDetailTable SET pos_register_transaction_id = '" . $registerTransactionId. "', pos_register_id = '" .$registerId. "', code = '" .$code. "', title = '" .$title. "', description = '" .$description. "', amount = '" .$cashReturned. "', date_added = '" .$date."'");
                }

            } else {
                $amount = (float)$this->convert((float)$amount, $storeCurrencyCode, $storeBaseCurrencyCode);
                $connection->query("INSERT INTO $supermaxRegisterTransDetailTable SET pos_register_transaction_id = '" . $registerTransactionId. "', pos_register_id = '" .$registerId. "', code = '" .$code. "', title = '" .$title. "', description = '" .$description. "', amount = '" .$amount. "', date_added = '" .$date."'");
            }
        }
    }
    
    // To get data from user and outlet tables.
    public function joinUserOutletData($userId)
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')]
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id"
        )->where("spu.pos_user_id = $userId");
        $userData = $connection->query($select)->fetch();
        return $userData;
    }


    // function to update data in event.json file
    public function updateEventFile($updateID, $updateCode){
        $config_path = 'ah_supermax_pos_sync_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status';
        $posSyncModuleStatus = (bool)$this->getConfig($config_path, $storeId = null);
        if ($posSyncModuleStatus) {
            $sseDir = $this->_filesystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath('supermax/sse/');
            $sseEventFile = $sseDir. 'event.json';
            if(is_file($sseEventFile)){
                $sseEventFileContent = json_decode(file_get_contents($sseEventFile), true);
                if (!empty($sseEventFileContent) && is_array($sseEventFileContent)) {
                    foreach ($sseEventFileContent as $sessionId => $value) {
                        if (!isset($value[$updateCode])) {
                            $value[$updateCode] = array();
                        }
                        array_push($value[$updateCode], (int)$updateID);
                        $sseEventFileContent[$sessionId] = $value;
                    }
                    file_put_contents($sseEventFile, json_encode($sseEventFileContent));
                }
            }
        }
    }

    public function getCustomerAttributeValue(array $attribute) {
        $attributeValue = [];
        if (!empty($attribute['label']) && !empty($attribute['key'])) {
            $options = $this->customerMetadata->getAttributeMetadata($attribute['key'])->getOptions();
            $customerTypeArray = explode(',',$attribute['label']);
            foreach ($options as $optionData) {
                if (in_array($optionData->getLabel(), $customerTypeArray)) {
                    $attributeValue[] = $optionData->getValue();
                }
            }
        }
        return $attributeValue;
    }

    public function getCustomerAttribute($connection, $customer, $attributeCode) {
        $optionTable = $this->resource->getTableName('eav_attribute_option_value');
        $customerStatus = $customer->getCustomAttribute($attributeCode);
        $customerType = "";
        if (!empty($customer->getCustomAttribute($attributeCode))) {
            $customerAttributeValue = $customer->getCustomAttribute($attributeCode)->getValue();
            if($attributeCode == "business_activities" && !empty($customerAttributeValue)) {
                $optionValues = explode(",", $customerAttributeValue);
                if(!empty($optionValues)) {
                    foreach ($optionValues as $option) {
                        $customerOption = $connection->query("SELECT value FROM  $optionTable WHERE option_id= '" . $option . "' AND store_id=0")->fetch();
                        $customerType =  empty($customerType) ? $customerType . $customerOption['value'] : $customerType . ", " . $customerOption['value'];
                    }
                }

            } else {
                $customerOption = $connection->query("SELECT value FROM  $optionTable WHERE option_id= '" . $customerAttributeValue . "' AND store_id=0")->fetch();
                $customerType = $customerOption['value'];
            }
        }
        return $customerType;
    }

    public function curlPostRequest($url, $data, $header, $log = false, $requestType = "") {
        if($log) {
            $this->addLogData("---- Start " . $requestType . " Request ----");
            $this->addLogData("Request URL: " . $url);
            $this->addLogData("Request Method: POST");
            $this->addLogData("Request Headers: " . json_encode($header));
            $this->addLogData("Request Payload: " . json_encode($data));
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
        $resultData = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($log) {
            $this->addLogData("API Response Code : " . $code);
            $this->addLogData("API Response: " . $resultData);
            $this->addLogData("---- End " . $requestType . " Request ----");
        }
        return  $resultData;
    }

    public function curlGetRequest($url, $header, $log = false, $requestType = "") {
        if($log) {
            $this->addLogData("---- Start " . $requestType . " Request ----");
            $this->addLogData("Request URL: " . $url);
            $this->addLogData("Request Method: GET");
            $this->addLogData("Request Headers: " . json_encode($header));
            $this->addLogData("Request Payload: N/A");
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                              
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $resultData = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($log) {
            $this->addLogData("API Response Code : " . $code);
            $this->addLogData("API Response: " . $resultData);
            $this->addLogData("---- End " . $requestType . " Request ----");
        }
        return  $resultData;
    }

    public function updateEdcInfo($requestId, $apiResponse, $edcType, $cancelFlag = false) {
        $connection = $this->resource->getConnection();
        $edcPaymentTable = $this->resource->getTableName('ah_supermax_pos_payment_' . $edcType);
        $userId = $this->supermaxSession->getPosUserId();
        $outletId = null;
        $userData = $this->joinUserOutletData($userId);
        if(!empty($userData)){
            $outletId = $userData['pos_outlet_id'];
        }

        $paymentData = $connection->query("SELECT * FROM $edcPaymentTable WHERE request_id='" . $requestId . "'")->fetch();

        if($cancelFlag) {
            if(!empty($paymentData)) {
                $connection->query("UPDATE $edcPaymentTable SET cancel_info = '" . $apiResponse . "', pos_user_id = '" . (int)$userId . "', pos_outlet_id = '" . (int)$outletId . "', date_modified = NOW() WHERE request_id = '" . $requestId . "'");
            } else {
                $connection->query("INSERT INTO $edcPaymentTable SET request_id = '" . $requestId . "', cancel_info = '" . $apiResponse . "', pos_user_id = '" . (int)$userId . "', pos_outlet_id = '" . (int)$outletId . "', date_added = NOW(), date_modified = NOW()");
            }
        } else {
            if(!empty($paymentData)) {
                $connection->query("UPDATE $edcPaymentTable SET status_check_info = '" . $apiResponse . "', pos_user_id = '" . (int)$userId . "', pos_outlet_id = '" . (int)$outletId . "', date_modified = NOW() WHERE request_id = '" . $requestId . "'");
            } else {
                $connection->query("INSERT INTO $edcPaymentTable SET request_id = '" . $requestId . "', status_check_info = '" . $apiResponse . "', pos_user_id = '" . (int)$userId . "', pos_outlet_id = '" . (int)$outletId . "', date_added = NOW(), date_modified = NOW()");
            }
        }
    }

    public function getCustomerCustomEmail($customerId){
        $customerEmail = '';
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName('customer_entity');
        $customerData = $connection->query("SELECT * FROM $customerTable WHERE entity_id='" . (int)$customerId . "'")->fetch();
        if(!empty($customerData)){
            $customerMobile = trim($customerData['mobilenumber'], "+");
            $customerEmail = $customerMobile . '@' . $customerMobile . '.com';
        }
        return $customerEmail;
    }

    public function getIboPaymentOptions() {
        $data = array(
            "PAY-ON-DELIVERY" => array(
                "method" => "PAY-ON-DELIVERY",
                "code" => "PAY-ON-DELIVERY",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pod_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pod_payment_option_id', null)
            ),
            "CASH" => array(
                "method" => "CASH",
                "code" => "CASH",
                "psp_id" =>$this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ibo_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_cash_payment_option_id', null)
            ),
            "OFFLINE" => array(
                "method" => "OFFLINE",
                "code" => "OFFLINE",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ibo_offline_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_offline_payment_option_id', null)
            ),
            "WALLET" => array(
                "method" => "WALLET",
                "code" => "WALLET",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ibo_wallet_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_wallet_payment_option_id', null)
            ),
            "BANK-DEPOSIT" => array(
                "method" => "BANK-DEPOSIT",
                "code" => "BANK-DEPOSIT",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ibo_offline_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_bank_deposit_payment_option_id', null)
            ),
            "SARALOAN" => array(
                "method" => "SARALOAN",
                "code" => "SARALOAN",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_sara_loan_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_sara_loan_payment_option_id', null)
            ),
            "BHARATPE" => array(
                "method" => "BHARATPE",
                "code" => "BHARATPE",
                "psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_bharat_pe_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_bharat_pe_payment_option_id', null)
            ),
            "CARD" => array(
                "method" => "CARD",
                "code" => "CARD",
                "pinelabs_icici_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_icici_psp_id', null),
                "pinelabs_axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_card_payment_option_id', null)
            ),
            "EMI" => array(
                "method" => "EMI",
                "code" => "EMI",
                "pinelabs_icici_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_icici_psp_id', null),
                "pinelabs_axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_emi_payment_option_id', null)
            ),
            "EZETAP-EMI" => array(
                "method" => "EMI",
                "code" => "EMI",
                "hdfc_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_hdfc_psp_id', null),
                "axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_emi_payment_option_id', null)
            ),
            "PINELABS-UPI" => array(
                "method" => "UPI",
                "code" => "UPI",
                "pinelabs_icici_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_icici_psp_id', null),
                "pinelabs_axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_pinelabs_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_upi_payment_option_id', null)
            ),
            "UPI" => array(
                "method" => "UPI",
                "code" => "UPI",
                "hdfc_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_hdfc_psp_id', null),
                "axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_upi_payment_option_id', null)
            ),
            "CREDIT-CARD" => array(
                "method" => "CREDIT-CARD",
                "code" => "CREDIT-CARD",
                "hdfc_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_hdfc_psp_id', null),
                "axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_credit_card_payment_option_id', null)
            ),
            "DEBIT-CARD" => array(
                "method" => "DEBIT-CARD",
                "code" => "DEBIT-CARD",
                "hdfc_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_hdfc_psp_id', null),
                "axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_debit_card_payment_option_id', null)
            ),
            "NET-BANKING" => array(
                "method" => "NET-BANKING",
                "code" => "NET-BANKING",
                "hdfc_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_hdfc_psp_id', null),
                "axis_psp_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_ezetap_axis_psp_id', null),
                "payment_option_id" => $this->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_net_banking_payment_option_id', null)
            )
        );

        return $data;
    }

    public function deactivateQuote($quoteId) {
        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('quote');
        $quoteItemData = $connection->query("UPDATE $quoteTable SET is_active = 0 WHERE entity_id='" . $quoteId . "'");
    }

    public function getEdcData($userId, $terminal_id, $edcType) {
        $result = array();
        $connection = $this->resource->getConnection();
        $userHistoryTable = $this->resource->getTableName('ah_supermax_pos_user_login_history');
        $terminalTable = $this->resource->getTableName('ah_supermax_pos_terminals');
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $edcData = $connection->query("SELECT pt.ezetap_app_key, pt.pinelabs_merchant_pos_code, pt.ezetap_username, pt.ezetap_device_id, pt.pinelabs_device_id, pt.pinelabs_allowed_mops FROM $userHistoryTable as uh LEFT JOIN $terminalTable as pt ON(uh.pos_terminal_id = pt.pos_terminal_id) LEFT JOIN $outletTable ou ON(ou.pos_outlet_id = pt.pos_outlet_id) WHERE uh.pos_user_id = $userId AND uh.pos_terminal_id = $terminal_id AND uh.status = 1 ")->fetch();

        if(!empty($edcData)) {
            if($edcType == "ezetap") {
                $result = array(
                    "username" => $edcData['ezetap_username'],
                    "appKey" => $edcData['ezetap_app_key'],
                    "pushTo" => array('deviceId' => $edcData['ezetap_device_id']),
                );
            } elseif($edcType == "pinelabs") {
                $result = array(
                    "AllowedPaymentMode" => $edcData['pinelabs_allowed_mops'],
                    "MerchantStorePosCode" => $edcData['pinelabs_merchant_pos_code'],
                    "MerchantID" => (int)$this->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_pinelabs_marchant_id", null),
                    "SecurityToken" => $this->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_pinelabs_security_token", null),
                    "IMEI" => $edcData['pinelabs_device_id'],
                    "AutoCancelDurationInMinutes" => 5
                );
            }
        }

        return $result;
    }

    public function revokeCustomerToken($customerId, $customerToken) {
        $connection = $this->resource->getConnection();
        $oauthTokenTable = $this->resource->getTableName('oauth_token');
        $connection->query("UPDATE $oauthTokenTable SET revoked = 1 WHERE customer_id = $customerId AND token = '$customerToken'");
    }

    public function isAccessTokenValid($accessToken) {
        $result = array();
        $connection = $this->resource->getConnection();
        $supermaxApiTable = $this->resource->getTableName('ah_supermax_pos_api');
        $result = $connection->query("SELECT * FROM $supermaxApiTable WHERE token = '$accessToken' AND expire > NOW()")->fetch();
        return $result;
    }

    public function addLogData($data) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ah_supermax_oms_log.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
        $logger->info($data);
    }

    public function addDebuggingLogData($data) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ah_supermax_debugger.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($data);
    }

    public function assignedOutletIds(){        
        $user = $this->authSession->getUser(); 
        $assignedOutletIds = 0;
        if(!empty($user)) {
            $outletID = $user->getPosStoreRole();
            $assignedOutletIds = !isset($outletID) ? -1 : (($outletID != 0) ? [$outletID] : 0);
        }
        // $assignedOutletIds = [1];
        return $assignedOutletIds;
    }
    
    public function orderDevice() {
        $deviceType = 'pos';
        $useragent = $_SERVER['HTTP_USER_AGENT'];
        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {
            $deviceType = 'mpos';
        }
        return $deviceType;
    }

    public function getOrderDuplicateItems($orderId){
        $connection = $this->resource->getConnection();
        $salesOrderItemTable = $this->resource->getTableName('sales_order_item');
        $result = $connection->query("SELECT * FROM $salesOrderItemTable WHERE order_id = $orderId GROUP BY sku, product_id
        HAVING COUNT(*) > 1")->fetchAll();

        return $result;
    }

    public function deleteDuplicateOrderItems($orderId) {
        $connection = $this->resource->getConnection();
        $salesOrderTable = $this->resource->getTableName('sales_order');
		$salesOrderItemTable = $this->resource->getTableName('sales_order_item');
        $result = $connection->query("DELETE sot1 FROM $salesOrderItemTable sot1, $salesOrderItemTable sot2 WHERE sot1.item_id > sot2.item_id AND sot1.sku = sot2.sku AND sot1.product_id = sot2. product_id AND sot1.order_id = $orderId AND sot2.order_id = $orderId");

        $connection->query("UPDATE $salesOrderTable SET discount_amount=-(SELECT SUM($salesOrderItemTable.discount_amount) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), base_discount_amount=-(SELECT SUM($salesOrderItemTable.base_discount_amount) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), subtotal=(SELECT SUM($salesOrderItemTable.row_total) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), base_subtotal=(SELECT SUM($salesOrderItemTable.base_row_total) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), subtotal_incl_tax=(SELECT SUM($salesOrderItemTable.row_total_incl_tax) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), base_subtotal_incl_tax=(SELECT SUM($salesOrderItemTable.base_row_total_incl_tax) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), tax_amount=(SELECT SUM($salesOrderItemTable.tax_amount) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), base_tax_amount=(SELECT SUM($salesOrderItemTable.base_tax_amount) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), total_qty_ordered=(SELECT SUM($salesOrderItemTable.qty_ordered) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), base_total_qty_ordered=(SELECT SUM($salesOrderItemTable.qty_ordered) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId), total_item_count=(SELECT COUNT(*) FROM $salesOrderItemTable WHERE $salesOrderItemTable.order_id = $orderId) WHERE entity_id = $orderId");
	}

    public function isOrderTotalCorrect($orderId) {
        $flag = false;
        $connection = $this->resource->getConnection();
        $salesOrderTable = $this->resource->getTableName('sales_order');
		$salesOrderItemTable = $this->resource->getTableName('sales_order_item');
        $result = $connection->query("SELECT SUM(sot1.discount_amount) AS item_discount_amount, SUM(sot1.row_total_incl_tax) AS item_row_total_incl_tax FROM $salesOrderItemTable sot1, $salesOrderItemTable sot2 WHERE sot1.item_id > sot2.item_id AND sot1.sku = sot2.sku AND sot1.product_id = sot2. product_id AND sot1.order_id = $orderId AND sot2.order_id = $orderId")->fetch();

        if(!empty($result)) {
            $orderData = $connection->query("SELECT base_grand_total, base_shipping_amount FROM $salesOrderTable WHERE entity_id = $orderId")->fetch();
            if(!empty($orderData)) {
                $actualPaidAmount = $orderData['base_grand_total'];
                $calculatedTotalAmount = $result['item_row_total_incl_tax'] - $result['item_discount_amount'] + $orderData['base_shipping_amount'];
                if($actualPaidAmount == $calculatedTotalAmount) {
                    $flag = true;
                }
            }
        }

        return $flag;
    }

    public function getAllPosHeaders() {
        $headers = [];
        if (session_status() == PHP_SESSION_NONE) {    
            session_start();
        }
        // Check headers for authentication
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }

        return $headers;
    }

    public function getCashierToken() {
        $accessToken = "";
        $headers = $this->getAllPosHeaders();
        if ($headers && (isset($headers['access-token']) || isset($headers['Access-Token']))) {
            $accessToken = (isset($headers['access-token']) ? $headers['access-token'] : $headers['Access-Token']);
        }
        return $accessToken;
    }
}