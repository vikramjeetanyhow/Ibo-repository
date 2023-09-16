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
class OwnInvoicePromotion extends \Magento\Rule\Model\Condition\AbstractCondition
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
            'on_invoice_b2p_promotion' => __('On Invoice B2P Promo')
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
        $attribute = $this->getOnInvoiceB2PInfo();
        $attributeValue = false;
        if($attribute == "yes") {
            $attributeValue = true;
        }
        $model->setData('on_invoice_b2p_promotion', $attributeValue);
        return parent::validate($model);
    }

    public function getOnInvoiceB2PInfo()
    {
        return !empty($this->request->getHeader('invoiceB2Ppromo')) ? $this->request->getHeader('invoiceB2Ppromo') : '';
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
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/own_invoice.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $this->isLogEnable;
    }
}
