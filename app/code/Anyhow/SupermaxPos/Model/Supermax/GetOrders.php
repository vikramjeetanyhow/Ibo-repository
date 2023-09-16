<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class GetOrders implements \Anyhow\SupermaxPos\Api\Supermax\GetOrdersInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->supermaxUser = $supermaxUser;
        $this->orderRepository = $orderRepository;
        $this->currency = $currency;
        $this->storeManager = $storeManager;
        $this->supermaxSession = $supermaxSession;
        $this->timezone = $timezone;
    }

    /**
     * GET for Post api
     * @api
     * @param string $startdate
     * @param string $enddate
     * @return string
     */
    public function getOrders($startdate, $enddate)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($startdate) && !empty($enddate)){
                    $endDateUpdated = date('Y-m-d H:i:s', strtotime($enddate . ' +1 day'));
                    $storeViewId = '';
                    $userId = $this->supermaxSession->getPosUserId();
                    $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $userId);
                    $storeViewData = $storeView->getData();

                    if(!empty($storeViewData)) {
                        $storeViewId = $storeViewData[0]['store_view_id'];
                    }
                    $connection= $this->resource->getConnection();
                    $now = new \DateTime();
                    $collection = $this->_orderCollectionFactory->create()
                                    ->addAttributeToSelect('*')
                                    ->addFieldToFilter('store_id', $storeViewId)
                                    ->addFieldToFilter('created_at', [
                                        'from' => [$startdate],
                                        'to' => [$endDateUpdated]
                                    ])
                                    ->setOrder('order_id','DESC');
                    
                    /* join with ah_supermax_pos_orders table */
                    $supermaxOrderTable = $this->resource->getTableName("ah_supermax_pos_orders");
                    $collection->getSelect()
                        ->join(
                            ["spo" => $supermaxOrderTable],
                            'main_table.entity_id = spo.order_id'
                        )->where('spo.pos_user_id = ?', $userId);

                    $orders = array();
                    $storeCurrencySymbol = '';
                    foreach($collection as $order){
                        $orderItems = array();
                        $userFirstname = '';
                        $userLastname = '';
                        $orderId = $order->getEntityId();
                        $orderUserId = $order->getPosUserId();
                        $orderUser = $this->supermaxUser->addFieldToFilter('pos_user_id', $orderUserId);
                        $orderUserData = $orderUser->getData();

                        if(!empty($orderUserData)) {
                            $userFirstname = $orderUserData[0]['firstname'];
                            $userLastname = $orderUserData[0]['lastname'];
                        }

                        $orderCurrency = $order->getOrderCurrencyCode();
                        $barcode = $order->getBarcode();
                        $barcodeImageUrl = $this->storeManager->getStore()->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'/supermax/barcode/'.$barcode.'.jpg';
                        
                        if(!empty($orderCurrency)) {
                            $storeCurrencySymbol = $this->currency->getCurrency($orderCurrency)->getSymbol();
                        }

                        $orderItemDatas = $this->orderRepository->get($orderId);

                        foreach($orderItemDatas->getAllVisibleItems() as $orderItem){
                            if($orderItem->getProductType() == 'bundle'){
                                continue;
                            }
                            $orderItems[] = array(
                                'item_id' => (int)$orderItem->getProductId(),
                                'order_item_id' => (int)$orderItem->getItemId(),
                                'item_name' => html_entity_decode($orderItem->getName()),
                                'item_sku' => html_entity_decode($orderItem->getSku()),
                                'item_type' => html_entity_decode($orderItem->getProductType()),
                                'item_original_price' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getOriginalPrice(),2),2)),
                                'item_price' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getPrice(),2),2)),
                                'item_ordered_quantity' => (int)$orderItem->getQtyOrdered(),
                                'item_row_subtotal' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getRowTotal(),2),2)),
                                'item_row_tax_amount' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getTaxAmount(),2),2)),
                                'item_row_tax_percent' => (float)$orderItem->getTaxPercent(),
                                'item_row_dicount_amount' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getDiscountAmount(),2),2)),
                                'item_row_total' => html_entity_decode($storeCurrencySymbol.number_format(round($orderItem->getRowTotalInclTax(),2),2)),
                                'return_id' => (int)$this->getReturnId($orderId, $orderItem->getProductId())
                            );
                        }
                        $orders[] = array(
                            'order_id' => (int)$orderId,
                            'order_increment_id' => (int)$order->getIncrementId(),
                            'order_subtotal' => html_entity_decode($storeCurrencySymbol.number_format(round($order->getSubtotal(),2),2)),
                            'order_subtotal_incl_tax' => html_entity_decode($storeCurrencySymbol.number_format(round($order->getSubtotal()+$order->getTaxAmount(),2),2)),
                            'order_discount_amount' => html_entity_decode($storeCurrencySymbol.round($order->getDiscountAmount(),2)),
                            'order_tax_amount' => html_entity_decode($storeCurrencySymbol.number_format(round($order->getTaxAmount(),2),2)),
                            'order_shipping_and_handling_amount' => html_entity_decode($storeCurrencySymbol.number_format(round($order->getShippingAmount(),2),2)),
                            'order_total_amount' => html_entity_decode($storeCurrencySymbol.number_format(round($order->getGrandTotal(),2),2)),
                            'order_status' => html_entity_decode($order->getStatus()),
                            'order_date' => $this->timezone->date(new \DateTime(
                                $order->getCreatedAt()))->format('Y-m-d h:i:s A'), //19:24:15 06/13/2013
                            'customer_id' => (int)$order->getCustomerId(),
                            'customer_firstname' => html_entity_decode($order->getCustomerFirstname()),
                            'customer_lastname' => html_entity_decode($order->getCustomerLastname()),
                            'customer_email' => html_entity_decode($order->getCustomerEmail()),
                            'barcode' => html_entity_decode($barcode),
                            'barcode_url' => html_entity_decode($barcodeImageUrl),
                            'order_items' => $orderItems,
                            'order_tax' => $this->getOrderTax($orderId, $storeCurrencySymbol),
                            'user_name' => html_entity_decode($userFirstname.' '.$userLastname)                              
                        );
                    }
                    $result = array(
                        'filter_date_start' => html_entity_decode($startdate),
                        'filter_date_end' => html_entity_decode($enddate),
                        'orders' => $orders
                    );
                }
                
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }

    public function getOrderTax($orderId, $storeCurrencySymbol){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_order_tax'); 

        $sql = "SELECT * FROM " . $tableName . " WHERE order_id='" . $orderId . "'";
        $results = $connection->fetchAll($sql); 
        $taxes = array();
        if($results){
            foreach($results as $result){
                $taxes[] = array(
                    'title' => ((int)$result['percent']) ? html_entity_decode($result['title'].'(' . round($result['percent'],2) . '%)') : html_entity_decode($result['title']),
                    'amount' => html_entity_decode($storeCurrencySymbol.number_format(round($result['amount'] , 2),2)),
                );
            }
        }
        return $taxes;
    }

    public function getReturnId($orderId, $productId) {
        $return_id = 0;
        $rmaStatus = (bool)$this->helper->getConfig('ah_supermax_pos_rma_configuration/ah_supermax_pos_rma_basic_configutaion/ah_supermax_pos_rma_status');
        if($rmaStatus){
            $connection = $this->resource->getConnection();
            $select = $connection->select();
            $select->from(
                ['rr' => $this->resource->getTableName('ah_supermax_pos_rma_request')]
            )->joinLeft(
                ['rrp' => $this->resource->getTableName('ah_supermax_pos_rma_request_product')],
                "rr.pos_rma_request_id = rrp.request_id"
            )
            ->where("rr.order_id = $orderId") 
            ->where("rrp.product_id = $productId");

            $returnData = $connection->query($select)->fetch();
            if(!empty($returnData)){
                $return_id = $returnData['pos_rma_request_id'];
            }
        }

        return $return_id;
    }
}



