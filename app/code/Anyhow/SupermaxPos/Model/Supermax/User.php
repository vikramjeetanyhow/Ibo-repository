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

class User implements \Anyhow\SupermaxPos\Api\Supermax\UserInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Country $country,
        \Magento\Directory\Model\Region $regionFactory,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Module\Manager $moduleManager
    ){
        $this->resource = $resourceConnection;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->country = $country;
        $this->regionFactory = $regionFactory;
        $this->supermaxSession = $supermaxSession;
        $this->moduleManager = $moduleManager;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function getUser()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $storeName = '';
                $storeCurrencyCode = '';
                $storeLanguageCode = '';
                $headerLogoImage = '';
                $receiptTitle = '';
                $receiptHeaderDetails = '';
                $receiptFooterDetails = '';
                $receiptData =[];
                $connection= $this->resource->getConnection();
                $userId = $this->supermaxSession->getPosUserId();
                $restroModuleStatus = $this->moduleManager->isEnabled('Anyhow_SupermaxPosRestro');
                $userData = $this->joinUserData($connection, $userId, $restroModuleStatus);
                $userResult = array();
                $userOutlet = array();
                $userStoreView = array();
                $outletAddress = array();
                $salesAssociates = array();
                if(!empty($userData)) {
                    $user = $connection->query($userData)->fetch();
                    $userResult = array(
                        'user_id' => (int)$user['pos_user_id'], 
                        'user_name' => html_entity_decode($user['userfirstname'].' '.$user['userlastname']),
                        'employee_id' => $user['username'] ? $user['username'] : '',
                        'access_permission' => (!empty($user['access_permission']) && $user['role_status']) ? explode(',' , $user['access_permission']) : array(),
                        'password_reset_date' => $user['password_reset_date'] ? $user['password_reset_date'] : ''
                    );

                    $salesAssociates = $this->getAllSalesAssociates($user['pos_outlet_id']);
                    
                    if(!empty($user['header_logo'])){
                        $headerLogoImage = $this->storeManager->getStore()->getBaseUrl(
                            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                        ).$user['header_logo_path'];
                    }

                    $receiptId = $user['pos_receipt_id'];
                    $storeId = $user['store_view_id'];
                    $connection = $this->resource->getConnection();
                    $receiptStoreTable = $this->resource->getTableName('ah_supermax_pos_receipt_store');
                    $receiptStoreData = $connection->query("SELECT * FROM $receiptStoreTable WHERE receipt_id = $receiptId AND store_id = $storeId")->fetchAll();
                   
                    if(!empty($receiptStoreData)){
                        $receiptTitle = $receiptStoreData[0]['title'];
                        $receiptHeaderDetails = $receiptStoreData[0]['header_details'];
                        $receiptFooterDetails = $receiptStoreData[0]['footer_details'];
                        $receiptBankDetails = $receiptStoreData[0]['seller_bank_info'];
                        $receiptdisclaimer = $receiptStoreData[0]['disclaimer'];
                    } else {
                        $receiptAllStoreData = $connection->query("SELECT * FROM $receiptStoreTable WHERE receipt_id = $receiptId AND store_id = 0")->fetchAll();
                        if(!empty($receiptAllStoreData)){
                            $receiptTitle = $receiptAllStoreData[0]['title'];
                            $receiptHeaderDetails = $receiptAllStoreData[0]['header_details'];
                            $receiptFooterDetails = $receiptAllStoreData[0]['footer_details'];
                            $receiptBankDetails = $receiptAllStoreData[0]['seller_bank_info'];
                            $receiptdisclaimer = $receiptAllStoreData[0]['disclaimer'];
                        }
                    }
                    $receiptData = array(
                        'receipt_id' => (int)$user['pos_receipt_id'],
                        'receipt_title' => html_entity_decode($receiptTitle),
                        'header_logo_image' => html_entity_decode($headerLogoImage),
                        'receipt_width' => (int)$user['width'],
                        'receipt_barcode_width' => (int)$user['barcode_width'],
                        'receipt_font_size' => (int)$user['font_size'],
                        'receipt_header_deatils' => html_entity_decode($receiptHeaderDetails),
                        'receipt_footer_details' => html_entity_decode($receiptFooterDetails),
                        'receipt_bank_details' => html_entity_decode($receiptBankDetails),
                        'receipt_disclaimer' => html_entity_decode($receiptdisclaimer)
                    );

                    // Token receipt data
                    $tokenHeaderLogoImage = '';
                    $tokenReceiptData = array();
                    
                    if($restroModuleStatus){
                        if(isset($user['pos_restro_receipt_id'])){
                            $tokenReceiptId = $user['pos_restro_receipt_id'];
                            if(!empty($user['token_header_logo_path'])){
                                $tokenHeaderLogoImage = $this->storeManager->getStore()->getBaseUrl(
                                    \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                                ).$user['token_header_logo_path'];
                            }

                            $tokenReceiptStoreTable = $this->resource->getTableName('ah_supermax_pos_restro_receipt_store');
                            $tokenReceiptStoreData = $connection->query("SELECT * FROM $tokenReceiptStoreTable WHERE restro_receipt_id = $tokenReceiptId AND store_id = $storeId")->fetchAll();

                            if(!empty($tokenReceiptStoreData)){
                                $tokenReceiptTitle = $tokenReceiptStoreData[0]['title'];
                                $tokenReceiptHeaderDetails = $tokenReceiptStoreData[0]['header_details'];
                                $tokenReceiptFooterDetails = $tokenReceiptStoreData[0]['footer_details'];
                            } else {
                                $tokenReceiptAllStoreData = $connection->query("SELECT * FROM $tokenReceiptStoreTable WHERE restro_receipt_id = $tokenReceiptId AND store_id = 0")->fetchAll();
                                if(!empty($tokenReceiptAllStoreData)){
                                    $tokenReceiptTitle = $tokenReceiptAllStoreData[0]['title'];
                                    $tokenReceiptHeaderDetails = $tokenReceiptAllStoreData[0]['header_details'];
                                    $tokenReceiptFooterDetails = $tokenReceiptAllStoreData[0]['footer_details'];
                                }
                            }
                            
                            $tokenReceiptData = array(
                                'pos_restro_token_receipt_id' => (int)$user['pos_restro_receipt_id'],
                                'pos_restro_token_receipt_title' => html_entity_decode($tokenReceiptTitle),
                                'pos_restro_token_receipt_header_logo_image' => html_entity_decode($tokenHeaderLogoImage),
                                'pos_restro_token_receipt_width' => (int)$user['token_width'],
                                'pos_restro_token_receipt_fontsize' => (int)$user['token_font_size'],
                                'pos_restro_token_receipt_header_details' => html_entity_decode($tokenReceiptHeaderDetails),
                                'pos_restro_token_receipt_footer_details' => html_entity_decode($tokenReceiptFooterDetails)
                            );
                        }
                    }


                    $userStoreViewId = $user['store_view_id'];
                    if($user['outlet_address_type'] == 1){
                        $outletAddress = array(
                            'address_id' => 0,
                            'firstname' => html_entity_decode($user['firstname']),
                            'lastname' => html_entity_decode($user['lastname']),
                            'company' => html_entity_decode($user['company']),
                            'street' => html_entity_decode($user['street']),
                            'city' => html_entity_decode($user['city']),
                            'region_id' => (int)$user['region_id'],
                            'region_name' => html_entity_decode($user['region']),
                            'country_id' => html_entity_decode($user['country_id']),
                            'country_name' => html_entity_decode($this->country->load($user['country_id'])->getName()),
                            'postcode' => html_entity_decode($user['postcode']),
                            'telephone' => html_entity_decode($user['telephone']),
                            'pan_no' => html_entity_decode($user['pan_no']),
                            'gstin' => html_entity_decode($user['gstin']),
                        );
                    } else {
                        $regionId = $this->helper->getConfig('general/store_information/region_id', $userStoreViewId);
                        $countryId = $this->helper->getConfig('general/store_information/country_id', $userStoreViewId);
                        $regionName = $this->regionFactory->load($regionId)->getName();
                        $outletAddress = array(
                            'address_id' => 0,
                            'firstname' => html_entity_decode($this->helper->getConfig('general/store_information/name', $userStoreViewId)),
                            'lastname' => '',
                            'company' => '',
                            'street' => html_entity_decode($this->helper->getConfig('general/store_information/street_line1', $userStoreViewId)),
                            'city' => html_entity_decode($this->helper->getConfig('general/store_information/city', $userStoreViewId)),
                            'region_id' => (int)$regionId,
                            'region_name' => html_entity_decode($regionName),
                            'country_id' => html_entity_decode($countryId),
                            'country_name' => html_entity_decode($this->country->load($countryId)->getName()),
                            'postcode' => html_entity_decode($this->helper->getConfig('general/store_information/postcode', $userStoreViewId)),
                            'telephone' => html_entity_decode($this->helper->getConfig('general/store_information/phone', $userStoreViewId)),
                            'pan_no' => html_entity_decode($this->helper->getConfig('general/store_information/pan_no', $userStoreViewId)),
                            'gstin' => html_entity_decode($this->helper->getConfig('general/store_information/gstin', $userStoreViewId))
                        );
                    }
                    $userOutlet = array(
                        'outlet_id' => (int)$user['pos_outlet_id'], 
                        'outlet_name' => html_entity_decode($user['outlet_name']),
                        'outlet_store_id' => html_entity_decode($user['outlet_store_id']),
                        'allowed_ips' => html_entity_decode($user['allowed_ips']),
                        'inventory_node' => html_entity_decode($user['inventory_node']),
                        'store_wh_node' => html_entity_decode($user['store_wh_node']),
                        'outlet_address' => $outletAddress,
                        'receipt_data' => $receiptData,
                        'receipt_thermal_status' => (int)$user['receipt_thermal_status'],
                        'online_payment_popup_status' => (int)$user['online_payment_popup_status'],
                        'display_payments' => (!empty($user['display_payments'])) ? explode(',' , $user['display_payments']) : array(),
                        'multi_lot_status' => (int)$user['multi_lot_status'],
                        // 'multi_lot_info_api_url' => $user['multi_lot_info_api_url'],                        
                                               
                    );
                    if($restroModuleStatus){
                        $userOutlet['token_receipt_data'] = $tokenReceiptData;
                    }

                    if(!empty($userStoreViewId)) {
                        $storeData = $this->storeManager->getStore($userStoreViewId);

                        if(!empty($storeData)) {
                            $storeName = html_entity_decode($storeData->getName());
                            $storeCurrencyCode = html_entity_decode($storeData->getCurrentCurrencyCode());
                        }

                        $storeLanguageCode = html_entity_decode($this->helper->getConfig('general/locale/code', $userStoreViewId));
                    }
                    
                    $userStoreView =array(
                        'store_view_id' => (int)$userStoreViewId, 
                        'language_code' => $storeLanguageCode,
                        'currency_code' => $storeCurrencyCode,
                        'store_view_title' => $storeName
                    );
                } 
                $result = array(  
                    'user' => $userResult, 
                    'outlet' => $userOutlet, 
                    'store_view' => $userStoreView,
                    'sales_associates' => $salesAssociates
                );
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }

    // To get data from user and outlet tables.
    public function joinUserData($connection, $userId, $restroModuleStatus)
    {
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'username', 'pos_outlet_id', 'store_view_id', 'userfirstname'=>'firstname', 'userlastname'=>'lastname', 'password_reset_date']
        )->joinLeft(
            ['ur' => $this->resource->getTableName('ah_supermax_pos_user_role')],
            "ur.pos_user_role_id = spu.pos_user_role_id",
            ['access_permission', 'role_status'=>'status']
        )->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['outlet_name', 'outlet_address_type', 'allowed_ips', 'inventory_node', 'store_wh_node', 'outlet_store_id' => 'store_id','receipt_thermal_status', 'online_payment_popup_status' ,'display_payments', 'multi_lot_status' ]
        )->joinLeft(
            ['spoa' => $this->resource->getTableName('ah_supermax_pos_outlet_address')],
            "spu.pos_outlet_id = spoa.parent_outlet_id"
        )->joinLeft(
            ['spor' => $this->resource->getTableName('ah_supermax_pos_receipt')],
            "spo.pos_receipt_id = spor.pos_receipt_id"
        );
        
        if($restroModuleStatus){
            $select->joinLeft(
                ['sprr' => $this->resource->getTableName('ah_supermax_pos_restro_receipt')],
                "spo.pos_restro_receipt_id = sprr.pos_restro_receipt_id",
                ['pos_restro_receipt_id', 'token_header_logo_path'=> 'sprr.header_logo_path', 'token_width' => 'sprr.width', 'token_font_size' => 'sprr.font_size']
            );
        }
        
        $select->where("spu.pos_user_id = $userId");
        
        return $select;
    }

    private function getAllSalesAssociates($storeId) {
        $connection = $this->resource->getConnection();
        $posUserTable = $this->resource->getTableName('ah_supermax_pos_user');
        $posUserRoleTable = $this->resource->getTableName("ah_supermax_pos_user_role");
        $salesAssociateData = $connection->query("SELECT pos_user_id AS pos_sales_associate_id, CONCAT(pu.firstname, ' ', pu.lastname) AS name, pu.username FROM $posUserTable AS pu LEFT JOIN $posUserRoleTable AS pur ON(pu.pos_user_role_id = pur.pos_user_role_id) WHERE pu.status=1 AND pur.status=1 AND pu.pos_outlet_id = $storeId AND FIND_IN_SET('no_access', pur.access_permission) > 0")->fetchAll();
        return $salesAssociateData;
    }
}