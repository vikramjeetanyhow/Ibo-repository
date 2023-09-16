<?php

namespace Ibo\Order\Cron\IR;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Ibo\Order\Model\IR\Order as IROrderModel;
use Ibo\Order\Model\IR\Api as IRApi;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Ibo\Order\Helper\Logger;

class OrderConversion {

  /**
   * Logger
   */
  protected $logger;

  /**
     * @var IROrderModel
     */
    private $irOrderModel;

  /**
   * @var IRApi
   */
  private $irApi;

  /**
   *
   * @var ScopeConfigInterface
   */
  protected $scopeConfig; 

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
   * @param IROrderModel $irOrderModel
   * @param IRApi $irApi
   * @param ScopeConfigInterface $scopeConfig
   * @param Customer $customer
   * @param CustomerFactory $customerFactory
   */
  public function __construct(
      Logger $logger,
      IROrderModel $irOrderModel,
      IRApi $irApi,
      ScopeConfigInterface $scopeConfig,
      Customer $customer,
      CustomerFactory $customerFactory
  ) {
    $this->logger = $logger;
    $this->irOrderModel = $irOrderModel;
    $this->irApi = $irApi;
    $this->scopeConfig = $scopeConfig;       
    $this->customer = $customer;    
    $this->customerFactory = $customerFactory;    
  }

  /**
   * Customer push to Oodo
   * @return void
   */
  public function execute()
  {   
      $isCronEnable = $this->scopeConfig->getValue("sales_order_partner/ir_conversion/cron_status");
      if (!$isCronEnable) {
          $this->logger->addLog("IR Order Conversion Cron not enabled");
          return;
      }
      try {
        //get IR order converion Order Info
        $orderInfo = $this->irOrderModel->getOrderConversionInfo();  

        //check if Order Info exists in an array
        $this->logger->addLog('----Cron Started----');
        if(!empty($orderInfo)){
          foreach($orderInfo as $currentOrder){
              //Push the order to IR
              $orderIrResponse = $this->irApi->pushOrderToIR($currentOrder);
              
              $this->logger->addLog('Order Conversion API Response- ' . json_encode($orderIrResponse));
              if(!empty($orderIrResponse['status']) && $orderIrResponse['status'] != 200){
                $this->logger->addLog('IR Error with code - ' . $orderIrResponse['status']);
              }elseif(!empty($orderIrResponse['api_error'])){
                $this->logger->addLog('Internal API Error - ' . json_encode($orderIrResponse['api_error']));
              }elseif(!empty($orderIrResponse['status']) && $orderIrResponse['status'] == 200){
                  $this->logger->addLog('Record Pushed to IR for Order - ' . $currentOrder['increment_id']);
                  $this->logger->addLog($currentOrder['entity_id'] . ' - Deleted Pushed record');
              }else{
                $this->logger->addLog('No Response from IR');
              }
              if(!empty($orderIrResponse['status']) && !empty($currentOrder['entity_id'])){
                $this->irOrderModel->updatePushedIRData($currentOrder['entity_id'],$orderIrResponse['status']);
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