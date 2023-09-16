<?php
/**
 * @desc: for promotional referral condition
 *
 * @author Amar Jyoti
 *
 */
namespace Embitel\SalesRule\Model\Rule\Condition;

/**
 * Customer Groups model
 */
class PromotionalReferral extends \Magento\Rule\Model\Condition\AbstractCondition
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
        \Embitel\SalesRule\Helper\Data $helperData,
        array $data = []
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->salesRuleHelper = $helperData;
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
            'promotional_referral' => __('Promotional Referral')
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
            $options = [
                "0" => "FALSE",
                "1" => "TRUE"
            ];
            $optionsData = [];
            if (!empty($options)) {
                foreach ($options as $value => $lable) {
                    $optionsData[] = ['value' => $value,'label' => ucwords($lable)];
                }
            }

            $this->setData(
                'value_select_options',
                $optionsData
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
        $attributeValue = true;
        $model->setData('promotional_referral', $attributeValue);
        return parent::validate($model);
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
}
