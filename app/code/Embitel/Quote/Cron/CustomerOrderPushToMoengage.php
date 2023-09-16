<?php

namespace Embitel\Quote\Cron;

use Magento\Framework\App\Area;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\ScopeInterface;
use \Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\Quote\Helper\Data as QuoteHelper;
use Embitel\Customer\Model\Api as CustomerApi;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;

class CustomerOrderPushToMoengage
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $_customer;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $_customerFactory;

    /**
     * @var QuoteHelper
     */
    protected $quoteHelper;

    /**
     * @var CustomerApi
     */
    protected $customerApi;

    /**
     * @var groupRepository
     */
    private $groupRepository;

    private $eavConfig;

    protected $orderRepository;

    /**
     * @var ProductRepositoryInterface
    */
    private $productRepository;

    /**
     * @param string $context
     * @param string $logger
     * @param string $storeManagerInterface
     * @param string $scopeConfigInterface
     * @param string $customerFactory
     * @param string $customers
     * @param string $eavConfig
     * @param string $quoteHelper
     * @param string $customerApi
     * @param string $groupRepository
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\Customer $customers,
        \Magento\Eav\Model\Config $eavConfig,
        QuoteHelper $quoteHelper,
        CustomerApi $customerApi,
        groupRepository $groupRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->logger = $logger;
        $this->_storeManager = $storeManagerInterface;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->_customerFactory = $customerFactory;
        $this->_customer = $customers;
        $this->eavConfig = $eavConfig;
        $this->quoteHelper = $quoteHelper;
        $this->customerApi = $customerApi;
        $this->groupRepository = $groupRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Customer push to Oodo
     *
     * @return void
     */
    public function execute()
    {
       $storeScope = ScopeInterface::SCOPE_STORE;
       $isModuleEnable =  $this->scopeConfigInterface->getValue(
            'ebo_customer_export/moengage_cron/is_enable',
            $storeScope
        );
       if ($isModuleEnable == 1 ) {
           try {
                $isModuleEnable =  $this->scopeConfigInterface->getValue(
                    'ebo_customer_export/moengage_cron/url',
                    $storeScope
                );
                $recentCustomerOrders = $this->quoteHelper->getCustomerOrderInfo();
                if(!empty($recentCustomerOrders)){
                    $arrayChunk = array_chunk($recentCustomerOrders,10);
                    foreach($arrayChunk as $currentChunk){
                        $bulkData = ["type" => "transition","elements" => []];
                        $customerData = $orderData = $orderIds = [];
                        foreach($currentChunk as $key => $orderInfo){
                            $order = $this->orderRepository->get($orderInfo['entity_id']);
                            $ruleIds = $orderInfo['applied_rule_ids'];
                            $orderRuleNames = '';
                            if(!empty($ruleIds)){
                                $ruleNames = $this->quoteHelper->getRuleNames($ruleIds);
                                $currentRuleNames = current($ruleNames);
                                $orderRuleNames = !empty($currentRuleNames['rule_name']) ? $currentRuleNames['rule_name'] : '';
                            }
                            try {
                                $orderItems = $order->getAllItems();
                                $totalQty = $totalAmount = 0;
                                $itemIds = $itemNames = $categoryNames = [];
                                foreach ($orderItems as $item)
                                {
                                    $itemIds[] = $item->getId();
                                    $itemNames[] = $item->getName();
                                    $totalQty = $totalQty + $item->getQtyOrdered();
                                    $product = $this->productRepository->get($item->getSku(), false, null, true);
                                    $categoryNames[] = $product->getDepartment();
                                    $totalAmount = $totalAmount + $item->getRowTotal();
                                }
                                $customerId = $order->getCustomerId();
                                $customer = $this->getCustomerInfo($customerId);
                                $customerType = $customer->getCustomerType();
                                $customerGroup = $this->getGroupName($customer->getGroupId());
                                $customerAttributes = [
                                    'name' => $customer->getName(),
                                    'email' => !empty($customer->getCustomerSecondaryEmail()) ? $customer->getCustomerSecondaryEmail() : "",
                                    'customer_option' => $this->getCustomerAttributeValue('customer_type',$customerType),
                                    'customer_type' => $customerGroup,
                                    'mobile' => $customer->getMobilenumber()
                                ];
                                $orderActions = [];
                                $orderActions[] = [
                                    'action' => 'purchase',
                                    'attributes' => [
                                        'quantity' => $totalQty,
                                        'customer_type' => $customerGroup,
                                        'transaction_id' => $order->getIncrementId(),
                                        'payment_type' => $order->getPayment()->getMethod(),
                                        'coupon_code' => empty($order->getCouponCode()) ? "" : $order->getCouponCode(),
                                        'cart_id' => $order->getQuoteId(),
                                        'item_id' => implode(',',$itemIds),
                                        'item_name' => implode(',',$itemNames),
                                        'CURRENCY' => $order->getBaseCurrencyCode(),
                                        'item_category' => implode(',', $categoryNames),
                                        'store_id' => $order->getStoreId(),
                                        'fulfilment_mode' => $order->getOrderChannelInfo(),
                                        'promotion' => $orderRuleNames,
                                        'value' => $totalAmount
                                    ]
                                ];
                                $orderData[] = ['type' => 'customer', 'customer_id' => $customerId, 'attributes' => $customerAttributes];
                                $orderData[] = ['type' => 'event', 'customer_id' => $customerId, 'actions' => $orderActions];
                                $orderIds[] = $order->getId();
                                $customer->reset();
                            }catch(\Exception $e){
                                $this->quoteHelper->addLog("Moengage exception - " . $e->getMessage(),"moengage-order-push.log");
                            }
                        }
                        $bulkData["elements"] = $orderData;
                        $jsonencode = json_encode($bulkData);
                        //Push the customer order to Moengage
                        $jsonencode = json_encode($bulkData);
                        $this->quoteHelper->addLog("Moengage order push - ".$jsonencode,"moengage-order-push.log");
                        $moengageOrderPush = $this->customerApi->pushCustomerToMoengage($jsonencode);
                        $this->quoteHelper->addLog("Moengage Order push Response - ".json_encode($moengageOrderPush),"moengage-order-push.log");
                        if(!empty($moengageOrderPush['status']) && $moengageOrderPush['status'] != 'fail'){
                            $this->quoteHelper->setMoengageFlag($orderIds);
                        }
                    }
                }else{
                    $this->quoteHelper->addLog("No Orders to process - ","moengage-order-push.log");
                }
               // exit;
           } catch (LocalizedException | FileSystemException $exception) {
               $emailBody = 'Something went wrong while export process. ' . $exception->getMessage();
               $this->quoteHelper->addLog($emailBody,"moengage-order-push.log");
               $this->logger->critical($emailBody);
           }
       }else {
           $this->logger->info('Customer push to moengage cron module is disabled');
       }
    }

    private function getCustomerInfo($customerId){
        return $this->_customer->load($customerId);
    }

    private function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCustomerAttributeValue($attributeCode, $value)
    {
        if(!empty($value)){
            $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
            return $attribute->getSource()->getOptionText($value);
        }else{
            return '';
        }
        
    }
}
