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
use Magento\Framework\Mail\Template\TransportBuilder;

class MailOrderReceipt implements \Anyhow\SupermaxPos\Api\Supermax\MailOrderReceiptInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        $this->helper = $helper;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
    }

   /**
     * POST API
     * @api
     * 
     * @return string
     */
    public function mailOrderReceipt()
    {
        $result = array();
        $error = false;

        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $orderData = array();
                $params = $this->helper->getParams();

                $post_data = $params['data'];

                $post_data['total_products'] = count($post_data['products']);
                $post_data['display_tax_summary'] = $this->scopeConfig->getValue('ah_supermax_pos_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_show_order_tax_summary', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                $post_data['show_payment_shipping_address'] = ($post_data['shippingAddressShowReceipt'] || $post_data['paymentAddressShowReceipt']) ? true : false;
                $post_data['space_condition_for_address'] = ($post_data['shippingAddressShowReceipt'] && $post_data['paymentAddressShowReceipt']) ? true : false;

                $post_data['text_left'] = $post_data['is_rtl'] ? false : true;
                $post_data['text_right'] = $post_data['is_rtl'] ? true : false;

                $post_data['show_cash_paid_and_change'] = ($post_data['cash_change_status'] && $post_data['cashPaidText']) && ($post_data['cashChangeText']) ? true : false;

                if(isset($post_data['products']) && !empty($post_data['products'])){
                    foreach($post_data['products'] as $key=>$product){
                        $post_data['products'][$key]['product_price'] = $product['added_discount'] ? '<del>'.$product['cost_before_disc_without_tax_formatted'].'</del>' : $product['final_cost_formatted'];

                        $post_data['products'][$key]['product_disc_price'] = $product['added_discount'] ? $product['final_cost_formatted'] : '-';
                    }
                }

                if(!$post_data['display_tax_summary']){
                    if(isset($post_data['totals']) && !empty($post_data['totals'])){
                        $totals = array();
                        foreach($post_data['totals'] as $key=>$total){
                            if($total['code'] != 'tax'){
                                $totals[] = $total;
                            }
                        }
                        $post_data['totals'] = $totals;
                    }
                }

                $postObject = new \Magento\Framework\DataObject();
                $postObject->setData($post_data);

                $transport = $this->transportBuilder
                    ->setTemplateIdentifier('ahPosOrderReceiptEmailTemplate')
                    ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_ADMINHTML, 'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID])
                    ->setTemplateVars(['data' => $postObject])
                    ->setFrom(['name' => $this->scopeConfig->getValue('trans_email/ident_sales/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),'email' => $this->scopeConfig->getValue('trans_email/ident_sales/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)])
                    ->addTo([$post_data['customerEmail']])
                    ->getTransport();
                $transport->sendMessage();
                
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $post_data = array('error' => $error, 'result' => $result);
        return json_encode($post_data);
    }

  
}