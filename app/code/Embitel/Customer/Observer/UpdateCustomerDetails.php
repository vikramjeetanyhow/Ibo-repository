<?php
/**
 * @desc: Observer for create/update customer
 * @package Embitel_CustomerGraphQl
 * @author Amar Jyoti
 *
 */
declare(strict_types=1);

namespace Embitel\Customer\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Eav\Model\Config;
use Embitel\Customer\Model\Wallet;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Setup\Exception;
use Magento\Customer\Model\CustomerFactory;
use Psr\Log\LoggerInterface;

/**
 * Class observer UpgradeQuoteCustomerEmailObserver
 */
class UpdateCustomerDetails implements ObserverInterface
{

    public function __construct(
        CustomerFactory $customer,
        Config $eavConfig,
        Wallet $wallet,
        GroupRepositoryInterface $groupRepository,
        LoggerInterface $logger
    ) {
        $this->customer = $customer;
        $this->eavConfig = $eavConfig;
        $this->walletModel = $wallet;
        $this->groupInterface = $groupRepository;
        $this->logger = $logger;
    }

    /**
     * Upgrade quote customer email when customer has changed email
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Customer\Model\Data\Customer $customerOrig */
            $customerOrigData = $observer->getEvent()->getOrigCustomerDataObject();
            if (!$customerOrigData) {
                return;
            }

            $customer = $observer->getEvent()->getCustomerDataObject();

            $oldCusmerGroup = $customerOrigData->getGroupId() ?? '';
            $newCustmerGroup = $customer->getGroupId() ?? '';

            $oldMobileNumber = $customerOrigData->getCustomAttribute("mobilenumber")->getValue() ?? '';
            $newMobileNumber = $customer->getCustomAttribute("mobilenumber")->getValue() ?? '';

            /* Call wallet only if mobile no or group updated */
            if($oldCusmerGroup != $newCustmerGroup || $oldMobileNumber != $newMobileNumber) {
                $walletData = [
                    'customer_id' => $customer->getId(),
                    'customer_group_id' => $customer->getGroupId(),
                    'mobilenumber' => $customer->getCustomAttribute("mobilenumber")->getValue()
                        ?? '',
                    'approval_status' => $customer->getCustomAttribute('approval_status')->getValue() ?? ''
                ];

                $this->walletModel->createWallet($walletData);
            }
        } catch (Exception $ex) {
            $this->logger->debug("Error in customer update observer", $ex->getMessage());
        }
    }
}
