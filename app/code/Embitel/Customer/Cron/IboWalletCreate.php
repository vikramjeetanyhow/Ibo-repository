<?php
/**
 * @desc: Create Ibo wallet
 * @package Embitel_Customer
 * @Author Amar Jyoti
 *
 */

namespace Embitel\Customer\Cron;

use Magento\Framework\App\ResourceConnection;
use Embitel\Customer\Model\Wallet;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Eav\Model\Config;
use Magento\Customer\Api\GroupRepositoryInterface;

class IboWalletCreate
{
    private \Magento\Framework\DB\Adapter\AdapterInterface $connection;
    private Wallet $walletModel;
    private ScopeConfigInterface $scopeConfig;
    private ResourceConnection $resourceConnection;
    private CustomerFactory $customerModelFactory;
    private Config $eavConfig;
    private GroupRepositoryInterface $groupInterface;

    public function __construct(
        ResourceConnection $resourceConnection,
        Wallet $wallet,
        ScopeConfigInterface $scopeConfig,
        CustomerFactory $customer,
        Config $config,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->walletModel = $wallet;
        $this->scopeConfig = $scopeConfig;
        $this->customerModelFactory = $customer;
        $this->eavConfig = $config;
        $this->groupInterface = $groupRepository;
    }

    public function execute()
    {

        $enable = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/is_cron_enable");

        if($enable) {
            $this->walletModel->addLog("=== Ibo Wallet Cron Started ===");

            $maxAttempt = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/wallet_retry_attempt") ?? 3;
            $tableName = $this->connection->getTableName('ibo_customer_wallet');
            $select = $this->connection->select()
                ->from(
                    ['c' => $tableName],
                    ['*']
                );
            $records = $this->connection->fetchAll($select);
            $this->walletModel->addLog("Record to process in cron: " . count($records));

            if (!empty($records)) {
                foreach ($records as $data) {
                    if ($data['retry_attempt'] < $maxAttempt) {
                        $customerObj = $this->customerModelFactory->create()->load($data['customer_id']);
                        if (!empty($customerObj->getDataModel()->getCustomAttribute('approval_status'))) {
                            $approval_status_code = $customerObj->getDataModel()
                                ->getCustomAttribute('approval_status')->getValue();
                            $attribute = $this->eavConfig->getAttribute('customer', 'approval_status');
                            $status = $attribute->getSource()->getOptionText($approval_status_code);
                        }

                        $customerGroup = $this->groupInterface->getById($customerObj->getGroupId());
                        $groupName = $customerGroup->getCode();

                        if (($groupName === 'B2P') && !empty($status) &&
                            ($status === 'pending' || $status === 'rejected')) {
                            $groupName = 'B2C';
                        }

                        $walletData = [
                            'customer_id' => $customerObj->getId(),
                            'customer_group_id' => $customerObj->getGroupId() ?? '',
                            'mobilenumber' => $customerObj->getData()['mobilenumber'] ?? '',
                            'approval_status' => $approval_status_code ?? ''
                        ];

                        $walletResponse = $this->walletModel->createWallet($walletData);
                        if (!empty($walletResponse['wallet_id'])) {
                            $this->walletModel->addLog("Wallet created through cron: " . $walletResponse['wallet_id']);
                            //delete customer from temp table
                            $this->connection->delete('ibo_customer_wallet', 'customer_id=' . $customerObj->getId());
                        }
                    } else {
                        //call slack integration here.
                        $this->walletModel->addLog("Max retry attempt exceed, customer Id: " . $data['customer_id']);
                    }
                }
            }

            $this->walletModel->addLog("=== Ibo Wallet Cron Ended ===");
        }
    }
}
