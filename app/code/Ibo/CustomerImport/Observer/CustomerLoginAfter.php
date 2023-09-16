<?php

namespace Ibo\CustomerImport\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Embitel\CustomerGraphQl\Model\Customer\ExtractCustomerAttribute;

class CustomerLoginAfter implements ObserverInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
 
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ExtractCustomerAttribute $extractCustomerAttribute
    ) {
        $this->customerRepository = $customerRepository;
        $this->extractCustomerAttribute = $extractCustomerAttribute;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        $customerId = $customer->getId();
        $isCsvImport = 'No';

        if ($customerId !='') {
            $this->addLog('Customer After Login: '. $customerId);
            $customerRepo = $this->customerRepository->getById($customerId);
            if (!empty($customerRepo->getCustomAttribute('is_csv_import'))) {
                $isCsvImport = $customerRepo->getCustomAttribute('is_csv_import')->getValue();
                if ($isCsvImport == 'Yes' || $isCsvImport == 'yes' || $isCsvImport == 'YES') {
                    $this->addLog('CSV Import True');
                    $approval_attribute = ['label' => 'approved', 'key' => 'approval_status'];
                    $approvalcustomerOptionValue = $this->extractCustomerAttribute->get($approval_attribute);
                    $customerRepo->setCustomAttribute('approval_status', $approvalcustomerOptionValue);
                    $this->customerRepository->save($customerRepo);
                    $this->addLog('Customer Status updated.');
                } else {
                    $this->addLog('CSV import value not found YES/Yes/yes for customer: '.$customerId);
                }
            }
        }
    }

    public function addLog($logData)
    {
        $filename = "customerLoginAfter.log";
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
