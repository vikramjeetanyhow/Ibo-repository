<?php

namespace Embitel\Oodo\Cron;

use Embitel\Oodo\Helper\OodoPush;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\Oodo\Model\Api as OodoApi;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Embitel\Oodo\Helper\Logger;

class CustomerPush {

  /**
   * Logger
   */
  protected $logger;

  /**
     * @var OodoPush
     */
    private $oodoPush;

  /**
   *
   * @var ScopeConfigInterface
   */
  protected $scopeConfig;

  /**
   *
   * @var OodoApi
   */
  protected $oodoApi;

  /**
   * @var CustomerFactory
   */
  protected $customerFactory;

  /**
   * @var Customer
   */
  protected $customer;

  /**
   * @param Logger $logger
   * @param OodoPush $oodoPush
   * @param ScopeConfigInterface $scopeConfig
   * @param OodoApi $oodoApi
   * @param Customer $customer
   * @param CustomerFactory $customerFactory
   */
  public function __construct(
      Logger $logger,
      OodoPush $oodoPush,
      ScopeConfigInterface $scopeConfig,
      OodoApi $oodoApi,
      Customer $customer,
      CustomerFactory $customerFactory
  ) {
    $this->logger = $logger;
    $this->oodoPush = $oodoPush;
    $this->scopeConfig = $scopeConfig;
    $this->oodoApi = $oodoApi;
    $this->customer = $customer;
    $this->customerFactory = $customerFactory;
  }

  /**
   * Customer push to Oodo
   * @return void
   */
  public function execute()
  {
      $isCronEnable = $this->scopeConfig->getValue("oodo/customer_push/cron_status");
      if (!$isCronEnable) {
          $this->logger->addLog("Oodo Customer Push Cron not enable in configuration");
          return;
      }
      try {
        //get B2B Customer ids
        $oodoCustomerIds = $this->oodoPush->get();
        //check if B2B customer Exists
        $this->logger->addLog('----Cron Started----');
        if(!empty($oodoCustomerIds)){
          foreach($oodoCustomerIds as $currentCustomer){
              if(!empty($currentCustomer['flag']) && !empty($currentCustomer['customer_id'])){
                  $customerId = $currentCustomer['customer_id'];

                  //get customer object from Customer Id
                  $customer = $this->customer->load($customerId);
                  $customer->cleanAllAddresses();
                  //Push the customer to Oodo
                  $oodoPushResponse = $this->oodoApi->pushCustomerToOodo($customer);

                  //Success
                  $successResponse = !empty($oodoPushResponse['result']) ? json_decode($oodoPushResponse['result'],true) : [];

                  $attempt = $currentCustomer['attempts'];
                  $attempt++;

                  $this->logger->addLog('Oodo API Response- ' . json_encode($oodoPushResponse));
                  if(!empty($oodoPushResponse['error'])){
                      $this->oodoPush->updateFailureAttempt($currentCustomer['id'], $attempt);
                  }elseif(!empty($oodoPushResponse['api_error'])){
                    $this->logger->addLog('Internal Error - ' . json_encode($oodoPushResponse['api_error']));
                      $this->oodoPush->updateFailureAttempt($currentCustomer['id'], $attempt);
                  }elseif(!empty($successResponse['customer_odoo_id'])){
                      $this->logger->addLog('Record Pushed to Oodo for Customer - ' . $customerId);
                      $customerData = $customer->getDataModel();
                      $customerData->setCustomAttribute('oodo_customer_id',$successResponse['customer_odoo_id']);
                      $customer->updateData($customerData);
                      $customerResource = $this->customerFactory->create();
                      $customerResource->saveAttribute($customer, 'oodo_customer_id');
                      $this->logger->addLog('Oodo Customer Id ' . $successResponse['customer_odoo_id'] . ' updated');
                      $this->oodoPush->deletePushedOodoData($currentCustomer['id']);
                      $this->logger->addLog($currentCustomer['id'] . ' - Deleted Pushed record');
                  }elseif($successResponse['result']){
                      $this->oodoPush->updateFailureAttempt($currentCustomer['id'], $attempt);
                      $this->logger->addLog('validation error from Oodo End - ' . $oodoPushResponse['result']);
                  }else{
                    $this->logger->addLog('No Response from Oodo');
                  }
              }
          }
        }else{
            $this->logger->addLog('No data to process');
        }

      } catch (LocalizedException $e) {
        $this->logger->addLog($e->getMessage());
      } catch (\Exception $e) {
        $this->logger->addLog($e->getMessage());
      }
      $this->logger->addLog('---Cron ended-----');
  }

  public function addLog($logdata)
  {
      if ($this->canWriteLog()) {
          $this->logger->info($logdata);
      }
  }

}
