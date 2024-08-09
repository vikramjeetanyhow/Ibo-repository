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

class Email implements \Anyhow\SupermaxPos\Api\Supermax\EmailInterface {

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Sales\Model\OrderRepository $orderRepository
    ){
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
        $this->resource = $resource;
        $this->remote = $remote;
        $this->httpHeader = $httpHeader;
        $this->storeManager = $storeManager;
        $this->timezone = $timezone;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

   /**
     * POST API
     * @api
     * 
     * @return string
     */
    public function email() {
        $result = array();
        $error = false;

        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(isset($params['type']) && !empty($params['type']) && isset($params['details']) && !empty($params['details'])) {
                    $postData = $params['details'];
                    $postData['type'] = $params['type'];
                    $postData['user_details'] = $this->getUserDetails();
                    $postData['store_url'] = $this->storeManager->getStore()->getBaseUrl();
                    $postData['user_ip'] = $this->remote->getRemoteAddress();
                    $postData['user_browser_details'] = $this->getBrowserDetails();
    
                    switch ($postData['type']) {
                        case "user_login":
                            $this->sendCashierLoginEmailToCashier($postData);
                            $this->sendCashierLoginEmailToAdmin($postData);
                        break;
                        case "user_signout":
                            $this->sendCashierSignOutEmailToCashier($postData);
                            $this->sendCashierSignOutEmailToAdmin($postData);
                        break;
                        case "terminal_reset":
                            $this->sendCashierTerminalResetEmailToCashier($postData);
                            $this->sendCashierTerminalResetEmailToAdmin($postData);
                        break;
                        case "custom_product":            
                            $this->sendAddCustomProductEmailToAdmin($postData);
                        break;
                        case "hold_cart":            
                            $this->sendHoldCartEmailToAdmin($postData);
                        break;
                        case "return_product":            
                            $this->sendOrderedProductReturnEmailToCustomer($postData);
                            $this->sendOrderedProductReturnEmailToAdmin($postData);
                        break;
                        case "customer_feedback":       
                            if($this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status', $postData['user_details']['store_view_id'])) {  
                                $this->sendCustomerFeedbackEmailToCustomer($postData);
                                $this->sendCustomerFeedbackEmailToAdmin($postData);
                            }
                        break;
                        default:      
                    }
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

    private function getUserDetails(){
        $userResult = array();
        $userId = $this->supermaxSession->getPosUserId();
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['spu' => $this->resource->getTableName('ah_supermax_pos_user')],
            ['pos_user_id', 'cashier_firstname'=>'firstname', 'cashier_lastname'=>'lastname', 'cashier_email'=>'email', 'cashier_telephone'=>'phone','store_view_id']
        )
        ->joinLeft(
            ['spo' => $this->resource->getTableName('ah_supermax_pos_outlet')],
            "spu.pos_outlet_id = spo.pos_outlet_id",
            ['pos_outlet_id', 'outlet_name', 'outlet_email'=>'email']
        )
        ->joinLeft(
            ['spoa' => $this->resource->getTableName('ah_supermax_pos_outlet_address')],
            "spu.pos_outlet_id = spoa.parent_outlet_id",
            ['outlet_telephone'=>'telephone']
        )
        ->where("spu.pos_user_id = $userId");
        
        $userData = $connection->query($select)->fetch();
        if(!empty($userData)){
            $userResult = $userData;
            $userResult['cashier_telephone'] = $userResult['cashier_telephone'] ? $userResult['cashier_telephone'] : $this->helper->getConfig('general/store_information/phone', $userResult['store_view_id']);
            $userResult['outlet_telephone'] = $userResult['outlet_telephone'] ? $userResult['outlet_telephone'] : $this->helper->getConfig('general/store_information/phone', $userResult['store_view_id']);
        }
        return $userResult;
    }

    private function getBrowserDetails() {

        $user_agent = $this->httpHeader->getHttpUserAgent();
        $browser_name = 'Unknown';
        $platform = 'Unknown';
        $version = "";
    
        if (preg_match('/linux/i', $user_agent)) {
            $platform = 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
            $platform = 'mac';
        } elseif (preg_match('/windows|win32/i', $user_agent)) {
            $platform = 'windows';
        }

        if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')){ 
            $browser_name = 'Opera';
            $browser_code = 'OPR';
        } elseif (strpos($user_agent, 'Edg')) { 
            $browser_name = 'Edge';
            $browser_code = 'Edg';
        } elseif (strpos($user_agent, 'Chrome')) { 
            $browser_name = 'Chrome';
            $browser_code = 'Chrome';
        } elseif (strpos($user_agent, 'Safari')) {
            $browser_name = 'Safari';
            $browser_code = 'Safari';
        } elseif (strpos($user_agent, 'Firefox')) {
            $browser_name = 'Firefox';
            $browser_code = 'Firefox';
        } elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) {
            $browser_name = 'Internet Explorer';
            $browser_code = strpos($user_agent, 'MSIE') ? 'MSIE' : 'Trident';
        } else {
            $browser_name = 'Other';
            $browser_code = 'Other';
        }

        $known = array('Version', $browser_code, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $user_agent, $matches)) {
        }
   
        $i = count($matches['browser']);
        if ($i != 1) {
            if (strripos($user_agent, "Version") < strripos($user_agent, $browser_code)){
                $version = $matches['version'][0];
            } else {
                $version = isset($matches['version'][1]) ? $matches['version'][1] : '?';
            }
        } else {
            $version = $matches['version'][0];
        }

        if ($version == null || $version == "") {
            $version = "?";
        }
        
        return array(
            'user_agent' => $user_agent,
            'name'      => $browser_name,
            'version'   => $version,
            'platform'  => $platform
        );
    }

    private function sendCashierLoginEmailToCashier($postData){
        $cashier_template_for_cashier_login = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_cashier_template_for_cashier_login', $postData['user_details']['store_view_id']);

        if($cashier_template_for_cashier_login){
            $email_data = array(
                'template_id' => (int)$cashier_template_for_cashier_login,
                'to_email' => $postData['user_details']['cashier_email'],
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCashierLoginEmailToAdmin($postData){
        $admin_template_for_cashier_login = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_admin_template_for_cashier_login', $postData['user_details']['store_view_id']);

        if($admin_template_for_cashier_login){
            $email_data = array(
                'template_id' => (int)$admin_template_for_cashier_login,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCashierSignOutEmailToCashier($postData){
        $cashier_template_for_cashier_logout = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_cashier_template_for_cashier_logout', $postData['user_details']['store_view_id']);

        if($cashier_template_for_cashier_logout){
            $email_data = array(
                'template_id' => (int)$cashier_template_for_cashier_logout,
                'to_email' => $postData['user_details']['cashier_email'],
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCashierSignOutEmailToAdmin($postData){
        $admin_template_for_cashier_logout = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_admin_template_for_cashier_logout', $postData['user_details']['store_view_id']);

        if($admin_template_for_cashier_logout){
            $email_data = array(
                'template_id' => (int)$admin_template_for_cashier_logout,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCashierTerminalResetEmailToCashier($postData){
        $cashier_template_for_terminal_reset = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_cashier_template_for_terminal_reset', $postData['user_details']['store_view_id']);

        if($cashier_template_for_terminal_reset){
            $email_data = array(
                'template_id' => (int)$cashier_template_for_terminal_reset,
                'to_email' => $postData['user_details']['cashier_email'],
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCashierTerminalResetEmailToAdmin($postData){
        $admin_template_for_terminal_reset = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_admin_template_for_terminal_reset', $postData['user_details']['store_view_id']);

        if($admin_template_for_terminal_reset){
            $email_data = array(
                'template_id' => (int)$admin_template_for_terminal_reset,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendAddCustomProductEmailToAdmin($postData){
        $admin_template_for_custom_product_add = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_admin_template_for_custom_product_add', $postData['user_details']['store_view_id']);

        if($admin_template_for_custom_product_add){
            $email_data = array(
                'template_id' => (int)$admin_template_for_custom_product_add,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendHoldCartEmailToAdmin($postData){
        $admin_template_for_hold_cart = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_email_configutaion/ah_supermax_pos_admin_template_for_hold_cart', $postData['user_details']['store_view_id']);

        if($admin_template_for_hold_cart){
            $email_data = array(
                'template_id' => (int)$admin_template_for_hold_cart,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendOrderedProductReturnEmailToCustomer($postData){
        $customer_template_for_custom_ordered_product_return = $this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_email_configutaion/ah_supermax_pos_customer_template_for_ordered_product_return', $postData['user_details']['store_view_id']);

        if($customer_template_for_custom_ordered_product_return){
            $email_data = array(
                'template_id' => (int)$customer_template_for_custom_ordered_product_return,
                'to_email' => $postData['email'],
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendOrderedProductReturnEmailToAdmin($postData){
        $admin_template_for_ordered_product_return = $this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_email_configutaion/ah_supermax_pos_admin_template_for_ordered_product_return', $postData['user_details']['store_view_id']);

        if($admin_template_for_ordered_product_return){
            $email_data = array(
                'template_id' => (int)$admin_template_for_ordered_product_return,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCustomerFeedbackEmailToCustomer($postData){
        $customer_template_for_customer_feedback = $this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_customer_feedback_configutaion/ah_supermax_pos_customer_template_for_customer_feedback', $postData['user_details']['store_view_id']);

        if($customer_template_for_customer_feedback){
            $template_vars = $this->getTemplateVars($postData);
            $email_data = array(
                'template_id' => (int)$customer_template_for_customer_feedback,
                'to_email' => $template_vars['customer_email'],
                'template_vars' => $template_vars,
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendCustomerFeedbackEmailToAdmin($postData){
        $admin_template_for_customer_feedback = $this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_customer_feedback_configutaion/ah_supermax_pos_admin_template_for_customer_feedback', $postData['user_details']['store_view_id']);

        if($admin_template_for_customer_feedback){
            $email_data = array(
                'template_id' => (int)$admin_template_for_customer_feedback,
                'to_email' => $this->helper->getConfig('trans_email/ident_general/email', $postData['user_details']['store_view_id']),
                'template_vars' => $this->getTemplateVars($postData),
                'store_id' => (int)$postData['user_details']['store_view_id']
            );

            $this->sendEmail($email_data);
        }
    }

    private function sendEmail($email_data){
        $postObject = new \Magento\Framework\DataObject();
        $postObject->setData($email_data['template_vars']);

        $transport = $this->transportBuilder
            ->setTemplateIdentifier($email_data['template_id'])
            ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_ADMINHTML, 'store' => $email_data['store_id']])
            ->setTemplateVars(['data' => $postObject])
            ->setFrom(['name' => $this->helper->getConfig('trans_email/ident_sales/name', $email_data['store_id']), 'email' => $this->helper->getConfig('trans_email/ident_sales/email', $email_data['store_id'])])
            ->addTo([$email_data['to_email']])
            ->getTransport();
        $transport->sendMessage();
    }

    private function getTemplateVars($postData){
        $template_vars = array(
            'ip' => $postData['user_ip'],
            'browser' => $postData['user_browser_details']['name'] . '-' . $postData['user_browser_details']['version'],
            'system_platform' => $postData['user_browser_details']['platform'],
            'cashier_firstname' => $postData['user_details']['cashier_firstname'],
            'cashier_lastname' => $postData['user_details']['cashier_lastname'],
            'cashier_email' => $postData['user_details']['cashier_email'],
            'cashier_telephone' => $postData['user_details']['cashier_telephone'], 
            'outlet_name' => $postData['user_details']['outlet_name'],
            'outlet_email' => $postData['user_details']['outlet_email'],
            'store_url' => $postData['store_url'],
            'outlet_telephone' => $postData['user_details']['outlet_telephone'],
        );

        if($postData['type'] == 'user_login') {
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['login_time']))->format('Y-m-d h:i:s A');
        }

        if($postData['type'] == 'user_signout'){
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['signout_time']))->format('Y-m-d h:i:s A');
        }
        
        if($postData['type'] == 'terminal_reset'){
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['terminal_reset_time']))->format('Y-m-d h:i:s A');
        }

        if($postData['type'] == 'custom_product'){
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['custom_product_add_time']))->format('Y-m-d h:i:s A');
            $template_vars['product_name'] = $postData['name'];
            $template_vars['product_price'] = $postData['price'];
            $template_vars['product_tax_class'] =  $postData['tax_class_id'] ? $this->getTaxClassTitle($postData['tax_class_id']) : "";
            $template_vars['product_quantity'] = $postData['qty'];
        }

        if($postData['type'] == 'hold_cart'){
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['hold_cart_time']))->format('Y-m-d h:i:s A');
            $template_vars['hold_cart_note'] = $postData['note'];
        }

        if($postData['type'] == 'hold_cart' || $postData['type'] == 'return_product' || $postData['type'] == 'customer_feedback'){
            $customer = array(
                'customer_firstname' => 'Guest',
                'customer_lastname' => 'User',
                'customer_email' => $postData['user_details']['cashier_email'],
                'customer_telephone' => $postData['user_details']['cashier_telephone']
            );

            if($postData['type'] == 'hold_cart' || $postData['type'] == 'return_product'){
                if((isset($postData['customer']) && $postData['customer']) || (isset($postData['customer_id']) && $postData['customer_id'])){
                    $customer_id = isset($postData['customer']) ? $postData['customer'] : $postData['customer_id'];
                    $customer_data = $this->getCustomerData($customer_id);
                    if($customer_data){
                        $customer = $customer_data;
                    }
                }
            } elseif($postData['type'] == 'customer_feedback'){
                $order_id = $postData['feedbackData']['order_id'];
                $orderData = $this->orderRepository->get($postData['feedbackData']['order_id']);
                if($orderData->getCustomerFirstname()){
                    $customer['customer_firstname'] = $orderData->getCustomerFirstname();
                    $customer['customer_lastname'] = $orderData->getCustomerLastname();
                    $customer['customer_email'] = $orderData->getCustomerEmail();
                }
            }

            $template_vars = array_merge($template_vars, $customer);
        }

        if($postData['type'] == 'hold_cart' || $postData['type'] == 'return_product' || $postData['type'] == 'customer_feedback') {

            if($postData['type'] == 'hold_cart'){
                $products_data = $postData['cart'];
            } elseif($postData['type'] == 'return_product') {
                $products_data = $postData['products'];
            } elseif($postData['type'] == 'customer_feedback') {
                $products_data = $postData['cartData'];
            }
            $product = $this->concatProductsData($products_data, $postData['type'], $postData['user_details']['store_view_id']);
            $template_vars = array_merge($template_vars, $product);
        }

        if($postData['type'] == 'hold_cart' || $postData['type'] == 'customer_feedback'){
        

            $postData['cart_subtotal'] = '';
            $postData['cart_tax']['title'] = $postData['cart_tax']['amount'] = array();
            if(isset($postData['cartTotals']) && !empty($postData['cartTotals'])){

                foreach($postData['cartTotals'] as $key=>$cart_totals){
                    if($cart_totals['code'] == 'sub_total'){
                        $postData['cart_subtotal'] = $cart_totals['formatted'];
                    }

                    if($cart_totals['code'] == 'tax'){
                        $postData['cart_tax']['title'][] = $cart_totals['title'];
                        $postData['cart_tax']['amount'][] = $cart_totals['formatted'];                        
                    }
                }
            }

            $template_vars['cart_subtotal'] = $postData['cart_subtotal'];
            $template_vars['cart_tax_title'] = implode(", " , $postData['cart_tax']['title']);
            $template_vars['cart_tax_amount'] = implode(", " , $postData['cart_tax']['amount']);
            if($postData['type'] == 'customer_feedback'){
                $orderData = $this->orderRepository->get($postData['feedbackData']['order_id']);
                $template_vars['cart_grand_total'] = $orderData->getGrandTotal();
                $template_vars['order_id'] = "#".$postData['feedbackData']['order_id'];
                $template_vars['order_date'] = $this->timezone->date(new \DateTime($orderData->getCreatedAt()))->format('Y-m-d h:i:s A');
            } else {
                $template_vars['cart_grand_total'] = $postData['grandTotal'];
            }
        }

        if($postData['type'] == 'return_product'){
            $template_vars['date_time'] = $this->timezone->date(new \DateTime($postData['return_product_time']))->format('Y-m-d h:i:s A');
            $template_vars['payment_method'] = $postData['payment_mode'];
            $template_vars['order_id'] = "#".$postData['order_id'];
            $template_vars['order_date'] = $postData['date_ordered'];
            $template_vars['return_status'] = $this->getReturnStatus($postData['return_status_id'], $postData['user_details']['store_view_id']);
            $template_vars['return_custom_fields'] = $this->getReturnCustomFields($postData['custom_fields']);
        }

        if($postData['type'] == 'customer_feedback'){
            $returnCustomFieldsResult = array();
            if(isset($postData['feedbackData']['feedback_details']) && !empty($postData['feedbackData']['feedback_details'])){
                $feedbackCheckboxValues = $feedbackMultiValues = array();

                foreach($postData['feedbackData']['feedback_details'] as $feedback) {
                    if($feedback['type'] == 'checkbox'){
                        $feedbackCheckboxValues[$feedback['pos_cds_question_id']][] = $feedback['option_value'];
                    }
    
                    if($feedback['type'] == 'multi-select'){
                        $feedbackMultiValues[$feedback['pos_cds_question_id']][] = $feedback['option_value'];
                    }
                }
    
                foreach($postData['feedbackData']['feedback_details'] as $feedback) {
                    if($feedback['type'] == 'checkbox'){
                        $feedbackAnswer = implode(', ', $feedbackCheckboxValues[$feedback['pos_cds_question_id']]);
                    } elseif($feedback['type'] == 'multi-select'){
                        $feedbackAnswer = implode(', ', $feedbackMultiValues[$feedback['pos_cds_question_id']]);
                    } elseif($feedback['type'] == 'datetime') {
                        $feedbackAnswer = $this->timezone->date(new \DateTime($feedback['option_value']))->format('Y-m-d h:i:s A');
                    } elseif($feedback['type'] == 'datetime') {
                        $feedbackAnswer = $this->timezone->date(new \DateTime($feedback['option_value']))->format('Y-m-d h:i:s A');
                    } elseif($feedback['type'] == 'time') {
                        $feedbackAnswer = $this->timezone->date(new \DateTime($feedback['option_value']))->format('h:i:s A');
                    } elseif($feedback['type'] == 'date') {
                        $feedbackAnswer = $this->timezone->date(new \DateTime($feedback['option_value']))->format('Y-m-d');
                    } elseif($feedback['type'] == 'file') {
                        $image_url = $feedback['option_value'];
                        $feedbackAnswer = "<img src= '$image_url' style='width:100px; height:100px;' /> ";
                    } else {
                        $feedbackAnswer = $feedback['option_value'];
                    }
                    $returnCustomFieldsResult[$feedback['pos_cds_question_id']] = html_entity_decode($feedback['title']) . " : " . html_entity_decode($feedbackAnswer);
                }
            }
    
            $template_vars['customer_feedback'] = implode("<br/>", $returnCustomFieldsResult);
        }

        
        return $template_vars;
    }

    private function getTaxClassTitle($tax_class_id) {
        $tax_class_title = '';
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['tc' => $this->resource->getTableName('tax_class')]
        )
        ->where("tc.class_id = $tax_class_id");
        
        $tax_class_data = $connection->query($select)->fetch();
        if(!empty($tax_class_data)){
            $tax_class_title = $tax_class_data['class_name'];
        }
        return $tax_class_title;
    }

    private function getCustomerData($customer_id){
        $customer_data = array();
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['ce' => $this->resource->getTableName('customer_entity')],
            ['customer_firstname'=>'firstname', 'customer_lastname'=>'lastname', 'customer_email'=>'email']
        )
        ->joinLeft(
            ['cae' => $this->resource->getTableName('customer_address_entity')],
            "ce.entity_id = cae.parent_id",
            ['customer_telephone'=>'telephone']
        )
        ->where("ce.entity_id = $customer_id");
        
        $customer_data = $connection->query($select)->fetch();
        return $customer_data;
    }

    private function concatProductsData($postData, $type, $store_id){
        $product = array('product_name' => array(), 'product_sku' => array(), 'product_price' => array(), 'product_quantity'=> array(), 'product_tax_class' => array(), 'product_return_reason' => array(), 'product_return_resolution' => array(), 'product_return_item_condition'=> array());
        $product_result = array('product_name' => '', 'product_sku' => '', 'product_price' => '', 'product_quantity'=> '', 'product_tax_class' => '', 'product_return_reason' => '', 'product_return_resolution' => '', 'product_return_item_condition'=> '');
        if(!empty($postData)){

            foreach($postData as $key=>$cart_product) {
                $tax_class = '';
                if(isset($cart_product['taxClassId']) && $cart_product['taxClassId']) {
                    $tax_class = $this->getTaxClassTitle($cart_product['taxClassId']);
                } else {
                    if((isset($cart_product['product_id']) && $cart_product['product_id']) || (isset($cart_product['productId']) && $cart_product['productId'])) {
                        $product_id = isset($cart_product['product_id']) ? $cart_product['product_id'] : $cart_product['productId'];
                        $product_repo = $this->productRepository->getById($cart_product['product_id']);
                        $tax_class_id = (int)$product_repo->getTaxClassId();
                        $tax_class = $this->getTaxClassTitle($tax_class_id);
                    }
                }

                $product['product_name'][] = $cart_product['name'];
                $product['product_sku'][] = isset($cart_product['sku']) ? $cart_product['sku'] : '';
                $product['product_price'][] = $cart_product['price']; 
                $product['product_quantity'][] = $cart_product['quantity'];
                $product['product_tax_class'][] = $tax_class;

                if($type == 'return_product'){
                    $product['product_return_reason'][] = $this->getProductReturnReason($cart_product['return_reason_id'], $store_id);
                    $product['product_return_resolution'][] = $this->getProductReturnResolution($cart_product['return_resolution_id'], $store_id);
                    $product['product_return_item_condition'][] = $this->getProductReturnCondition($cart_product['item_condition_id'], $store_id);
                }
            }       
        }

        $product_result = array(
            'product_name' => implode(", ", $product['product_name']),
            'product_sku' => implode(", ", $product['product_sku']),
            'product_price' => implode(", ", $product['product_price']),
            'product_quantity' => implode(", ", $product['product_quantity']),
            'product_tax_class' => implode(", ", $product['product_tax_class']),
            'product_return_reason' => implode(", ", $product['product_return_reason']),
            'product_return_resolution' => implode(", ", $product['product_return_resolution']),
            'product_return_item_condition' => implode(", ", $product['product_return_item_condition'])
        );

        return $product_result;
    }

    private function getProductReturnReason($reason_id, $store_id){
        $product_return_reason = '';
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_reason_store')],
            ['title']
        )
        ->where("rs.reason_id = $reason_id")
        ->where("rs.store_id = $store_id");
        $return_reason_data = $connection->query($select)->fetch();

        if(empty($return_reason_data)){
            $select = $connection->select();
            $select->from(
                ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_reason_store')],
                ['title']
            )
            ->where("rs.reason_id = $reason_id")
            ->where("rs.store_id = 0");
            
            $return_reason_data = $connection->query($select)->fetch();
        }

        if(!empty($return_reason_data)){
            $product_return_reason = $return_reason_data['title'];
        }
        return $product_return_reason;
    }

    private function getProductReturnResolution($resolution_id, $store_id){
        $product_return_resolution = '';
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_resolution_store')],
            ['title']
        )
        ->where("rs.resolution_id = $resolution_id")
        ->where("rs.store_id = $store_id");
        $return_resolution_data = $connection->query($select)->fetch();

        if(empty($return_resolution_data)){
            $select = $connection->select();
            $select->from(
                ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_resolution_store')],
                ['title']
            )
            ->where("rs.resolution_id = $resolution_id")
            ->where("rs.store_id = 0");
            
            $return_resolution_data = $connection->query($select)->fetch();
        }

        if(!empty($return_resolution_data)){
            $product_return_resolution = $return_resolution_data['title'];
        }
        return $product_return_resolution;
    }

    private function getProductReturnCondition($condition_id, $store_id){
        $product_return_condition = '';
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_condition_store')],
            ['title']
        )
        ->where("rs.condition_id = $condition_id")
        ->where("rs.store_id = $store_id");
        $return_condition_data = $connection->query($select)->fetch();

        if(empty($return_condition_data)){
            $select = $connection->select();
            $select->from(
                ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_condition_store')],
                ['title']
            )
            ->where("rs.condition_id = $condition_id")
            ->where("rs.store_id = 0");
            
            $return_condition_data = $connection->query($select)->fetch();
        }

        if(!empty($return_condition_data)){
            $product_return_condition = $return_condition_data['title'];
        }
        return $product_return_condition;
    }

    private function getReturnStatus($status_id, $store_id){
        $return_status = '';
        $connection =  $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_status_store')],
            ['title']
        )
        ->where("rs.status_id = $status_id")
        ->where("rs.store_id = $store_id");
        $return_status_data = $connection->query($select)->fetch();

        if(empty($return_status_data)){
            $select = $connection->select();
            $select->from(
                ['rs' => $this->resource->getTableName('ah_supermax_pos_rma_status_store')],
                ['title']
            )
            ->where("rs.status_id = $status_id")
            ->where("rs.store_id = 0");
            
            $return_status_data = $connection->query($select)->fetch();
        }

        if(!empty($return_status_data)){
            $return_status = $return_status_data['title'];
        }
        return $return_status;
    }

    private function getReturnCustomFields($custom_fields){
        $custom_fields_result = '';
        $returnCustomFieldsResult = array();
        if(!empty($custom_fields)){
            $customFieldCheckboxValues = $customFieldMultiValues = array();
            foreach($custom_fields as $customField) {
                if($customField['type'] == 'checkbox'){
                    $customFieldCheckboxValues[$customField['pos_rma_custom_field_id']][] = $customField['option_value'];
                }

                if($customField['type'] == 'multi-select'){
                    $customFieldMultiValues[$customField['pos_rma_custom_field_id']][] = $customField['option_value'];
                }
            }

            foreach($custom_fields as $customField) {
                if($customField['type'] == 'checkbox'){
                    $customFieldAnswer = implode(', ', $customFieldCheckboxValues[$customField['pos_rma_custom_field_id']]);
                } elseif($customField['type'] == 'multi-select'){
                    $customFieldAnswer = implode(', ', $customFieldMultiValues[$customField['pos_rma_custom_field_id']]);
                } elseif($customField['type'] == 'datetime') {
                    $customFieldAnswer = $this->timezone->date(new \DateTime($customField['option_value']))->format('Y-m-d h:i:s A');
                } elseif($customField['type'] == 'datetime') {
                    $customFieldAnswer = $this->timezone->date(new \DateTime($customField['option_value']))->format('Y-m-d h:i:s A');
                } elseif($customField['type'] == 'time') {
                    $customFieldAnswer = $this->timezone->date(new \DateTime($customField['option_value']))->format('h:i:s A');
                } elseif($customField['type'] == 'date') {
                    $customFieldAnswer = $this->timezone->date(new \DateTime($customField['option_value']))->format('Y-m-d');
                } elseif($customField['type'] == 'file') {
                    $image_url = $customField['option_value'];
                    $customFieldAnswer = "<img src= '$image_url' style='width:100px; height:100px;' /> ";
                } else {
                    $customFieldAnswer = $customField['option_value'];
                }
                $returnCustomFieldsResult[$customField['pos_rma_custom_field_id']] = html_entity_decode($customField['title']) . " : " . html_entity_decode($customFieldAnswer);
            }
        }

        $custom_fields_result = implode("<br/>", $returnCustomFieldsResult);
        return $custom_fields_result;
    }
}