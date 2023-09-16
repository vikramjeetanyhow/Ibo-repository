<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\Customer\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Class observer UpgradeQuoteCustomerEmailObserver
 */
class InvalidateCustomerObserver implements ObserverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository, 
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Integration\Model\CustomerTokenService $customerToken,
        \Magento\Variable\Model\VariableFactory $varFactory

    ) {
        $this->quoteRepository = $quoteRepository; 
        $this->tokenService = $customerToken;
        $this->varFactory = $varFactory;
        $this->customer = $customer; 
    }

    /**
     * Upgrade quote customer email when customer has changed email
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    { 
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/custupdt.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer); 
        $logger->info("Entered Observer");

        /** @var \Magento\Customer\Model\Data\Customer $customerOrig */
        $customerOrigData = $observer->getEvent()->getOrigCustomerDataObject();
        if (!$customerOrigData) {
            return;
        }else { 
            $customerOrig = $this->customer->create()->load($customerOrigData->getId());
            $customerOrig->reset();
            $customerOrig->updateData($customerOrigData);
        } 

        $invalidateCustomer = false;
        /** @var \Magento\Customer\Model\Data\Customer $customer */
        $customerLastData = $observer->getEvent()->getCustomerDataObject(); 
        if (!$customerLastData) {
            return;
        }else { 
            $customerLast = $this->customer->create()->load($customerLastData->getId()); 
            $customerLast->reset();
            $customerLast->updateData($customerLastData);
        } 
        $varObj = $this->varFactory->create();
        $varObj->loadByCode('customer_dependent_attributes');
        $customerAttr = $varObj->getValue('text');        
        $customerAttr = (isset($customerAttr))?explode(",",$customerAttr):"";
        if(isset($customerAttr)) { 
            foreach($customerAttr as $custAttr) { 
                $logger->info("--->".$custAttr);
                
                $customerAttrOrig = (isset($customerOrig) && null !== $customerOrig->getData($custAttr))?$customerOrig->getData($custAttr):""; 
                $logger->info("Before--->".$customerAttrOrig);
                $customerAttrNew = (isset($customerLast) && null !== $customerLast->getData($custAttr))?$customerLast->getData($custAttr):"";
                $logger->info("After--->".$customerAttrNew);
                if ($customerAttrNew != $customerAttrOrig) { 
                    $invalidateCustomer = true;
                } 
                $logger->info("--->".$invalidateCustomer);
    
            } 
            if($invalidateCustomer) { 
                try { 
                    $customerId = $customerOrigData->getId();
                    $this->tokenService->revokeCustomerAccessToken($customerId);
                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }         
                $logger->info("Customer Logged OUT ".$customerId);   
            }else { 
                $logger->info("NO LOG OUT");
    
            }
        }

    }
}
