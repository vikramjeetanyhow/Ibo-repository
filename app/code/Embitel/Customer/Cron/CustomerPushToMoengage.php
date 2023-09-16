<?php

namespace Embitel\Customer\Cron;

use Magento\Framework\App\Area;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\ScopeInterface;
use \Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\Customer\Helper\Data as CustomerHelper;
use Embitel\Customer\Model\Api as CustomerApi;
use Magento\Customer\Api\GroupRepositoryInterface as groupRepository;

class CustomerPushToMoengage
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
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var CustomerApi
     */
    protected $customerApi;

    /**
     * @var groupRepository
     */
    private $groupRepository;

    /**
     * @var eavConfig
     */
    private $eavConfig;

    /**
     * @param string $context
     * @param string $logger
     * @param string $storeManagerInterface
     * @param string $scopeConfigInterface
     * @param string $customerFactory
     * @param string $customers
     * @param string $eavConfig
     * @param string $customerHelper
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
        CustomerHelper $customerHelper,
        CustomerApi $customerApi,
        groupRepository $groupRepository
    ) {
        $this->logger = $logger;
        $this->_storeManager = $storeManagerInterface;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->_customerFactory = $customerFactory;
        $this->_customer = $customers;
        $this->eavConfig = $eavConfig;
        $this->customerHelper = $customerHelper;
        $this->customerApi = $customerApi;
        $this->groupRepository = $groupRepository;
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
        $isCSVImport = 'No';

        if ($isModuleEnable == 1) {
            $this->logger->info('Moengage Start');
            try {
                $isModuleEnable =  $this->scopeConfigInterface->getValue(
                    'ebo_customer_export/moengage_cron/url',
                    $storeScope
                );
                $recentCustomers = $this->customerHelper->getCustomerInfo();
                if (!empty($recentCustomers)) {
                    $arrayChunk = array_chunk($recentCustomers, 10);
                    foreach ($arrayChunk as $currentChunk) {
                        $bulkData = ["type" => "transition","elements" => []];
                        $customerData = [];
                        foreach ($currentChunk as $key => $customerInfo) {
                            $customer = $this->getCustomerInfo($customerInfo['entity_id']);
                            $customerValue = $customer->getData();
                            $firstName = $customer->getFirstname();
                            $lastName = $customer->getLastname();
                            $customerType = $customer->getCustomerType();
                            $approvalStatus = $this->getCustomerAttributeValue(
                                'approval_status',
                                $customer->getApprovalStatus()
                            );
                            if (isset($customerValue['is_csv_import']) && ($customerValue['is_csv_import'] !='')) {
                                $isCSVImport = $customerValue['is_csv_import'] ;
                            }
                            $baActivities = $customer->getBusinessActivities();
                            $this->logger->info('Ba Activities ' . $baActivities);
                            $allBa = [];
                            if (!empty($baActivities)) {
                                $baActivities = explode(',', $baActivities);
                                foreach ($baActivities as $currentBa) {
                                    $allBa[] = $this->getCustomerAttributeValue(
                                        'business_activities',
                                        $currentBa
                                    );
                                }
                            }
                            $implodedBa = !empty($allBa) ? implode(',', $allBa) : "";

                            if ($approvalStatus != 'pending') {
                                $customerData['type'] = 'customer';
                                $customerData['customer_id'] = $customerInfo['mobilenumber'];
                                $customerData['attributes']['name'] = $firstName . " " . $lastName;
                                $secondaryEmail = $customer->getCustomerSecondaryEmail();
                                $customerData['attributes']['email'] = !empty($secondaryEmail) ? $secondaryEmail : "";
                                $customerData['attributes']['approval_status'] = $this->getCustomerAttributeValue(
                                    'approval_status',
                                    $customer->getApprovalStatus()
                                );
                                $customerData['attributes']['customer_type'] = $this->getGroupName(
                                    $customer->getGroupId()
                                );
                                $customerData['attributes']['customer_option'] = $this->getCustomerAttributeValue(
                                    'customer_type',
                                    $customerType
                                );
                                $customerData['attributes']['customer_category'] = $implodedBa;
                                $customerData['attributes']['primary_category'] = $implodedBa;
                                $customerData['attributes']['mobile'] = $customerInfo['mobilenumber'];
                                $salesRepName = $customer->getSalesRepName();
                                $customerData['attributes']['sales_spoc'] = !empty($salesRepName) ? $salesRepName : "";
                            }
                            $customer->reset();
                            $bulkData["elements"][] = $customerData;
                        }
                        $jsonencode = json_encode($bulkData);
                        //Push the customer to Moengage
                        $moengageCustomerResponse = $this->customerApi->pushCustomerToMoengage($jsonencode);
                        $this->logger->info('Customer encoded data ' . json_encode($bulkData, JSON_PRETTY_PRINT));
                        $this->logger->info('Customer Response ' . json_encode($moengageCustomerResponse));

                        if ($isCSVImport == 'Yes' || $isCSVImport == 'yes' || $isCSVImport == 'YES') {
                            $moengageOfflineCustomerResponse = $this->customerApi->pushCustomerToOfflineMoengage(
                                $jsonencode
                            );
                            $customerResponse = json_encode($moengageOfflineCustomerResponse);
                            $this->logger->info('IBO Offline Customer Response ' . $customerResponse);
                        } else {
                            $this->logger->info('CSV file does not found column is_csv_import value YES/Yes/yes for customer: '.$customerInfo['entity_id']);
                        }
                    }
                } else {
                    $this->logger->info('No customer found to push to Moengage');
                }
            } catch (LocalizedException | FileSystemException $exception) {
                $emailBody = 'Something went wrong while export process. ' . $exception->getMessage();
                $this->logger->critical('Something went wrong while export process. ' . $exception->getMessage());
            }
        } else {
            $this->logger->info('Customer push to moengage cron module is disabled');
        }
        unset($isCSVImport);
    }

    private function getCustomerInfo($customerId)
    {
        return $this->_customer->load($customerId);
    }

    public function getGroupName($groupId)
    {
        $group = $this->groupRepository->getById($groupId);
        return $group->getCode();
    }

    private function getCustomerAttributeValue($attributeCode, $value)
    {
        if (!empty($value)) {
            $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
            return $attribute->getSource()->getOptionText($value);
        } else {
            return '';
        }
    }
}
