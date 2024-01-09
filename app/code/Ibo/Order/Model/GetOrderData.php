<?php 
namespace Ibo\Order\Model;

use Ibo\Order\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface as orderRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class GetOrderData implements OrderRepositoryInterface {

    CONST FRACTION = 10000;
    
    /**
    * @var OrderRepositoryInterface
    */
    protected $orderRepository;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $CustomerFactory,
        orderRepository $orderRepository,
        OrderFactory $orderFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->_storeManager=$storeManager;
        $this->customerFactory = $CustomerFactory;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->_productFactory = $productFactory;
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    public function getMerchandisingRootId()
    {
        $this->merchRootId = "";
        $collection = $this->categoryFactory->create()->getCollection()
                ->addFieldToFilter('name','Merchandising Category');       
        if ($collection->getSize()) {
            $this->merchRootId = $collection->getFirstItem()->getId();                  
        }
    }

    public function getMerchandisingCat($CategoriesIds)
    {
        $this->getMerchandisingRootId();
        $catcollection = $this->categoryCollectionFactory
                    ->create()                   
                    ->addFieldToFilter('entity_id',['in'=>$CategoriesIds])                 
                    ->addFieldToFilter('path', array('like'=> "1/$this->merchRootId/%"));
          
        foreach ($catcollection->getData() as $merchCatId) {

            //Product Category Id
            return $this->getCategoryLoad($merchCatId['entity_id']);
        }
    }

    /***
     * get all data using Category Id load
     */
    public function getCategoryLoad($productCategoryId){
        $collection = $this->categoryFactory->create()->load($productCategoryId);
        return $collection->getData();
    }


    public function getOrderData(){
        $this->addLog("==========================================");
        $this->addLog("Start Get Order data API");
        $error = "";

        try {
            $params = $this->helper->getParams();
            $orderModel = $this->orderFactory->create();
            $orderModel_load = $orderModel->loadByIncrementId($params['order_number']);
           echo $orderId = $orderModel_load->getId();
            $orderRepository = $this->orderRepository->get($orderId);
            
            $customerEmail = $orderRepository->getCustomerEmail();
            $customerId = $orderRepository->getCustomerId();


            $CustomerModel = $this->customerFactory->create()->getCollection();
            $CustomerModel->getSelect()->joinLeft(
                ['cg'=>'customer_group'],
                'e.group_id = cg.customer_group_id',
                ['cg.customer_group_code','cg.tax_class_id']
            )->where("e.entity_id='$customerId'");


            //get customer details
            $customerdetails = $CustomerModel->getData()[0];
            $customergroup = $customerdetails['customer_group_code'] =="B2B"?true:false;

            $customer['customer'] =  array(
                "customer_id"=>$customerdetails['entity_id'],
                "customer_group"=>$customerdetails['customer_group_code'],
                "customer_name"=>array(
                    "salutation"=> $customerdetails['suffix'],
                    "first_name"=> $customerdetails['firstname'],
                    "middle_name"=>$customerdetails['middlename'],
                    "last_name"=> $customerdetails['lastname']
                ),
                "email_id"=>$customerdetails['email'],
                "phone_number"=> array(
                    "country_code"=> "+91",
                    "number" => $customerdetails['mobilenumber'],   
                ),
                "is_b2b_customer"=> $customergroup,

            );
           
            //Get Shipping Address data by order id
            $shippingAddress = $orderRepository->getShippingAddress();
            $countryCode = $shippingAddress->getCountryId()=="IN"?"+91":"";
            $shippingData['shipping_address'] = array(
                "address_id"=> $shippingAddress->getQuoteAddressId(),
                "address_line1"=> $shippingAddress->getStreet()[0],
                "address_line2"=> !empty($shippingAddress->getStreet()[1])?$shippingAddress->getStreet()[1]:"",
                "address_line3"=> !empty($shippingAddress->getStreet()[2])?$shippingAddress->getStreet()[2]:"",
                "landmark"=> $shippingAddress->getLandmark(),
                "country_code"=> $countryCode,
                "state_code"=> $shippingAddress->getRegionId(),
                "city"=> $shippingAddress->getCity(),
                "state"=> $shippingAddress->getRegion(),
                "country"=> $shippingAddress->getCountryId(),
                "phone_number"=> array(
                    "country_code"=> $countryCode,
                    "number"=> $shippingAddress->getTelephone()
                )
            );


            //Get Billing Address data by order id
            $BillingAddress = $orderRepository->getBillingAddress();
            $BillingData['billing_address'] = array(
                "address_id"=> $BillingAddress->getQuoteAddressId(),
                "address_line1"=> $BillingAddress->getStreet()[0],
                "address_line2"=> !empty($BillingAddress->getStreet()[1])?$BillingAddress->getStreet()[1]:"",
                "address_line3"=> !empty($BillingAddress->getStreet()[2])?$BillingAddress->getStreet()[2]:"",
                "landmark"=> $BillingAddress->getLandmark(),
                "country_code"=> $countryCode,
                "state_code"=> $BillingAddress->getRegionId(),
                "city"=> $BillingAddress->getCity(),
                "state"=> $BillingAddress->getRegion(),
                "country"=> $BillingAddress->getCountryId(),
                "phone_number"=> array(
                    "country_code"=> $countryCode,
                    "number"=> $BillingAddress->getTelephone()
                )
            );

            // Get Product details by Id that get from allItems 
            $orderItems = $orderRepository->getAllItems();
            $productsArry = array();
            foreach ($orderItems as $key => $item) {
                $ProductId = $item->getProductId();
    
                $productDetails = $this->_productFactory->create()->load($ProductId);

                $quantityUom = $productDetails->getAttributeText('sale_uom');
                if($orderRepository->getOrderChannel() == 'STORE') {
                    $quantityUom = $productDetails->getData('sale_uom');
                }
                $serviceCategory = ($productDetails->getServiceCategory()) ? $productDetails->getServiceCategory() : "NATIONAL";
                $rowAmount = $item->getBaseRowTotalInclTax()*self::FRACTION;
                $unitePrice = $rowAmount/$item->getQtyOrdered();
                $appliedRule = $item->getAppliedRuleIds();
                $totalDiscount = 0;
                if($orderRepository->getDiscountAmount() != null){
                    $discountAmount = $orderRepository->getDiscountAmount();
                    $totalDiscount = $discountAmount * self::FRACTION;
                }
                $ItemDiscountAmount = 0;
                if (!empty($appliedRule)) {
                    $ItemDiscountAmount = $item->getDiscountAmount();
                    $ItemDiscountAmount = ($ItemDiscountAmount >0)?$ItemDiscountAmount/$item->getQtyOrdered():$ItemDiscountAmount;
                    $ItemDiscountAmount = number_format($ItemDiscountAmount,4,'.','');
                    $ItemDiscountAmount = $ItemDiscountAmount*self::FRACTION;
                }

                $tempArray[$item->getSku()]= array(
                    'offer_id' => !empty($item->getSku()) ?  $item->getSku() : '',
                    'ebo_title' => !empty($item->getName()) ?  $item->getName() : '',
                    'quantity' => array(
                        'quantity_number' => !empty((int)$item->getQtyOrdered()) ?  (int)$item->getQtyOrdered() : 0,
                        'quantity_uom' => !empty($quantityUom) ?  $quantityUom : "",
                    ),
                    'unit_price' => array(
                        'cent_amount' => $unitePrice,
                        'currency' => "INR",
                        'fraction' => self::FRACTION,
                    ),
                    'unit_mrp' => array(
                        'cent_amount' => !empty($item->getIboMrp()) ? $item->getIboMrp() * self::FRACTION : 0,
                        'currency' => 'INR',
                        'fraction' => self::FRACTION
                    ),
                    'discount_total' => array(
                        'cent_amount' => $ItemDiscountAmount,
                        'currency' => 'INR',
                        'fraction' => self::FRACTION
                    ),
                    'grand_total' => array(
                        'cent_amount' => $rowAmount,
                        'currency' => 'INR',
                        'fraction' => self::FRACTION
                    )
                );
            }
            

            //get Order details
            $orderPromise = json_decode($orderRepository->getPromiseOptions(),true);
            $payment = $orderRepository->getPayment();
            
            $distributionOrder = array();
            $deliveryGroup = json_decode($orderRepository->getDelivery_group(), true);
            if (!(empty($deliveryGroup))) {
                foreach ($deliveryGroup as $newkey => $delivery) {
                    $distributionOrder[$newkey] = array(
                        "do_number" => $delivery["delivery_group_number"],
                        "store_fulfillment_mode" => ($delivery["promise_options"][0]["delivery_method"] == "CLICK_AND_COLLECT") ? "CNC" : "DWH",
                    );
                    foreach($delivery['delivery_group_lines'] as $deliveygroup_line) {
                        if(array_key_exists($deliveygroup_line['item']['offer_id'], $tempArray)) {
                            $distributionOrder[$newkey]['items'][] = $tempArray[$deliveygroup_line['item']['offer_id']];
                        }
                    }
                }
            }

            
            $Orderdetails['order_details'] = array(
                "order_number"=>$orderRepository->getIncrementId(),
                "order_created_at"=>$orderRepository->getCreatedAt(),
                "node_id"=>$orderPromise[0]['node_id'],
                "payment_type"=> $payment->getMethod(),
                "distribution_order"=> $distributionOrder,
                "payment_methods" => ""
            );

            $totalDiscount=0;
            if($orderRepository->getDiscountAmount() != null){
                $discountAmount = $orderRepository->getDiscountAmount() * (-1);
                $totalDiscount = $discountAmount*self::FRACTION;
            }
            
            $dataArray['totals'] =[
                [
                    'type' => "SHIPPING_TOTAL",
                    'amount' => [
                      'cent_amount' => $orderRepository->getShippingAmount()*self::FRACTION,
                      'currency' => "INR",
                   'fraction' =>  self::FRACTION
                    ],
                  ],
                  [
                    'type' => "DISCOUNT_TOTAL",
                    'amount' => [
                      'cent_amount' =>$totalDiscount,
                      'currency' => "INR",
                   'fraction' =>  self::FRACTION
                    ],
                  ],
                  [
                    'type' => "ITEM_TOTAL",
                    'amount' => [
                      'cent_amount' => $orderRepository->getSubtotalInclTax()*self::FRACTION,
                      'currency' => "INR",
                   'fraction' =>  self::FRACTION
                    ],
                  ],
                  [
                    'type' => "TAX_TOTAL",
                    'amount' => [
                      'cent_amount' => $orderRepository->getTaxAmount()*self::FRACTION,
                      'currency' => "INR",
                   'fraction' =>  self::FRACTION
                    ],
                  ],
                  [
                    'type' => "GRAND_TOTAL",
                    'amount' => [
                      'cent_amount' => $orderRepository->getGrandTotal()*self::FRACTION,
                      'currency' => "INR",
                   'fraction' =>  self::FRACTION
                    ],
                  ],
    
            ];

            $connection = $this->resourceConnection->getConnection();
            $supermaxOrderTable = $this->resourceConnection->getTableName("ah_supermax_pos_orders");
            $supermaxUserTable = $this->resourceConnection->getTableName("ah_supermax_pos_user");
            $supermaxOutletTable = $this->resourceConnection->getTableName("ah_supermax_pos_outlet");
            $receiptTable = $this->resourceConnection->getTableName('ah_supermax_pos_receipt');
            $receiptStoreTable = $this->resourceConnection->getTableName('ah_supermax_pos_receipt_store');
            $receiptId = 0;
            $storeDetails = array();
            $posOrderData = $connection->query("SELECT CONCAT(su.`firstname`, ' ', su.`lastname`) AS cashier_name, sou.`pos_receipt_id`, so.`payment_method`, so.`pos_user_id` FROM $supermaxOrderTable AS so LEFT JOIN $supermaxUserTable AS su ON(so.`pos_user_id`= su.`pos_user_id`) LEFT JOIN $supermaxOutletTable AS sou ON(so.`pos_outlet_id` = sou.`pos_outlet_id`) WHERE so.`order_id`=$orderId")->fetch();
            if(!empty($posOrderData)) {
                $receiptId = (int)$posOrderData['pos_receipt_id'];
                $storeDetails['cashier'] = array(
                    "id" => $posOrderData['pos_user_id'],
                    "name" => $posOrderData['cashier_name']
                );
                $Orderdetails['order_details']['payment_methods'] = $posOrderData['payment_method'];
            }
            $receiptAllStoreData = $connection->query("SELECT * FROM $receiptStoreTable AS rst WHERE rst.`receipt_id`=$receiptId AND rst.`store_id`=0")->fetch();
            if(!empty($receiptAllStoreData)) {
                $storeDetails['cin_number'] = $receiptAllStoreData['header_details'];
            }

            $OrderResponceCombine = array(
                "customer"=>$customer['customer'],
                "shipping_address"=>$shippingData['shipping_address'],
                "billing_address"=>$BillingData['billing_address'],
                "order_details"=>$Orderdetails['order_details'],
                "totals"=>$dataArray['totals'],
                "store_details"=>$storeDetails
            );
           
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->addLog($error);
        }

        $this->addLog("End order data get API");
        $this->addLog("==========================================");
        $data = array("error" => !empty($error) ? true : false, "OrderResponse"=>!empty($OrderResponceCombine)?$OrderResponceCombine:"", "message" => !empty($error) ? $error : "get Order API Successfully.");
        return [$data]; 
    }


    public function addLog($logData) {
        $fileName = "getOrderDataAPI.log";
        if ($this->canWriteLog($fileName)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename) {
        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }
        return $logEnable;
    }

}