<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\InventorySales\Plugin\Sales\OrderManagement;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionInterface;
use Magento\InventorySales\Model\CheckItemsQuantity;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Framework\Exception\LocalizedException;


/**
 * Add reservation during order placement
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AppendReservationsAfterOrderPlacementPlugin
{
    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemsToSellFactory;

    /**
     * @var CheckItemsQuantity
     */
    private $checkItemsQuantity;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var GetProductTypesBySkusInterface
     */
    private $getProductTypesBySkus;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private $isSourceItemManagementAllowedForProductType;

    /**
     * @var SalesEventExtensionFactory;
     */
    private $salesEventExtensionFactory;

    /**
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemsToSellFactory
     * @param CheckItemsQuantity $checkItemsQuantity
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param SalesEventExtensionFactory $salesEventExtensionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        WebsiteRepositoryInterface $websiteRepository,
        SalesChannelInterfaceFactory $salesChannelFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemsToSellFactory,
        CheckItemsQuantity $checkItemsQuantity,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        GetProductTypesBySkusInterface $getProductTypesBySkus,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        SalesEventExtensionFactory $salesEventExtensionFactory
    ) {
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->websiteRepository = $websiteRepository;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemsToSellFactory = $itemsToSellFactory;
        $this->checkItemsQuantity = $checkItemsQuantity;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->getProductTypesBySkus = $getProductTypesBySkus;
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->salesEventExtensionFactory = $salesEventExtensionFactory;
    }

    /**
     * Add reservation before place order
     *
     * In case of error during order placement exception add compensation
     *
     * @param OrderManagementInterface $subject
     * @param callable $proceed
     * @param OrderInterface $order
     * @return OrderInterface
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundPlace(
        OrderManagementInterface $subject,
        callable $proceed,
        OrderInterface $order
    ): OrderInterface {
        $itemsById = $itemsBySku = $itemsToSell = [];
        foreach ($order->getItems() as $item) {
            if (!isset($itemsById[$item->getProductId()])) {
                $itemsById[$item->getProductId()] = 0;
            }
            $itemsById[$item->getProductId()] += $item->getQtyOrdered();
        }
        $productSkus = $this->getSkusByProductIds->execute(array_keys($itemsById));
        $productTypes = $this->getProductTypesBySkus->execute($productSkus);

        foreach ($productSkus as $productId => $sku) {
            if (false === $this->isSourceItemManagementAllowedForProductType->execute($productTypes[$sku])) {
                continue;
            }

            $itemsBySku[$sku] = (float)$itemsById[$productId];
            $itemsToSell[] = $this->itemsToSellFactory->create([
                'sku' => $sku,
                'qty' => -(float)$itemsById[$productId]
            ]);
        }

        $websiteId = (int)$order->getStore()->getWebsiteId();
        $websiteCode = $this->websiteRepository->getById($websiteId)->getCode();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute((int)$websiteId)->getStockId(); 

        // Start Bypassing default salable check and checking only inStock/OutStock
        // $fulldir        = explode('app/code',dirname(__FILE__)); 
        // $logfilename    = $fulldir[0] . 'var/log/PlaceOrder-temp.log'; 
        // file_put_contents($logfilename, "Bypassing stock qty check : "."\r\n", FILE_APPEND); 
        // $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
        // $connection = $objectManager->create('\Magento\Framework\App\ResourceConnection');
        // $conn = $connection->getConnection(); 
        // $productIdArr = array();
        // if(isset($itemsBySku)){ 
        //     foreach ($itemsBySku as $key => $value) { 
        //         file_put_contents($logfilename, "Entered foreach : "."\r\n", FILE_APPEND); 
        //         $productId = $objectManager->create('Magento\Catalog\Model\Product')->getIdBySku($key); 
        //         $productIdArr[] = $productId;
        //         file_put_contents($logfilename, "Got productId : ".$productId."\r\n", FILE_APPEND); 
        //     } 
        //     if(count($productIdArr) > 0){ 
        //         $productIds = implode(",", $productIdArr);
        //         $query = "select is_in_stock from cataloginventory_stock_item where product_id in (".$productIds.")"; 
        //         file_put_contents($logfilename, "Query:".$query."\r\n", FILE_APPEND);
        //         $results = $conn->fetchCol($query); 
        //         file_put_contents($logfilename, "Result:".json_encode($results)."\r\n", FILE_APPEND);
        //     } 
        //     if(isset($results) && count($results) > 0 && in_array(0, $results)){ 
        //         file_put_contents($logfilename, "Error for thrown"."\r\n", FILE_APPEND); 
        //          throw new LocalizedException(__("Product is not available currently."));
        //     } 
        //     file_put_contents($logfilename, "End ByPassing : "."\r\n", FILE_APPEND); 
        //     // $this->checkItemsQuantity->execute($itemsBySku, $stockId); 
        //     // End Bypassing default salable check and checking only inStock/OutStock
        // }

        // As confirm by Ankur we are commenting product in-stock/out-of-stock checking code. 

        /** @var SalesEventExtensionInterface */
        $salesEventExtension = $this->salesEventExtensionFactory->create([
            'data' => ['objectIncrementId' => (string)$order->getIncrementId()]
        ]);

        /** @var SalesEventInterface $salesEvent */
        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_ORDER_PLACED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string)$order->getEntityId()
        ]);
        $salesEvent->setExtensionAttributes($salesEventExtension);
        $salesChannel = $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $websiteCode
            ]
        ]);

        $this->placeReservationsForSalesEvent->execute($itemsToSell, $salesChannel, $salesEvent);

        try {
            $order = $proceed($order);
        } catch (\Exception $e) {
            //add compensation
            foreach ($itemsToSell as $item) {
                $item->setQuantity(-(float)$item->getQuantity());
            }

            /** @var SalesEventInterface $salesEvent */
            $salesEvent = $this->salesEventFactory->create([
                'type' => SalesEventInterface::EVENT_ORDER_PLACE_FAILED,
                'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
                'objectId' => (string)$order->getEntityId()
            ]);
            $salesEvent->setExtensionAttributes($salesEventExtension);

            $this->placeReservationsForSalesEvent->execute($itemsToSell, $salesChannel, $salesEvent);

            throw $e;
        }
        return $order;
    }
}
