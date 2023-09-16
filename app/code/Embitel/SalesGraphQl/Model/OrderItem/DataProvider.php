<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\SalesGraphQl\Model\OrderItem;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\SalesGraphQl\Model\OrderItem\OptionsProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Data provider for order items
 */
class DataProvider extends \Magento\SalesGraphQl\Model\OrderItem\DataProvider
{
    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OptionsProcessor
     */
    private $optionsProcessor;

    /**
     * @var int[]
     */
    private $orderItemIds = [];

    /**
     * @var array
     */
    private $orderItemList = [];
    /**
     * @var ImageFactory
     */
    protected $_productFactory;
    public $imageFactory;
    protected $_productRepositoryFactory;
    protected $storeManager;
     /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;
    /**
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param ProductRepositoryInterface $productRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OptionsProcessor $optionsProcessor
     */
    public function __construct(
        OrderItemRepositoryInterface $orderItemRepository,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ImageFactory $imageFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
        OptionsProcessor $optionsProcessor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->orderItemRepository = $orderItemRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->optionsProcessor = $optionsProcessor;
        $this->imageFactory = $imageFactory;
        $this->imageHelper = $imageHelper;
        $this->appEmulation = $appEmulation;
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->_productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Add order item id to list for fetching
     *
     * @param int $orderItemId
     */
    public function getProductImages($productId) {

        $_product = $this->_productFactory->create()->load($productId);
        $productImages = $_product->getMediaGalleryImages();
        return $productImages;
       }

    public function addOrderItemId(int $orderItemId): void
    {
        if (!in_array($orderItemId, $this->orderItemIds)) {
            $this->orderItemList = [];
            $this->orderItemIds[] = $orderItemId;
        }
    }

    /**
     * Get order item by item id
     *
     * @param int $orderItemId
     * @return array
     */
    public function getOrderItemById(int $orderItemId): array
    {
        $orderItems = $this->fetch();
        if (!isset($orderItems[$orderItemId])) {
            return [];
        }
        return $orderItems[$orderItemId];
    }

    /**
     * Fetch order items and return in format for GraphQl
     *
     * @return array
     */
    private function fetch()
    {
        if (empty($this->orderItemIds) || !empty($this->orderItemList)) {
            return $this->orderItemList;
        }

        $itemSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderItemInterface::ITEM_ID, $this->orderItemIds, 'in')
            ->create();

        $orderItems = $this->orderItemRepository->getList($itemSearchCriteria)->getItems();
        $productList = $this->fetchProducts($orderItems);
        $orderList = $this->fetchOrders($orderItems);

        foreach ($orderItems as $orderItem) {

            /** @var ProductInterface $associatedProduct */
            $associatedProduct = $productList[$orderItem->getProductId()] ?? null;
            /** @var OrderInterface $associatedOrder */
            $associatedOrder = $orderList[$orderItem->getOrderId()];
            $itemOptions = $this->optionsProcessor->getItemOptions($orderItem);
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->get($orderItem->getSku());
            $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
            $imageUrl = $this->imageHelper->init($product, 'product_base_image')->getUrl();
            $baseImgUrl = '';
            if($product->getBaseImageCustom() != '') {
                $baseImgUrl = $product->getBaseImageCustom();
            }
            $this->orderItemList[$orderItem->getItemId()] = [
                'id' => base64_encode($orderItem->getItemId()),
                'associatedProduct' => $associatedProduct,
                'model' => $orderItem,
                'product_name' => $orderItem->getName(),
                'product_sku' => $orderItem->getSku(),
                'product_image' => $imageUrl,
                'image_custom' => $baseImgUrl,
                'product_url_key' => $associatedProduct ? $associatedProduct->getUrlKey() : null,
                'product_type' => $orderItem->getProductType(),
                'esin' => !empty($product->getEsin()) ?  $product->getEsin() : '',
                'status' => $orderItem->getStatus(),
                'discounts' => $this->getDiscountDetails($associatedOrder, $orderItem),
                'product_sale_price' => [
                    'value' => $orderItem->getPrice(),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ],
                'product_sale_price_incl_tax' => [
                    'value' => $orderItem->getPriceInclTax(),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ],
                'selected_options' => $itemOptions['selected_options'],
                'entered_options' => $itemOptions['entered_options'],
                'quantity_ordered' => $orderItem->getQtyOrdered(),
                'quantity_shipped' => $orderItem->getQtyShipped(),
                'quantity_refunded' => $orderItem->getQtyRefunded(),
                'quantity_invoiced' => $orderItem->getQtyInvoiced(),
                'quantity_canceled' => $orderItem->getQtyCanceled(),
                'quantity_returned' => $orderItem->getQtyReturned()
            ];
            $this->appEmulation->stopEnvironmentEmulation();
        }

        return $this->orderItemList;
    }

     /** @var array */
     private static $catalogImageLabelTypes = [
        'image' => 'image_label',
        'small_image' => 'small_image_label',
        'thumbnail' => 'thumbnail_label'
    ];

    /**
     * Fetch associated products for order items
     *
     * @param array $orderItems
     * @return array
     */
    private function fetchProducts(array $orderItems): array
    {
        $productIds = array_map(
            function ($orderItem) {
                return $orderItem->getProductId();
            },
            $orderItems
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();
        $productList = [];
        foreach ($products as $product) {
            $productList[$product->getId()] = $product;
        }
        return $productList;
    }

    /**
     * Fetch associated order for order items
     *
     * @param array $orderItems
     * @return array
     */
    private function fetchOrders(array $orderItems): array
    {
        $orderIds = array_map(
            function ($orderItem) {
                return $orderItem->getOrderId();
            },
            $orderItems
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $orderIds, 'in')
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        $orderList = [];
        foreach ($orders as $order) {
            $orderList[$order->getEntityId()] = $order;
        }
        return $orderList;
    }

    /**
     * Returns information about an applied discount
     *
     * @param OrderInterface $associatedOrder
     * @param OrderItemInterface $orderItem
     * @return array
     */
    private function getDiscountDetails(OrderInterface $associatedOrder, OrderItemInterface $orderItem) : array
    {
        if ($associatedOrder->getDiscountDescription() === null && $orderItem->getDiscountAmount() == 0
            && $associatedOrder->getDiscountAmount() == 0
        ) {
            $discounts = [];
        } else {
            $discounts [] = [
                'label' => $associatedOrder->getDiscountDescription() ?? __('Discount'),
                'amount' => [
                    'value' => abs($orderItem->getDiscountAmount()) ?? 0,
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ]
            ];
        }
        return $discounts;
    }
}
