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

class Configuration implements \Anyhow\SupermaxPos\Api\Supermax\ConfigurationInterface
{
    protected $helper;
    public function __construct( 
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper; 
        $this->resource = $resourceConnection;
        $this->groupRepository = $groupRepository;
        $this->supermaxSession = $supermaxSession;
      
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function configuration()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders(); 

            if($tokenFlag) {
                $connection= $this->resource->getConnection();
                $userId = $this->supermaxSession->getPosUserId();
                $storeId = null;
                $pos_register_status = false;
                $pos_sales_report_status = false;

                if(!empty($userId)){
                    $userTable = $this->resource->getTableName('ah_supermax_pos_user');
                    $userRoleTable = $this->resource->getTableName('ah_supermax_pos_user_role');
                    $userDatas = $connection->query("SELECT u.*, ur.access_permission, ur.status AS role_status FROM $userTable AS u LEFT JOIN $userRoleTable AS ur ON(u.pos_user_role_id = ur.pos_user_role_id) WHERE u.pos_user_id = $userId");
                    
                    if(!empty($userDatas)){
                        foreach($userDatas as $userData){
                            $storeId = $userData['store_view_id'];
                            
                            if($userData['access_permission'] && $userData['role_status']){
                                if(in_array('register_and_cash_mgmt', explode(',' , $userData['access_permission']))){
                                    $pos_register_status = true;
                                }

                                if(in_array('dashboard', explode(',' , $userData['access_permission']))){
                                    $pos_sales_report_status = true;
                                }
                            }
                        }
                    }
                    $posStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status', $storeId);
                    // $showImage = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_product_image_enable_disable', $storeId);
                    $cashPaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_cash_payment_order_status', $storeId);
                    $onlinePaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_online_payment_order_status', $storeId);
                    $offinePaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_offline_payment_order_status', $storeId);
                    $splitPaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_split_payment_order_status', $storeId);
                    $bankDepositePaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_bank_deposite_payment_order_status', $storeId);
                    $payLaterPaymentOrderStatus = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_pay_later_payment_order_status', $storeId);
                    $productsVisible  = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_product_configutaion/ah_supermax_pos_no_of_products_visible', $storeId);
                    $popularProductsVisible = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_product_configutaion/ah_supermax_pos_no_of_visible_popular_products', $storeId);
                    if(empty($productsVisible)){
                        $productsVisible = 50;
                    }
                    if(empty($popularProductsVisible)){
                        $popularProductsVisible = 20;
                    }
                    $squarePaymentStatus = $this->helper->getConfig('ah_supermax_pos_sq_payment_configuration/ah_supermax_pos_sq_payment_basic_configutaion/ah_supermax_pos_status', $storeId);

                    $tenderTypes = $this->helper->getConfig('ah_supermax_pos_sq_payment_configuration/ah_supermax_pos_sq_payment_basic_configutaion/ah_supermax_pos_tender_types', $storeId);
                    $squarePaymentConfig = array(
                        "application_id"    => html_entity_decode($this->helper->getConfig('ah_supermax_pos_sq_payment_configuration/ah_supermax_pos_sq_payment_basic_configutaion/ah_supermax_pos_application_id', $storeId)),
                        "callback_url"      => html_entity_decode($this->helper->getConfig('ah_supermax_pos_sq_payment_configuration/ah_supermax_pos_sq_payment_basic_configutaion/ah_supermax_pos_callback_url', $storeId)),
                        "tender_types"      => explode(',', $tenderTypes)
                    );

                    $supermaxPosSyncStatus = $this->helper->getConfig('ah_supermax_pos_sync_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status', $storeId);
                    
                    $supermaxPosCfdStatus = $this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status', $storeId);

                    $restroStatus = $this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_status');
                    $restroConfig = array(
                        "pos_restro_default_kot_type_id" => (int)$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_type'),
                        "pos_restro_default_kot_priority_id" => (int)$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_priority'),
                        "pos_restro_default_kot_status_id" => (int)$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_status'),
                        "pos_restro_default_kot_product_status_id" => (int)$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_product_status'),
                        "pos_restro_kot_link_kot_type" => explode(',',$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_link_type')),
                        "pos_restro_kot_link_kot_status" => explode(',',$this->helper->getConfig('ah_supermax_pos_restro_configuration/ah_supermax_restro_basic_configutaion/ah_supermax_restro_kot_bot_link_status'))
                    );

                    $rmaStatus = $this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_status');
                    $rmaConfig = array(
                        "pos_rma_default_resolution_period" => (int)$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_default_resolution_period'),
                        "pos_rma_allow_upload_file_ext" => explode(',' ,$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_custom_field_configutaion/ah_supermax_pos_rma_upload_file_ext')),
                        "pos_rma_upload_file_max_size" => (int)$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_custom_field_configutaion/ah_supermax_pos_rma_upload_file_max_size'),
                        "pos_rma_default_return_status" => (int)$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_default_return_status'),
                        "pos_rma_return_request_complete_status" => (int)$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_return_request_complete_status'),
                        "pos_rma_allow_product_type" => explode(',' , $this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_allow_product_type')),
                        "pos_rma_allow_order_status" => explode(',' , $this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_allow_order_status'))
                    );

                    $email_time_interval = (int)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_email_time_interval', $storeId);

                    $paymentConfig = array(
                        "pos_payment_intent_api" => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_payment_engine/ah_supermax_pos_create_payment_intent_api', null),
                        "pos_payment_options" => $this->helper->getIboPaymentOptions()
                    );

                    $result = array(
                        'pos_status' => (bool)$posStatus,
                        // 'show_image' => (bool)$showImage,
                        'pos_customer_group' => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_customer_group_enable_disable', $storeId),
                        'pos_invoice_buttons_timer' => (int)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_invoice_buttons_timer', $storeId),
                        'display_full_tax_summary' => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_show_order_tax_summary', $storeId),
                        // 'multi_lot_status' => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_multi_lot_configutaion/ah_supermax_pos_mulit_lot_status', $storeId),
                        'multi_mrp_info_api_url' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_multi_lot_configutaion/ah_supermax_pos_multi_mrp_info_api_url', $storeId),
                        'multi_lot_info_api_url' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_multi_lot_configutaion/ah_supermax_pos_multi_lot_info_api_url', $storeId),
                        'cash_payment_order_status' => html_entity_decode($cashPaymentOrderStatus),
                        'offline_payment_order_status' => html_entity_decode($offinePaymentOrderStatus),
                        'online_payment_order_status' => html_entity_decode($onlinePaymentOrderStatus),
                        'split_payment_order_status' => html_entity_decode($splitPaymentOrderStatus),
                        'bank_deposite_payment_order_status' => html_entity_decode($bankDepositePaymentOrderStatus),
                        'pay_later_payment_order_status' => html_entity_decode($payLaterPaymentOrderStatus),
                        "pos_mail_order_receipt_status" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_order_receipt_email_status', $storeId),
                        'products_visible' => (int)$productsVisible,
                        'popular_products_visible' => (int)$popularProductsVisible,
                        'pos_square_payment_status' => (bool)$squarePaymentStatus,
                        'pos_square_payment_config' => $squarePaymentConfig,
                        'pos_sync_status' => (bool)$supermaxPosSyncStatus,
                        'pos_cds_status' => (bool)$supermaxPosCfdStatus,
                        'pos_cds_enable_customer_feedback' => (bool)$this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_customer_feedback_configutaion/ah_supermax_pos_cds_enable_customer_feedback'),
                        'pos_restro_status' => (bool)$restroStatus,
                        'restro_config' => $restroConfig,
                        "customer_group_not_logged_in_tax_class_id" => (int)$this->customerTaxId(0),
                        "pos_register_status" => (bool)$pos_register_status,
                        "pos_sales_report_status" => (bool)$pos_sales_report_status,
                        'product_price_include_tax' => (bool)$this->helper->getConfig('tax/calculation/price_includes_tax'),
                        "apply_customer_tax" => (bool)$this->helper->getConfig('tax/calculation/apply_after_discount'),
                        "apply_discount_on_price" => (bool)$this->helper->getConfig('tax/calculation/discount_tax'),
                        'display_subtotal' => (int)$this->helper->getConfig('tax/sales_display/subtotal', $storeId),
                        'display_cart_product_price_include_tax' => ($this->helper->getConfig('tax/cart_display/price', $storeId) == 2) ? true : false,
                        "pos_rma_status" => (bool)$rmaStatus,
                        "rma_config" => $rmaConfig,
                        'pos_email_time_interval' => $email_time_interval ? $email_time_interval : 1000,
                        "pos_ibo_invoice_base_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_invoice_base_url"),
                        // "display_cash_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_cash_payment_enable_disable', $storeId),
                        // "display_pod_payment" => false,
                        // "display_online_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_online_payment_enable_disable', $storeId),
                        // "display_offline_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_offline_payment_enable_disable', $storeId),
                        // "display_split_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_split_payment_enable_disable', $storeId),
                        // "display_bank_deposite_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_bank_deposite_payment_enable_disable', $storeId),
                        // "display_pay_later_payment" => (bool)$this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_pay_later_payment_enable_disable', $storeId),
                        'wallet_balance' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_wallet_api_engine/ah_supermax_pos_wallet_balance_api_url', $storeId),
                        'wallet_register' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_wallet_api_engine/ah_supermax_pos_wallet_register_api_url', $storeId),
                        'wallet_authenticate' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_wallet_api_engine/ah_supermax_pos_wallet_authenticate_api_url', $storeId),
                        'wallet_charge' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_wallet_api_engine/ah_supermax_pos_wallet_charge_api_url', $storeId),
                        'wallet_rollback' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_wallet_api_engine/ah_supermax_pos_wallet_rollback_api_url', $storeId),
                        'product_search_by_desc' => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_product_search_by_desc_api_url', $storeId),
                        "ibo_api_client_id" => $this->helper->getConfig("promise_engine/promise_engine_settings/promise_engine_client_id", $storeId),
                        "price_zone_api_url" => $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_price_zone_api_url', $storeId),
                        'default_price_zone' => $this->helper->getConfig('regional_pricing/setting/default_zone', $storeId),
                        'regional_pricing_status' => (bool)$this->helper->getConfig('regional_pricing/setting/active', $storeId),
                        "pinelabs_emi_min_amount" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_pinelabs_emi_min_amount", $storeId),
                        "ezetap_emi_min_amount" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_edc_api_engine/ah_supermax_pos_ezetap_emi_min_amount", $storeId),
                        "bff_product_search_api_status" => (bool)$this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_bff_product_search_api_status", $storeId),
                        "product_search_api_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_product_search_api_url", $storeId),
                        "update_fetch_quote_status" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_update_fetch_quote_status", $storeId),
                        "ibo_account_holder_name" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_ibo_account_holder_name", $storeId),
                        "hold_cart_status" => (bool)$this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_hold_cart_status", $storeId),
                        "ah_shipping_charge_brekup" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_ibo_shipping_charge_brekup", $storeId),
                        "ah_categories_condition" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_receipt_configutaion/ah_supermax_pos_categories_condition", $storeId),
                        "ah_terms_and_Conditions_one" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_receipt_configutaion/ah_supermax_pos_terms_and_Conditions_one", $storeId),
                        "ah_terms_and_Conditions_two" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_receipt_configutaion/ah_supermax_pos_terms_and_Conditions_two", $storeId),
                        "pos_payment_data" => $paymentConfig,
                        "customer_domain" => array(
                            "customer_register_api_url" => $this->helper->getConfig("ah_supermax_pos_customer_domain_configuration/ah_supermax_pos_customer_domain_configuration/ah_supermax_pos_customer_register_api_url", $storeId)
                        ),
                        "serviceability_api_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_check_serviceability_api_url", $storeId),
                        "pos_verify_gst_address_api_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_verify_gst_address_api_url", $storeId),
                        "pos_attach_gst_address_api_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_attach_gst_address_api_url", $storeId),
                        "pos_get_quotation_api_url" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_quotation/ah_supermax_pos_get_quotation_api_url", $storeId),
                        "pos_quotation_expire_time" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_quotation/ah_supermax_pos_quotation_expire_time", $storeId),
                        "pos_quotation_create_promise_api" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_quotation/ah_supermax_pos_quotation_create_promise_api", $storeId),
                        "pos_get_sales_associates_status" => (bool)$this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_sales_associate_configutaion/ah_supermax_pos_sales_associate_status", $storeId),
                        "pos_get_sales_associates_api" => $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_sales_associate_configutaion/ah_supermax_pos_get_sales_associates_api", $storeId),
                        "pos_cnc_delivery_mode_status" => (bool)$this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_cnc_delivery_mode_status", $storeId),
                        "pos_dwh_delivery_mode_status" => (bool)$this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_dwh_delivery_mode_status", $storeId)
                    );
                }

            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    // to get customer tax class.
    public function customerTaxId($groupId){
        $taxClassId = '';
        $group = $this->groupRepository->getById($groupId);
        if(!empty($group)){
            $taxClassId = $group->getTaxClassId();
        }
        return $taxClassId;
    }

}