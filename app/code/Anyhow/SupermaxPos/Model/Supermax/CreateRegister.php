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

class CreateRegister implements \Anyhow\SupermaxPos\Api\Supermax\CreateRegisterInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Store\Model\StoreManagerInterface $storeManager
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
    public function createRegister()
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
                    // Store Data
                    $storeData = $this->_storeManager->getStore($storeId);
                    if(!empty($storeData)) {
                        $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                        $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                    }
                    $registerName = $params['registerName'];
                    $title = $params['title'];
                    $floatAmount = (float)$this->helper->convert($params['floatAmount'], $storeCurrencyCode, $storeBaseCurrencyCode);
                    $note = $params['note'];
                    //$currencyCode = $params['currency'];
                    $date = $this->timezone->date(new \DateTime())->format('Y-m-d h:i:s');
                    
                    $supermaxRegisterTable = $this->resource->getTableName('ah_supermax_pos_register');
                    $supermaxRegisterTransactionTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
                    $supermaxRegisterTransactionDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
                    
                    $connection->insert($supermaxRegisterTable,
                        ['name' => $registerName, 'pos_user_id' => (int)$userId, 'status' => 1, 'close_note' => $note, 'date_open' => $date ]); 
                    
                    $posRegisterId = $connection->lastInsertId();
                    $connection->insert($supermaxRegisterTransactionTable,
                        ['pos_register_id' => $posRegisterId, 'code' => 'cash']);
                    
                    $posRegisterTransactionId = $connection->lastInsertId();
                    $connection->insert($supermaxRegisterTransactionDetailTable,
                        ['pos_register_transaction_id' => (int)$posRegisterTransactionId, 'pos_register_id' => (int)$posRegisterId, 'code' => 'cash', 'title' => $title, 'description' => $note, 'amount' => $floatAmount, 'date_added' => $date ]);
                    
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