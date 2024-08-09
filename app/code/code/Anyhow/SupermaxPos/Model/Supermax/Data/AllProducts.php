<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

//  Api to get all products
namespace Anyhow\SupermaxPos\Model\Supermax\Data;

use Magento\Framework\Filesystem;
use Magento\Framework\Url;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Filesystem\DirectoryList;

class AllProducts implements \Anyhow\SupermaxPos\Api\Supermax\AllProductsInterface
{
    /** @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory */
    protected $productCollectionFactory;
    
    /** @var \Anyhow\SupermaxPos\Model\Supermax\ProductFactory $_productFactory */
    protected $_productFactory;

    /** @var \Anyhow\SupermaxPos\Helper\Data $helper */
    protected $productHelper;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Anyhow\SupermaxPos\Model\Supermax\ProductFactory $productFactory,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\Collection $supermaxOutlet,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        // \Magento\InventoryCatalogAdminUi\Model\GetSourceItemsDataBySku $sourceDataBySku,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Bundle\Model\Product\Type $bundleChildProducts,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurableChildProducts,
        \Magento\GroupedProduct\Model\Product\Type\Grouped $groupedChildProducts,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $productOptions,
        \Magento\Catalog\Model\Product $productData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,

        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\ResponseInterface $response,
        \Magento\Framework\App\Config\Storage\WriterInterface $resourceConfig,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Image\AdapterFactory $imageFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockItemRepository,
        \Magento\Catalog\Model\Category $modelCategory,
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Embitel\ProductImport\Model\CategoryProcessor $categoryProcessor,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Embitel\ProductImport\Model\Import\ProductFieldProcessor $productFieldProcessor
    ) {
        $this->_productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->helper = $helper;
        $this->supermaxUser = $supermaxUser;
        $this->supermaxOutlet = $supermaxOutlet;
        // $this->sourceDataBySku = $sourceDataBySku;
        $this->resource = $resourceConnection;
        $this->bundleChildProducts = $bundleChildProducts;
        $this->configurableChildProducts = $configurableChildProducts;
        $this->groupedChildProducts = $groupedChildProducts;
        $this->productOptions = $productOptions;
        $this->productData = $productData;
        $this->storeManager = $storeManager;
        $this->priceCurrencyInterface = $priceCurrencyInterface;
        $this->supermaxSession = $supermaxSession;
        $this->productFieldProcessor = $productFieldProcessor;

        $this->scopeConfig = $scopeConfig;
        $this->messageManager=$messageManager;
        $this->_response=$response;
        $this->_resourceConfig=$resourceConfig;
         $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->_filesystem = $filesystem;
        $this->_directory = $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->_imageFactory = $imageFactory;
        $this->stockItemRepository = $stockItemRepository;
        $this->modelCategory = $modelCategory;
        $this->setup = $setup;
        $this->objectManager = $objectManager;
        $this->categoryProcessor = $categoryProcessor;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Get Api data.
     * @api
     * @param int $page
     * @return string
     */
    public function getAllProducts($page)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                $allProductData = array();
                $totalProducts = 0;
                if(isset($params['sku']) && !empty($params['sku'])) {
                    $storeCurrencyCode = $storeBaseCurrencyCode = '';
                    $storeViewId = $websiteId = 0;
                    $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                    $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                    $storeViewData = $storeView->getData();
                    $customerGroup = isset($params['customer_group']) ? $params['customer_group'] : 0;
                    $priceZone = isset($params['price_zone']) ? $params['price_zone'] : "DEFAULT";
                    $quantity = isset($params['quantity']) ? $params['quantity'] : 1;

                    if(!empty($storeViewData)) {
                        $storeViewId = $storeViewData[0]['store_view_id'];
                        $storeData = $this->storeManager->getStore($storeViewId);
                        if(!empty($storeData)) {
                            $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                            $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                            $websiteId = $storeData->getWebsiteId();
                        }
                        $collection = $this->productCollectionFactory->create()
                            ->addAttributeToSelect('*')
                            ->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                            ->addAttributeToFilter('sku', $params['sku'])
                            ->addAttributeToFilter('type_id', 'simple');
                        $products = $collection->load()->getItems();

                        if(!empty($products)) {
                            foreach($products as $product) {
                                $allProductData[] = array(
                                    'product_id' => (int)$product->getId(),
                                    'status' => (bool)$product->getStatus(),
                                    'short_name' => html_entity_decode($product->getName()), 
                                    'ebo_title' => html_entity_decode($product->getName()),
                                    'sku' => html_entity_decode($product->getSku()),
                                    'offer_id' => html_entity_decode($product->getSku()),
                                    'type' => html_entity_decode($product->getTypeId()),
                                    'childProducts' => array(),
                                    'barcode' => html_entity_decode($product->getBarcode()),
                                    'media' => array("0" => array("position" => 0, "url" => "")),
                                    'is_qty_decimal'=> false,
                                    'baseCost' => (float)$this->helper->convert((float)$product->getPrice(), $storeBaseCurrencyCode, $storeCurrencyCode),
                                    'cost' => (float)$this->helper->convert((float)$product->getPrice(), $storeBaseCurrencyCode, $storeCurrencyCode),
                                    'mrp' => (float)$this->helper->convert((float)$product->getMrp(), $storeBaseCurrencyCode, $storeCurrencyCode),
                                    'dynamic_price' => '',
                                    'special' => $this->getProductSpecialPrice($product, $storeViewId, $storeCurrencyCode, $storeBaseCurrencyCode),
                                    'discounts' => $this->getTierPriceTable($product->getId()),
                                    'quantity' => 100,
                                    'tax_class_id' => (int)$product->getTaxClassId(),
                                    'attributes' => array(),
                                    'options' => $this->getProductOptions($product, $storeBaseCurrencyCode, $storeCurrencyCode),
                                    'variations' => array(),
                                    'bundle_options' => array(),
                                    'catalog_prices' => array(),
                                    'category_id' => $this->getCategoryIds($product),
                                    'is_bom' => ($product->getIsBom()) ? true : false,
                                    'brand_id' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                                    'brand_name' => !empty($product->getAttributeText('brand_Id')) ? $this->productFieldProcessor->getBrandNameById(strtolower($product->getAttributeText('brand_Id'))) : '',
                                    'seller_id' =>!empty($product->getSellerId()) ?  $product->getSellerId() : '',
                                    'is_lot_controlled' => ($product->getIsLotControlled()) ? true : false,
                                    'quantity_uom' => $product->getAttributeText('sale_uom'),
                                    'service_category' => $product->getServiceCategory(),
                                    'store_fulfilment_mode' => $this->getFulfilmentOption($product),
                                    'courier_type' => ($product->getCourierType()) ? $product->getCourierType() : 'F',
                                    "prices" => $this->getProductPrices($product, $customerGroup, $priceZone, $quantity),
                                    'ean' => $product->getEan(),
                                    "package_dimensions" => array(
                                        "height_in_cm"  => $product->getPackageHeightInCm(),
                                        "length_in_cm"  => $product->getPackageLengthInCm(),
                                        "width_in_cm"   => $product->getPackageWidthInCm(),
                                        "weight_in_kg"  => $product->getPackageWeightInKg()
                                    )
                                );
                            }
                            $totalProducts = 1;
                        }
                    }
                }

                $result = array(
                    'products' => $allProductData, 
                    'total' => $totalProducts
                );

                // Get all products and get connection updated product commented due to performance issue.
                // // check connection update data.
                // $connectionId = $this->supermaxSession->getPosConnectionId();
                // $code = 'product';
                // $conncetionUpdateData = $this->helper->checkConnectionUpdate($connectionId, $code);
                // $allProductData = array();
                // $totalProducts = null;
                // 
                // $productIds = array();

                // if(!empty($params)){
                //     $productIds = $params['products'];
                // }

                // if($productIds || ($conncetionUpdateData && is_null($conncetionUpdateData[0]['update']))){
                //     $storeCurrencyCode = '';
                //     $storeViewId = '';
                //     $websiteId = 0;
                //     $supermaxPosUserId = $this->supermaxSession->getPosUserId();
                //     $storeView = $this->supermaxUser->addFieldToFilter('pos_user_id', $supermaxPosUserId);
                //     $storeViewData = $storeView->getData();

                //     if(!empty($storeViewData)) {
                //         $storeViewId = $storeViewData[0]['store_view_id'];
                //         $userOutletId = $storeViewData[0]['pos_outlet_id'];
                //         $connection = $this->resource->getConnection();

                //         // Store Data
                //         $storeData = $this->storeManager->getStore($storeViewId);
                //         if(!empty($storeData)) {
                //             $storeCurrencyCode = $storeData->getCurrentCurrencyCode();
                //             $storeBaseCurrencyCode = $storeData->getBaseCurrencyCode();
                //             $websiteId = $storeData->getWebsiteId();
                //         }

                //         // get product Assignment Basis and product ids array accordingly.
                //         $outletProductData = array();
                //         $userOutlet = $this->supermaxOutlet->addFieldToFilter('pos_outlet_id', $userOutletId);
                //         $outletData = $userOutlet->getData();
                //         $assignmentCriteria = "";

                //         if(!empty($outletData)) {
                //             $assignmentCriteria = $outletData[0]['product_assignment_basis'];
                //         }

                //         if($assignmentCriteria == 'category'){
                //             $categoryData = $connection->select();
                //             $categoryData->from(
                //                 ['scto' => $this->resource->getTableName('ah_supermax_pos_category_to_outlet')],
                //                 ['category_id']
                //             )->joinLeft(
                //                 ['ccp' => $this->resource->getTableName('catalog_category_product')],
                //                 "scto.category_id = ccp.category_id",
                //                 ['product_id']
                //             )->where("scto.parent_outlet_id = $userOutletId");
                //             $categoryProductCollection = $connection->query($categoryData)->fetchAll();

                //             foreach($categoryProductCollection as $categoryProduct){
                //                 $outletProductData[] =  (int)$categoryProduct['product_id'];
                //             }

                //         } elseif($assignmentCriteria == 'product') {
                //             $outletProductTableName = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
                //             $outletProductDatas = $connection->query("SELECT product_id FROM $outletProductTableName WHERE parent_outlet_id = $userOutletId ")->fetchAll();

                //             if(!empty($outletProductDatas)){
                //                 foreach($outletProductDatas as $product){
                //                     $outletProductData[] =  (int)$product['product_id'];
                //                 }
                //             }

                //         } 

                //         if(empty($page) || $page == 1 || $page == 0) {
                //             $page = 1;
                //         }

                //         $products = array();
                //         $totalProductCollection = $this->productCollectionFactory->create()
                //             ->addAttributeToSelect('*')
                //             ->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                //             ->addAttributeToFilter('type_id', 'simple')
                //             ->setPageSize(6000) 
                //             ->setStoreId($storeViewId);
                
                //         $collection = $this->productCollectionFactory->create()
                //             ->addAttributeToSelect('*')
                //             ->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                //             ->addAttributeToFilter('type_id', 'simple')
                //             ->setStoreId($storeViewId)
                //             ->setPageSize(2000) 
                //             ->setCurPage($page);

                //         // $outletProductData = [1,2,3];
                //         if(!empty($productIds)){
                //             $totalProductCollection = $totalProductCollection->addFieldToFilter('entity_id', array('in' => $productIds));
                //             $collection = $collection->addFieldToFilter('entity_id', array('in' => $productIds));
                //         } 
                        
                //         if($assignmentCriteria == 'category' || $assignmentCriteria == 'product'){
                //             $totalProductCollection = $totalProductCollection->addFieldToFilter('entity_id', array('in' => $outletProductData));
                //             $collection = $collection->addFieldToFilter('entity_id', array('in' => $outletProductData));
                //         }
                        
                //         $totalProductCollection = $totalProductCollection->load();
                //         $totalProducts = count($totalProductCollection->getData());
                //         $products = $collection->load()->getItems();
                //         $allProductData = array();

                //         if(!empty($products)) {
                //             foreach ($products as $product) {
                //                 $categoryIds = ""; 
                //                 $levelOne = $product->getDepartment();
                //                 $levelTwo = $product->getClass();
                //                 $levelThree = $product->getSubclass();

                //                 $rootCategoryName = "Merchandising Category"; 
                //                 $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree; 
                //                 $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
                //                 $id = $cateogyrProcessor;
                //                 if($id) { 
                //                     $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                //                     $categoryIds = $category->getCategoryId();
                //                 }
                //                 $allProductData[] = array(
                //                     'product_id' => (int)$product->getId(), 
                //                     'name' => html_entity_decode($product->getName()), 
                //                     'sku' => html_entity_decode($product->getSku()),
                //                     'type' => html_entity_decode($product->getTypeId()),
                //                     'childProducts' => array(),
                //                     // $this->getChildrenProducts($product),
                //                     'barcode' => html_entity_decode($product->getBarcode()),
                //                     'image' => "",
                //                     // html_entity_decode($this->productImageResize($product->getImage())),
                //                     'is_qty_decimal'=> false,
                //                     // (bool)$this->getStockItem((int)$product->getId()),
                //                     'baseCost' => (float)$this->helper->convert((float)$product->getPrice(), $storeBaseCurrencyCode, $storeCurrencyCode),
                //                     'cost' => (float)$this->helper->convert((float)$product->getPrice(), $storeBaseCurrencyCode, $storeCurrencyCode),
                //                     'dynamic_price' => '',
                //                     // $this->getBundlePriceType($product),
                //                     'special' => $this->getProductSpecialPrice($product, $storeViewId, $storeCurrencyCode, $storeBaseCurrencyCode),
                //                     'discounts' => $this->getTierPriceTable($product->getId()),
                //                     'quantity' => 100,
                //                     // (int)$this->getSourceQuantity($userOutletId, $product->getSku(), $product->getId()),
                //                     'tax_class_id' => (int)$product->getTaxClassId(),
                //                     'attributes' => array(),
                //                     // $this->getProductAttributes($product, $storeViewId),
                //                     'options' => $this->getProductOptions($product, $storeBaseCurrencyCode, $storeCurrencyCode),
                //                     'variations' => array(),
                //                     // $this->getProductVariations($product),
                //                     'bundle_options' => array(),
                //                     // $this->getBundleProductOptions($product, $storeViewId, $storeCurrencyCode, $storeBaseCurrencyCode),
                //                     'catalog_prices' => array(),
                //                     // $this->getCatalogPrice($product->getId(), $websiteId, $storeCurrencyCode, $storeBaseCurrencyCode),
                //                     'category_id' => $categoryIds,
                //                     'is_bom' => ($product->getIsBom()) ? true : false,
                //                     'brand_id' => !empty($product->getAttributeText('brand_Id')) ? $product->getAttributeText('brand_Id') : '',
                //                     'brand_name' => !empty($product->getAttributeText('brand_Id')) ? $this->productFieldProcessor->getBrandNameById(strtolower($product->getAttributeText('brand_Id'))) : '',
                //                     'seller_id' =>!empty($product->getSellerId()) ?  $product->getSellerId() : '',
                //                     'is_lot_controlled' => ($product->getIsLotControlled()) ? true : false,
                //                     'quantity_uom' => $product->getAttributeText('sale_uom')
                //                 );
                //             }
                            
                //         }

                //     }
                // }

                // $result = array(
                //     'products' => $allProductData, 
                //     'total' => $totalProducts
                // );
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array( 'error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }

    //To get tier price  and customer group price of the product
    public function getTierPriceTable($product_id) {
        $connection= $this->resource->getConnection();
        $productTierPriceTable = $this->resource->getTableName('catalog_product_entity_tier_price');
        $tierDatas = $connection->query("SELECT * FROM $productTierPriceTable WHERE entity_id = $product_id");

        if(!empty($tierDatas)) {
            $tierPrice = array();

            foreach($tierDatas as $tierData) {
                $tierPrice [] = array(
                    'all_groups' => (int)$tierData['all_groups'],
                    'customer_group_id' => (int)$tierData['customer_group_id'],
                    'qty' => (float)$tierData['qty'],
                    'value' => (float)$tierData['value'],
                    'percentage_value' => (float)$tierData['percentage_value'],
                    'customer_zone' => $tierData['customer_zone']
                );
            }

        } else {
            $tierPrice = [];
        }
        
        return $tierPrice;
    }

    // To get product special price, from date and to date.
    public function getProductSpecialPrice($product, $storeViewId, $storeCurrencyCode, $storeBaseCurrencyCode) {
        $specialPrice = array();

        if(!empty($product->getSpecialPrice())) {
            $specialPrice = array(
                'special_price' => (float)$this->helper->convert((float)$product->getSpecialPrice(), $storeBaseCurrencyCode, $storeCurrencyCode),
                'special_from_date' => $product->getSpecialFromDate(),
                'special_to_date' => $product->getSpecialToDate()
            );
        }

        return $specialPrice;
    }

    // To get child products Id of grouped configurable and bundle products.
    public function getChildrenProducts($product) {
        $childProductsId = array();
        $childProductsIds = array();
        $childrens = array();

        if($product->getTypeId() == 'grouped') {
            $childProductsIds = $this->groupedChildProducts->getChildrenIds($product->getId());
        } elseif($product->getTypeId() == 'configurable') {
            $childProductsIds = $this->configurableChildProducts->getChildrenIds($product->getId());
        } elseif($product->getTypeId() == 'bundle') {
            $childProductsIds = $this->bundleChildProducts->getChildrenIds($product->getId());
        }

        if(!empty($childProductsIds)) {
            foreach($childProductsIds as $childProductsId) {
                foreach($childProductsId as $key=>$value) {
                    $childrens[] = (int)$value;
                }
            }
        }

        return $childrens;
    }

    // To get product quantity.
    public function getSourceQuantity($userOutletId, $productSku, $productId) {
        $installer = $this->setup;
        $productQty = null;
        // IF MSI Enabled
        if($installer->tableExists('inventory_source')){
            $userOutlet = $this->supermaxOutlet->addFieldToFilter('pos_outlet_id', $userOutletId);
            $outletData = $userOutlet->getData();
            $outletSourceCode = "";

            if(!empty($outletData)) {
                $outletSourceCode = $outletData[0]['source_code'];
            }
            // if MSI enabled and source is also created.
            $connection = $this->resource->getConnection();
            $sourceTableName = $this->resource->getTableName("inventory_source");
            $sourceData = $connection->query("SELECT * FROM $sourceTableName")->fetchAll();
            if(!empty($sourceData)){
                $sourceDataBySku = $this->objectManager->get("Magento\InventoryCatalogAdminUi\Model\GetSourceItemsDataBySku");
                $productAllQtys = $sourceDataBySku->execute($productSku);
                
                if(!empty($productAllQtys)) {
                    foreach($productAllQtys as $productAllQty) {
                        if($productAllQty['source_code'] == $outletSourceCode) {
                            $productQty = $productAllQty['quantity'];
                        }
                    }
                }
            } else { // if MSI enabled but source is not created.
                $catalogInventoryData = $this->stockItemRepository->getStockItem($productId);
                if(!empty($catalogInventoryData)){
                    $productQty = $catalogInventoryData['qty'];
                }
            }
        } else { // If MSI is disabled
            $catalogInventoryData = $this->stockItemRepository->getStockItem($productId);
            if(!empty($catalogInventoryData)){
                $productQty = $catalogInventoryData['qty'];
            }
        }

        return $productQty;
    }  

    // To get configurable product's attributes and options data.
    public function getProductAttributes($product, $storeViewId){
        $connection = $this->resource->getConnection();
        $attributeResult = array();

        if($product->getTypeId() == 'configurable') {
            $attributeId = '';
            $attributeCode = '';
            $frontendLabel = '';
            $isRequired = '';
            $defaultValue = '';
            $type = '';
            $optionType = '';
            $opCode = '';
            $options = array();
            
            $customOptions = $this->productOptions->getConfigurableAttributesAsArray($product);

            foreach($customOptions as $customOption){
                $options = array();
                $attributeId = $customOption['attribute_id'];
                $attributeCode = $customOption['attribute_code'];
                $frontendLabel = $customOption['frontend_label'];

                $attributes = $this->configOption($attributeId);
                
                if(!empty($attributes)){
                    foreach($attributes as $attribute){
                        $isRequired = $attribute['is_required'];
                        $defaultValue = $attribute['default_value'];
                    }
                }
                
                foreach($customOption['values'] as $op) {
                    $optioncodes = $this->configOptionCode($op['value_index']);

                    if(!empty($optioncodes)) {
                        foreach($optioncodes as $optioncode){
                            $opCode = $optioncode['value'];
                            $type = $optioncode['type'];
                        }
                    }

                    $options[] = array(
                        'value_index' => (int)$op['value_index'],
                        'label' => html_entity_decode($op['label']),
                        'option_code' => html_entity_decode($opCode)
                    );
                }
                
                if($type == '') {
                    $optionType = 'dropdown';
                } elseif($type == 0) {
                    $optionType = 'text';
                } elseif($type == 1) {
                    $optionType = 'visual_swatch';
                } elseif($type == 2) {
                    $optionType = 'visual_image';
                }
    
                $attributeResult[] = array(
                    'attribute_id' => (int)$attributeId,
                    'attribute_code' => html_entity_decode($attributeCode),
                    'frontend_label' => html_entity_decode($frontendLabel),
                    'is_required' => (bool)$isRequired,
                    'default_value' => (int)$defaultValue,
                    'frontend_type' => html_entity_decode($optionType),
                    'options' => $options 
                );
            }
        }
        return $attributeResult;
    }

    public function configOption($attributeId) {
        $connection = $this->resource->getConnection();
        $attributeTable = $this->resource->getTableName('eav_attribute');
        $attributes = $connection->query("SELECT * FROM $attributeTable WHERE attribute_id = $attributeId");
        return $attributes;
    }

    public function configOptionCode($optionId) {
        $attributeOptionData = array();
        if($optionId){
            $connection = $this->resource->getConnection();
            $attributeSwatchTable = $this->resource->getTableName('eav_attribute_option_swatch');
            $attributeOptionData = $connection->query("SELECT * FROM $attributeSwatchTable WHERE option_id = $optionId AND store_id = 0");
        }
        return $attributeOptionData;
    }

    // To get variation(combination) products
    public function getProductVariations($product){
        $variations = array();

        if($product->getTypeId() == 'configurable') {
            $data = $product->getTypeInstance()->getConfigurableOptions($product);

            foreach($data as $attributes) {
                foreach($attributes as $attribute) {
                    $variations[(int)$this->productData->getIdBySku($attribute['sku'])][] = (int)$attribute['value_index'];
                }
            }
        }
       
        return $variations;
    }

    // To get bundle product's options
    public function getBundleProductOptions($product, $storeViewId, $storeCurrencyCode, $storeBaseCurrencyCode) {
        $connection = $this->resource->getConnection();
        $bundleOptionsResult = array();
        
        if($product->getTypeId() == 'bundle') {
            $bundleOptions = array();
            $productBundleSelectionTable = $this->resource->getTableName('catalog_product_bundle_selection');
            $options = $product->getTypeInstance()->getOptions($product);
            
            if(!empty($options)) {

                foreach($options as $option) {
                    $bundleOptions = array();
                    $optionId = $option['option_id'];
                    $isRequired = $option['required'];
                    $type = $option['type'];
                    $defaultTitle = $option['default_title'];
                    $title = $option['title'];
                    $parentId = $option['parent_id'];
                    $BundleOptionDatas = $connection->query("SELECT * FROM $productBundleSelectionTable WHERE option_id = $optionId AND parent_product_id = $parentId");
                    
                    if(!empty($BundleOptionDatas)) {

                        foreach($BundleOptionDatas as $bundleProduct) {
                            $priceType = $bundleProduct['selection_price_type'];

                            if($priceType == 0) {
                                $bundleOptionPriceType = 'fixed';
                            } elseif($priceType == 1) {
                                $bundleOptionPriceType = 'percent';
                            }

                            if($bundleOptionPriceType == 'fixed'){
                                $selectionPriceValue = (float)$this->helper->convert((float)$bundleProduct['selection_price_value'], $storeBaseCurrencyCode, $storeCurrencyCode);
                            } else {
                                $selectionPriceValue = $bundleProduct['selection_price_value'];
                            }
                            
                            $bundleOptions[] = array(
                                'selection_id' => (int)$bundleProduct['selection_id'],
                                'product_id' => (int)$bundleProduct['product_id'],
                                'is_default' => (bool)$bundleProduct['is_default'],
                                'selection_price_type' => $bundleOptionPriceType,
                                'selection_price_value' => (float)$selectionPriceValue,
                                'selection_qty' => (float)$bundleProduct['selection_qty'],
                                'selection_can_change_qty' => (bool)$bundleProduct['selection_can_change_qty']
                            );
                        }
                    }

                    $bundleOptionsResult[] = array(
                        'option_id' => (int)$optionId,
                        'is_required' => (bool)$isRequired,
                        'type' => $type,
                        'default_title' => $defaultTitle,
                        'title' => $title,
                        'product_values' => $bundleOptions
                    );
                }
            }
        }

        return $bundleOptionsResult;
    }

    // To get bundle product's price type
    public function getBundlePriceType($product) {
        $productPriceType = '';
        if($product->getTypeId() == 'bundle') {
            $productPriceType = (bool)(!$product->getPriceType());
        }
        return $productPriceType;
    }

    // Product image resize
    public function productImageResize($src, $imgWidth = 80, $imgHeight = 80){
        $resizedImageUrl = '';
        if(empty($src)) {
            return $resizedImageUrl;
        }
         
        // Get Original Product-Image Path 
        $absPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('catalog/product'.$src);
        if(file_exists($absPath)){
            // Get Resized Product-Image Path 
            $productImageResized = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath().$this->NewProductImage($src);
            
            // if resized image path exist return exist resized image url.
            if(file_exists($productImageResized)) {
                $resizedImageUrl =$this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$this->NewProductImage($src);
            } else {
                $productImageResize = $this->_imageFactory->create();
                $productImageResize->open($absPath);
                $productImageResize->constrainOnly(false);
                $productImageResize->keepTransparency(false);
                $productImageResize->keepFrame(false);
                $productImageResize->keepAspectRatio(false);
                $productImageResize->resize($imgWidth,$imgHeight);
                $productImageResize->save($productImageResized);
                $resizedImageUrl= $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$this->NewProductImage($src);
            }
        }
        
        return $resizedImageUrl;
    }

    public function NewProductImage($src) {
        $srcSegments = array_reverse(explode('/',$src));
        $dir1 = substr($srcSegments[0],0,1);
        $dir2 = substr($srcSegments[0],1,1);
        return 'catalog/product/supermax_resized/'.$dir1.'/'.$dir2.'/'.$srcSegments[0];
    }


    public function getStockItem($productId) {
        // cataloginventory_stock_item table data. also will be used for minqty and maxqty allowed in shopping cart.
        $isQtyDecimal = null;
        $catalogInventoryData = $this->stockItemRepository->getStockItem($productId);
        if(!empty($catalogInventoryData)){
            $isQtyDecimal = $catalogInventoryData['is_qty_decimal'];
        }
        return $isQtyDecimal;
    }

    public function getProductOptions($product, $storeBaseCurrencyCode, $storeCurrencyCode) {
        $productOptionsResult = array();
        if($product->getTypeId() == 'simple' || $product->getTypeId() == 'virtual' || $product->getTypeId() == 'downloadable') {
            $productId = $product->getId();
            $connection = $this->resource->getConnection();
            $optionQuery = $connection->select()->from(
                ['cpo' => $this->resource->getTableName('catalog_product_option')],
                ['option_id', 'product_id', 'type', 'is_require', 'option_sku'=>'sku', 'max_characters', 'file_extension', 'image_size_x', 'image_size_y', 'option_sort_order'=>'sort_order']
            )->joinLeft(
                ['cpop' => $this->resource->getTableName('catalog_product_option_price')],
                "cpop.option_id = cpo.option_id",
                ['option_price'=>'price', 'option_price_type'=>'price_type']
            )->joinLeft(
                ['cpot' => $this->resource->getTableName('catalog_product_option_title')],
                "cpot.option_id = cpo.option_id",
                ['option_title'=>'title']
            )->where("cpo.product_id = $productId")->group("cpo.option_id");

            $productOptionsCollection = $connection->query($optionQuery)->fetchAll();

            if(!empty($productOptionsCollection)) {
                foreach($productOptionsCollection as $optionData) {
                    $optionId = $optionData['option_id'];
                    $productOptionsResult[] = array(
                        "option_id" => (int)$optionId,
                        'option_type' => html_entity_decode($optionData['type']),
                        'is_option_require' => (bool)$optionData['is_require'],
                        'option_sku' => html_entity_decode($optionData['option_sku']),
                        'max_characters' => (int)$optionData['max_characters'],
                        'file_extension' => html_entity_decode($optionData['file_extension']),
                        'image_size_x' => (int)$optionData['image_size_x'],
                        'image_size_y' => (int)$optionData['image_size_x'],
                        'option_sort_order' => (int)$optionData['option_sort_order'],
                        'option_price' => $optionData['option_price_type'] == 'percent' ? (float)$optionData['option_price'] : (float)$this->helper->convert($optionData['option_price'], $storeBaseCurrencyCode, $storeCurrencyCode),
                        'option_price_type' => html_entity_decode($optionData['option_price_type']),
                        'option_title' => html_entity_decode($optionData['option_title']),
                        'option_type_data' => $this->getCustomOptionTypes($optionId, $productId, $storeBaseCurrencyCode, $storeCurrencyCode)
                    );

                }
            }

        }
        return $productOptionsResult;
    }

    public function getCustomOptionTypes($optionId, $productId, $storeBaseCurrencyCode, $storeCurrencyCode){
        $optionTypeResult = array();
        $connection = $this->resource->getConnection();
        $optionTypeQuery = $connection->select()->from(
            ['cpotv' => $this->resource->getTableName('catalog_product_option_type_value')],
            ['option_type_id','option_type_sku'=>'sku', 'option_type_sort_order'=>'sort_order']
        )->joinLeft(
            ['cpott' => $this->resource->getTableName('catalog_product_option_type_title')],
            "cpott.option_type_id = cpotv.option_type_id",
            ['option_type_title' => 'title']
        )->joinLeft(
            ['cpotp' => $this->resource->getTableName('catalog_product_option_type_price')],
            "cpotp.option_type_id = cpotv.option_type_id",
            ['option_type_price' => 'price', 'option_type_price_type'=> 'price_type']
        )->where("cpotv.option_id = $optionId");

        $optionTypeCollection = $connection->query($optionTypeQuery)->fetchAll();

        if(!empty($optionTypeCollection)){
            foreach($optionTypeCollection as $optionData){
                $optionTypeResult[] = array(
                    'option_type_id' => (int)$optionData['option_type_id'],
                    'option_type_sku' => html_entity_decode($optionData['option_type_sku']),
                    'option_type_sort_order' => (int)$optionData['option_type_sort_order'],
                    'option_type_title' => html_entity_decode($optionData['option_type_title']),
                    'option_type_price' => $optionData['option_type_price_type'] == 'percent' ? (float)$optionData['option_type_price'] : (float)$this->helper->convert($optionData['option_type_price'], $storeBaseCurrencyCode, $storeCurrencyCode),
                    'option_type_price_type' => html_entity_decode($optionData['option_type_price_type'])
                );
            }
        }

        return $optionTypeResult;
    }

    public function getCatalogPrice($product_id, $websiteId, $storeCurrencyCode, $storeBaseCurrencyCode){
        $catalogPrice = array();
        $connection = $this->resource->getConnection();
        $query = $connection->select()->from(
            ['cpp' => $this->resource->getTableName('catalogrule_product_price')],
            ['customer_group_id', 'rule_price', 'latest_start_date', 'earliest_end_date']
        )->where("cpp.product_id = $product_id AND cpp.website_id= $websiteId")->group("cpp.customer_group_id");

        $catalogPriceCollection = $connection->query($query)->fetchAll();
        if(!empty($catalogPriceCollection)){
            foreach($catalogPriceCollection as $data){
                $catalogPrice[] = array(
                    'customer_group_id' => (int)$data['customer_group_id'],
                    'catalog_price' => (float)$this->helper->convert((float)$data['rule_price'], $storeBaseCurrencyCode, $storeCurrencyCode),
                    'catalog_price_from_date' => $data['latest_start_date'],
                    'catalog_price_to_date' => $data['earliest_end_date']
                );
            }
        }
        return $catalogPrice;
    }

    public function getFulfilmentOption($product) {
        $fulfillmentType = "CNC";
        $fulfillmentOptionId = $product->getStoreFulfilmentMode();
        if(!empty($fulfillmentOptionId)) {
            $connection = $this->resource->getConnection();
            $optionValueTable = $this->resource->getTableName('eav_attribute_option_value');
            $data = $connection->query("SELECT * FROM $optionValueTable WHERE option_id = $fulfillmentOptionId AND store_id = 0")->fetch();
            if(!empty($data)) {
                $fulfillmentType = $data['value'];
            }
        }

        return $fulfillmentType;
    }

    private function getProductPrices($product, $customerGroup, $priceZone, $quantity) {
        $product_id = $product->getEntityId();
        $basePrice = (float)$product->getPrice();
        $tierPrice = array();
        $connection= $this->resource->getConnection();
        $productTierPriceTable = $this->resource->getTableName('catalog_product_entity_tier_price');
        $tierDatas = $connection->query("SELECT * FROM $productTierPriceTable WHERE entity_id = $product_id")->fetchALl();

        if(!empty($tierDatas)) {
            foreach($tierDatas as $tierData) {
                $tierPrice [] = array(
                    'all_groups' => (int)$tierData['all_groups'],
                    'customer_group_id' => (int)$tierData['customer_group_id'],
                    'qty' => (float)$tierData['qty'],
                    'value' => (float)$tierData['value'],
                    'percentage_value' => (float)$tierData['percentage_value'],
                    'customer_zone' => $tierData['customer_zone']
                );
            }
        }
        
        if(!empty($tierPrice)) {
            $defaultPriceZone = $this->helper->getConfig('regional_pricing/setting/default_zone', null);	
            $regional_pricing_status = (bool)$this->helper->getConfig('regional_pricing/setting/active', null);
            $applicablePrice = array();
            $allData = array();
            if($regional_pricing_status) {
                $allData = $this->filterTierData($tierPrice, $customerGroup, $priceZone, $quantity);
            }

            if(empty($allData)) {
                $allData = $this->filterTierData($tierPrice, $customerGroup, $defaultPriceZone, $quantity);
            }

            if(!empty($allData)) {
                foreach ($allData as $value) {
                    if($value['qty'] == $quantity) {
                        $applicablePrice = $value;
                    }
                }
                if(empty($applicablePrice)) {
                    usort($allData, function ($item1, $item2) {
                        if ($item1['qty'] == $item2['qty']) return 0;
                        return $item1['qty'] > $item2['qty'] ? -1 : 1;
                    });
                    $applicablePrice = $allData[0];
                }
            }

            if(!empty($applicablePrice)) {
                $price = ($applicablePrice['percentage_value']) ? (float)($basePrice - (float)($applicablePrice['percentage_value'] / 100) * $basePrice ) : $applicablePrice['value'];	
            } else {
                $price = $basePrice;
            }
        } else {
            $price = $basePrice;
        }

        $data = array(
            0 => array(
                "type" => "PRICE_INCL_TAX",
                "price" => array(
                    "cent_amount" => round($price * 100),
                    "currency" => "INR",
                    "fraction" => 100
                ),
            ),
            1 => array(
                "type" => "MRP",
                "price" => array(
                    "cent_amount" => round($product->getMrp() * 100),
                    "currency" => "INR",
                    "fraction" => 100
                ),
            )
        );
        return $data;
    }

    private function filterTierData($tierPrices, $customerGroupId, $priceZone, $quantity) {
        $allTierPrice = array();
        foreach($tierPrices as $tierPrice) {
            if($tierPrice['customer_group_id'] == $customerGroupId && $tierPrice['customer_zone'] == strtolower($priceZone) && $tierPrice['qty'] <= $quantity) {
                $allTierPrice[] = $tierPrice;
            }
        }
        return $allTierPrice;
    }

    public function getCategoryIds($product) {
        $categoryIds = ""; 
        if(!empty($product->getIboCategoryId())) {
            $categoryIds = $product->getIboCategoryId();
        } else {
            $levelOne = $product->getDepartment();
            $levelTwo = $product->getClass();
            $levelThree = $product->getSubclass();

            $rootCategoryName = "Merchandising Category"; 
            $finalCategoryName = $rootCategoryName . "||" . $levelOne . "||" . $levelTwo . "||" . $levelThree; 
            $cateogyrProcessor = $this->categoryProcessor->getCategoryIdByPath(trim($finalCategoryName));
            $id = $cateogyrProcessor;
            if($id) {
                $category = $this->categoryRepository->get($id, $this->storeManager->getStore()->getId());
                $categoryIds = $category->getCategoryId();
            }
        }

        return $categoryIds;
    }

}