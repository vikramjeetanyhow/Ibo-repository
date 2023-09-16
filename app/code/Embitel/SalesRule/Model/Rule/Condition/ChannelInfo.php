<?php
namespace Embitel\SalesRule\Model\Rule\Condition;

/**
 * Customer Groups model
 */
class ChannelInfo extends \Magento\Rule\Model\Condition\AbstractCondition
{

    /**
     * Constructor initialise
     *
     * @param \Magento\Rule\Model\Condition\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        array $data = []
    ) {
        $this->request = $request;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([
            'channel_info' => __('Channel Info')
        ]);
        return $this;
    }

    /**
     * Get input type
     *
     * @return string
     */
    public function getInputType()
    {
        return 'select';
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        return 'select';
    }

    /**
     * Get value select options
     *
     * @return array|mixed
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            $channelConfig = $this->getChannelConfig();
            $channelData = [];
            if(!empty($channelConfig)){
                foreach($channelConfig as $currentChannel){
                    $channelData[] = ['value' => $currentChannel,'label' => ucwords($currentChannel)];
                }
            }
            $this->setData(
                'value_select_options',
                $channelData
            );
        }
        return $this->getData('value_select_options');
    }

    /**
     * Validate Customer Group Rule Condition
     *
     * @param \Magento\Framework\Model\AbstractModel $model
     * @return bool
     */
    public function validate(\Magento\Framework\Model\AbstractModel $model)
    {
        $attributeValue = $this->getCurrentChannelInfo();
        $model->setData('channel_info', $attributeValue);
        return parent::validate($model);
    }

    /**
     * Get current Customer group
     *
     * @return string
     */
    public function getCurrentChannelInfo()
    {
        return !empty($this->request->getHeader('sourceChannelInfo')) ? $this->request->getHeader('sourceChannelInfo') : '';
    }
    
   /**
     * Get order approval conditions
     *
     * @return array|boolean
     */
    public function getOrderApprovalConditions()
    {
        $conditions =  $this->scopeConfig->getValue(
            "order_approval/settings/condition",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        
        if ($conditions) {
            return $this->_json->jsonDecode($conditions);
        }

        return false;
    }

    private function getChannelConfig(){
        $channelInfo =  $this->config->getValue(
            "cart_price_rules/codition/channel_info",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if(!empty($channelInfo)){
            return explode(',', $channelInfo);
        }
        return [];
    }
}