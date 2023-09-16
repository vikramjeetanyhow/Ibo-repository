<?php
namespace Embitel\Notification\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const UNAPPROVED_CUSTOMER_ENABLE = 'sms_notification/unapproved_customer/enable';
    const UNAPPROVED_CUSTOMER_EVENT_ID = 'sms_notification/unapproved_customer/event_id';
    const UNAPPROVED_CUSTOMER_TYPE  = 'sms_notification/unapproved_customer/customer_type';
    const BIZOM_MAGENTO_ENABLE  = 'sms_notification/bizom_to_magento/enable';    
    const BIZOM_MAGENTO_EVENT_ID  = 'sms_notification/bizom_to_magento/event_id';
    const IBO_URL  = 'sms_notification/bizom_to_magento/ibo_url';
    const NOTIFICATION_KEY = 'sms_notification/settings/api_key';
    const NOTIFICATION_URL = 'sms_notification/settings/url';
    const NOTIFICATION_ENABLED = 'sms_notification/settings/enable';
    const SALES_ORDER_ENABLE = 'sms_notification/sales_order/enable';
    const SALES_ORDER_EVENT_ID = 'sms_notification/sales_order/event_id';

    /**
     * @param Context $context     
     */
    public function __construct(
        Context $context
    ) {        
        parent::__construct($context);
    }

    /**
     * Is Unapproved Customer Notification enabled
     *
     * @param string $storeId
     * @return boolean
    */
    public function isUnapprovedCustomerSmsEnabled($storeId = null)
    {
        return (boolean)$this->scopeConfig->getValue(self::UNAPPROVED_CUSTOMER_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Unapproved Customer Event Id
     *
     * @param string $storeId
     * @return string
     */
    public function getUnapprovedCustomerEventId($storeId = null)
    {
        return $this->scopeConfig->getValue(self::UNAPPROVED_CUSTOMER_EVENT_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Get Unapproved Customer Types
     *
     * @param string $storeId
     * @return string
     */
    public function getCustomerTypeIds($storeId = null)
    {
        return $this->scopeConfig->getValue(self::UNAPPROVED_CUSTOMER_TYPE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Is Bizom to Magento Notification enabled
     *
     * @param string $storeId
     * @return boolean
    */
    public function isBizomMagentoSmsEnabled($storeId = null)
    {
        return (boolean)$this->scopeConfig->getValue(self::BIZOM_MAGENTO_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Bizom to Magento Event Id
     *
     * @param string $storeId
     * @return string
     */
    public function getBizomMagentoEventId($storeId = null)
    {
        return $this->scopeConfig->getValue(self::BIZOM_MAGENTO_EVENT_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Customer Type Ids
     *
     * @param string $storeId
     * @return string
     */
    public function getIboUrl($storeId = null)
    {
        return $this->scopeConfig->getValue(self::IBO_URL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Notification Api Key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue(self::NOTIFICATION_KEY);
    }   
    
    /**
     * Get Notification Api Url
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->scopeConfig->getValue(self::NOTIFICATION_URL);
    }

    /**
     * Get Notification Api Enabled
     *
     * @return boolean
     */
    public function getApiEnable()
    {
        return $this->scopeConfig->getValue(self::NOTIFICATION_ENABLED);
    }    

    /**
     * Is Sales Order Notification enabled
     *
     * @param string $storeId
     * @return boolean
    */
    public function isSalesOrderSmsEnabled($storeId = null)
    {
        return (boolean)$this->scopeConfig->getValue(self::SALES_ORDER_ENABLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Sales Order Event Id
     *
     * @param string $storeId
     * @return string
     */
    public function getSalesOrderEventId($storeId = null)
    {
        return $this->scopeConfig->getValue(self::SALES_ORDER_EVENT_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

}
