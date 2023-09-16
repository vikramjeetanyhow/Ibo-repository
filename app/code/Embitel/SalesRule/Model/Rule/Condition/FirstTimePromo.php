<?php
/**
 * @desc: for promotional referral condition
 *
 * @author Amar Jyoti
 *
 */
namespace Embitel\SalesRule\Model\Rule\Condition;

use _HumbugBoxe8a38a0636f4\phpDocumentor\Reflection\Types\Context;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;


/**
 * Customer Groups model
 */
class FirstTimePromo extends \Magento\Rule\Model\Condition\AbstractCondition
{

    /**
     * Constructor initialise
     *
     * @param \Magento\Rule\Model\Condition\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        RequestInterface $request,
        ScopeConfigInterface $config,
        UserContextInterface $userContext,
        GetCustomer $getCustomer,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->context = $context;
        $this->getCustomer = $getCustomer;
        $this->userContext = $userContext;
        $this->customerRepository = $customerRepository;
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
            'first_time_promo' => __('First Time Promo')
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
        $attributeValue =  $this->isFirstTimePromoApplicable();
        $model->setData('first_time_promo', $attributeValue);
        return parent::validate($model);
    }

    public function isFirstTimePromoApplicable() {
        try {
            $result = false;
            $isModuleEnable = $this->config->getValue(
                "first_time_promotion/first_time_promotion_group/first_time_promotion_enabled"
            );
            $customerId = $this->userContext->getUserId();
            if($this->request->getHeader('customerId') && ($customerId == 0)) {
                $customerId = (int)$this->request->getHeader('customerId');
            }
            if($isModuleEnable && $customerId > 0) {
                $this->addLog("====== Checking First time promotion ======");
                $this->addLog("Customer Id: ". $customerId);
                $customer = $this->customerRepository->getById($customerId);
                $firstTimePromoObj = $customer->getCustomAttribute('first_time_promo_applied');
                if(!empty($firstTimePromoObj)) {
                    $this->addLog("Customer first time Promo applied: " . $firstTimePromoObj->getValue());
                    //first time promo not applied (value 0), then result = true;
                    if (!$firstTimePromoObj->getValue()) {
                        $result = true;
                    }
                }
            }
            $this->addLog("Is Customer Eligible: ". $result);
            return $result;
        } catch (Exception $e) {
            $this->addLog($e->getMessage());
        }
    }

    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->config->getValue(
                "first_time_promotion/first_time_promotion_group/first_time_promotion_log_enabled"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/first_time_promo.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
