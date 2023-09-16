<?php

namespace Ibo\CustomerImport\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;
use Magento\Sales\Model\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;

class OrderPlaceAfter implements ObserverInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var ExtractCustomerAttribute
     */
    protected $extractCustomerAttribute;
    /**
     * @var Order
     */
    protected $order;
 
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ExtractCustomerAttribute $extractCustomerAttribute,
        Order $order,
        Registry $registry
    ) {
        $this->customerRepository = $customerRepository;
        $this->extractCustomerAttribute = $extractCustomerAttribute;
        $this->order = $order;
        $this->coreRegistry = $registry;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->coreRegistry->registry('sales_order_save_after')) {
            $this->coreRegistry->register('sales_order_save_after', 'Executed');
            try {
                $orderId = $observer->getEvent()->getOrder()->getEntityId();
                $this->addLog('=====Start===== OrderId: '.$orderId);
                $isCsvImport = 'No';
                $orderData = $this->order->load($orderId);
                $orderChennel = $orderData->getOrderChannel();
                if ($orderChennel == 'STORE') { //STORE is for off-line and ONLINE for web order
                    $customerId = $observer->getEvent()->getOrder()->getCustomerId();

                    if ($customerId !='') {
                        $this->addLog('Customer exist:'.$customerId);
                        $customerRepo = $this->customerRepository->getById($customerId);

                        if (!empty($customerRepo->getCustomAttribute('is_csv_import'))) {
                            $isCsvImport = $customerRepo->getCustomAttribute('is_csv_import')->getValue();

                            if ($isCsvImport == 'Yes' || $isCsvImport == 'yes' || $isCsvImport == 'YES') {
                                $this->addLog('CSV import is True');
                                $approval_attribute = ['label' => 'approved', 'key' => 'approval_status'];
                                $approvalcustomerOptionVal = $this->extractCustomerAttribute->get($approval_attribute);
                                $customerRepo->setCustomAttribute('approval_status', $approvalcustomerOptionVal);
                                $this->customerRepository->save($customerRepo);
                                $this->addLog('Customer status saved:');
                            } else {
                                $this->addLog('CSV import value not found YES/Yes/yes for customer: '.$customerId);
                            }
                        }
                    }
                }
            } catch (NoSuchEntityException $e) {
                  $this->addLog('Catch error: '.$e->getMessage());
                  throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
            }
        }
    }

    public function addLog($logData)
    {
        $filename = "orderPlaceAfter.log";
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $logEnable;
    }
}
