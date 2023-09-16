<?php
namespace Embitel\Oodo\Plugin;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Embitel\Oodo\Helper\OodoPush;
use Embitel\Oodo\Model\Api as OodoApi;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Setup\Exception;

/**
 * Class CustomerAfterSave
 * @package Embitel\Oodo\Plugin
 */
class CustomerAfterSave
{

    /**
     * @var OodoPush
     */
    private $oodoPush;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var Config
     */
    protected $eavConfig;

    /**
     *
     * @var OodoApi
     */
  protected $oodoApi;
    private GroupRepositoryInterface $groupRepository;

    /**
     * @param OodoPush $oodoPush
     * @param OodoApi $oodoApi
     * @param Customer $customer
     * @param CustomerFactory $customerFactory
     * @param Config $eavConfig
     * @param GroupRepositoryInterface $groupRepository
     */
    public function __construct(
        OodoPush $oodoPush,
        OodoApi $oodoApi,
        Customer $customer,
        CustomerFactory $customerFactory,
        Config $eavConfig,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->oodoPush = $oodoPush;
        $this->oodoApi = $oodoApi;
        $this->customer = $customer;
        $this->customerFactory = $customerFactory;
        $this->eavConfig = $eavConfig;
        $this->groupRepository = $groupRepository;
    }
    /**
     * @param CustomerRepository $subject
     * @param $savedCustomer
     * @return mixed
     */
    public function afterSave(
        CustomerRepository $subject,
        $savedCustomer
    ) {
        /**
         * $savedCustomer is your saved customer object.
         */
        $customerId = $savedCustomer->getId();

        try {
            $customerGroup = $this->groupRepository->getById($savedCustomer->getGroupId());
            if($customerGroup->getCode() != 'B2C'){
                $customer = $this->customer->load($customerId);
                $oodoPushResponse = $this->oodoApi->pushCustomerToOodo($customer);
                $successResponse = !empty($oodoPushResponse['result']) ? json_decode($oodoPushResponse['result'],true) : [];
                if(!empty($oodoPushResponse['error']) || !empty($oodoPushResponse['api_error'])){
                    $this->oodoPush->create(['customer_id' => $customerId]);
                }elseif(!empty($successResponse['customer_odoo_id'])){
                    $customerData = $customer->getDataModel();
                    $customerData->setCustomAttribute('oodo_customer_id',$successResponse['customer_odoo_id']);
                    $customer->updateData($customerData);
                    $customerResource = $this->customerFactory->create();
                    $customerResource->saveAttribute($customer, 'oodo_customer_id');
                    $this->oodoPush->deletePushedCustomerData($customerId);
                }
            }
        } catch (\Exception $e) {

        }

        return $savedCustomer;
    }
}
