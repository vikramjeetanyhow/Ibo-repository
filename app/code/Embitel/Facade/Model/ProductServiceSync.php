<?php

namespace Embitel\Facade\Model;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Exception\NoSuchActionException;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;
use Embitel\Facade\Helper\Data as FacadeHelper;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as ProductAttributeCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as AttributeGroupCollection;
use Magento\Framework\Stdlib\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Embitel\Facade\Api\MerchandiseInterface;

class ProductServiceSync extends AbstractModel implements MerchandiseInterface
{
    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var Api
     */
    protected $facadeApi;
    private LoggerInterface $logger;
    private OodoApiHelper $oodoApiHelper;
    private \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement;
    private Configurable $productTypeInstance;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $productCollection
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Image $imageHelper
     * @param LoggerInterface $logger
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement
     * @param Configurable $productTypeInstance
     */
    public function __construct(
        ScopeConfigInterface                                                       $scopeConfig,
        CollectionFactory                                                          $productCollection,
        ProductFactory                                                             $productFactory,
        ProductRepositoryInterface                                                 $productRepository,
        CategoryRepositoryInterface                                                $categoryRepository,
        Image                                                                      $imageHelper,
        LoggerInterface                                                            $logger,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement,
        Configurable                                                               $productTypeInstance,
        FacadeHelper                                                                $facadeHelper,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoriesFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        AttributeGroupCollection $attributeGroupCollection,
        ProductAttributeCollection $productAttributeCollection,
        DateTime $dateTime,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->productCollection = $productCollection;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->imageHelper = $imageHelper;
        $this->logger = $logger;
        $this->productLinkManagement = $productLinkManagement;
        $this->productTypeInstance = $productTypeInstance;
        $this->facadeHelper = $facadeHelper;
        $this->categoriesFactory = $categoriesFactory;
        $this->categoryFactory = $categoryFactory;
        $this->attributeGroupCollection = $attributeGroupCollection;
        $this->productAttributeCollection = $productAttributeCollection;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
        $this->resource = $resource;
    }

    /**
     * Sync product to facade
     *
     */
    public function syncProducts()
    {
        try {
            $this->syncProductsToCatalogServiceAllProducts();
        } catch (Exception $exception) {
            $this->logger->info($exception->getMessage());
        }
    }

    /**
     * @return void
     */
    protected function syncProductsToCatalogServiceAllProducts(): void
    {
        if (!$this->facadeHelper->isCronEnabled()) {
            return;
        }

        //$config = $this->facadeApi->getConfig();
        $productSyncApi = $this->facadeHelper->getProductSyncApi();
        if (!$productSyncApi) {
            $this->facadeHelper->addLog("Product sync API endpoint is not added in the configuration.");
            return;
        }

        $limit = (int)$this->facadeHelper->getFailureHitLimit();

        $connection = $this->resource->getConnection();
        $catalofServiceTable = $this->resource->getTableName('catalog_service_product_push');
        $sql = "SELECT * FROM $catalofServiceTable Where status_flag < $limit";

        $catalogData = $connection->query($sql)->fetchAll();

        foreach ($catalogData as $catData) {
            $this->facadeHelper->addLog('Product ID :'.$catData['product_id']);
            $this->prepareAndSendData($catData['product_id']);
        }


        // $productCollection = $this->productCollection->create()
        //     ->addAttributeToFilter('type_id', 'simple')
        //     ->addAttributeToFilter('status', Status::STATUS_ENABLED)
        //     ->addAttributeToFilter([
        //         ['attribute' => 'catalog_service_sync_count', 'null' => true],
        //         ['attribute' => 'catalog_service_sync_count', 'lt' => $limit]
        //     ]);
        // //$productCollection->getSelect()->limit(2);

        // //echo $productCollection->getSelect()->__toString();exit;

        // foreach ($productCollection as $product) {
        //     $this->facadeHelper->addLog('Sku :'.$product->getSku());
        //     //$this->prepareAndSendData(1000000012); exit;
        //     $this->prepareAndSendData($product->getSku());
        // }

    }

    /**
     * @return void
     */
    public function syncProductsToCatalogService(): void
    {
        if (!$this->facadeHelper->isCronEnabled()) {
            return;
        }

        //$config = $this->facadeApi->getConfig();
        $productSyncApi = $this->facadeHelper->getProductSyncApi();
        if (!$productSyncApi) {
            $this->facadeHelper->addLog("Product sync API endpoint is not added in the configuration.");
            return;
        }

        $limit = (int)$this->facadeHelper->getFailureHitLimit();
        $productCollection = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            //->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('allowed_channels', ['in' => ['ONLINE','OMNI']]);
        //$productCollection->getSelect()->limit(2);

        foreach ($productCollection as $product) {
            $this->facadeHelper->addLog('Sku :'.$product->getSku());
            //$this->prepareAndSendData(1000000012); exit;
            $this->prepareAndSendData($product->getId());
        }
    }

    /**
     * @return void
     */
    public function syncProductsToCatalogServiceStoreProducts($start,$end,$prodStatus): void
    {
        if (!$this->facadeHelper->isCronEnabled()) {
            return;
        }

        //$config = $this->facadeApi->getConfig();
        $productSyncApi = $this->facadeHelper->getProductSyncApi();
        if (!$productSyncApi) {
            $this->facadeHelper->addLog("Product sync API endpoint is not added in the configuration.");
            return;
        }

        $limit = (int)$this->facadeHelper->getFailureHitLimit();

        if($prodStatus == 'enabled') {
            $status = Status::STATUS_ENABLED;
        } else if ($prodStatus == 'disabled') {
            $status = Status::STATUS_DISABLED;
        }

        if($prodStatus == 'all') {
            $productCollection = $this->productCollection->create()
                ->addAttributeToFilter('type_id', 'simple')
                //->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('allowed_channels', ['in' => ['STORE']])
                ->addAttributeToFilter('entity_id', ['gt' => $start])
                ->addAttributeToFilter('entity_id', ['lt' => $end]);
        } else {
            $productCollection = $this->productCollection->create()
                ->addAttributeToFilter('type_id', 'simple')
                ->addAttributeToFilter('status', $status)
                ->addAttributeToFilter('allowed_channels', ['in' => ['STORE']])
                ->addAttributeToFilter('entity_id', ['gt' => $start])
                ->addAttributeToFilter('entity_id', ['lt' => $end]);
        }
        //$productCollection->getSelect()->limit(50);

        //echo $productCollection->getSelect()->__toString();exit;

        foreach ($productCollection as $product) {
            $this->facadeHelper->addLog('Sku :'.$product->getSku());
            //$this->facadeHelper->addLog('Allowed Channel :'.$product->getAllowedChannels());
            //$this->prepareAndSendData(1000000012); exit;
            $this->prepareAndSendData($product->getId());
        }
    }

    /**
     * Prepare product data to send to facade
     *
     * @param type $sku
     */
    public function prepareAndSendData($productId)
    {
        try {
 
            $product = $this->productRepository->getById($productId);
            $this->facadeHelper->addLog('Product Data Entered :');

            //Code to get the brand id
            // $brandId = '';
            // $brandName = trim($product->getResource()->getAttribute('brand_Id')->setStoreId(0)->getFrontend()->getValue($product));
            // $brandRootCategoryId = trim($this->scopeConfig->getValue("catalog_service/general/brand_category_id"));
            // $this->facadeHelper->addLog('Brand Name :'.$brandName);
            // if($brandName !='') {
            //    $catoryData = $this->categoryFactory->create()->getCollection()
            //    ->addAttributeToSelect('*')
            //    ->addAttributeToFilter('category_id',$brandName)
            //    ->addAttributeToFilter('parent_id', $brandRootCategoryId);
            //    if($catoryData->getSize()) {
            //         $brandCatId = $catoryData->getFirstItem()->getData();
            //         $brandId = isset($brandCatId['ibo_brand_id']) ? $brandCatId['ibo_brand_id'] :'';
            //     }
            // }

            $brandId = '';
            $attr = $product->getResource()->getAttribute('brand_Id');
            if ($attr->usesSource()) {
                $brandId = $product->getData('brand_Id');
            }

            $prodStatus = $product->getStatus();

            if($prodStatus == 1) {
                $status = true;
            } else {
                $status = false;
            }

            $replensibilityActionValue = '';
            if($product->getAttributeText('replenishability_action') != '') {
                $replensibilityActionValue = $product->getAttributeText('replenishability_action');
                if($replensibilityActionValue == 'R') {
                    $replensibilityActionValue = 'RP';
                }
            }

            $data = [
                "esin" => ($product->getEsin() != '')?$product->getEsin():'' ,
                "unique_group_id" => ($product->getUniqueGroupId() != '')?$product->getUniqueGroupId():'',
                "ebo_title" => ($product->getName())?$product->getName():'',
                "ebo_color" => ($product->getAttributeText('ebo_color') != '')?$product->getAttributeText('ebo_color'):'',
                "short_name" => ($product->getName())?$product->getName():'',
                "description" => ($product->getDescription() !='')?$product->getDescription():'',
                "department" => ($product->getDepartment() != '')?$product->getDepartment():'',
                "class" => ($product->getClass()!='')?$product->getClass():'',
                "sub_class" => ($product->getSubclass()!='')?$product->getSubclass():'',
                "pack_of" => ($product->getAttributeText('pack_of') != '')?$product->getAttributeText('pack_of'):'',
                "navigation_category_ids" => $this->getNavigationCatIds($product),//$product->getCategoryIds(),
                 "country_of_origin" => ($product->getAttributeText('country_of_origin')!='')?$product->getAttributeText('country_of_origin'):'',
                 "start_datetime" => ($product->getStartDate() !='')?$this->dateTime->formatDate($product->getStartDate()):'',
                 "product_type" => "REGULAR",
                 "category_id" => ($product->getIboCategoryId())?$product->getIboCategoryId():'',
                 "product_origin" => "",
                 "is_returnable" => ($product->getIsReturnable())?true:false,
                 "return_window_in_days" => ($product->getReturnWindowInDays() !='')?(int)$product->getReturnWindowInDays():'',
                 //"warranty_in_months" => ($product->getWarrantyInMonths() !='')?(int)$product->getWarrantyInMonths():'',
                 "warranty_description" => (trim($product->getWarrantyInMonths()) !='')?trim($product->getWarrantyInMonths()):'',
                 "requires_shipping" => true,
                 //"is_bom" => ($product->getIsBom())?true:false,
                 "allowed_channel" => ($product->getAllowedChannels() !='')?$product->getAllowedChannels():'',
                 "mrp" => ($product->getMrp() !='')?$product->getMrp():'',
                 "service_category" => ($product->getServiceCategory()!='')?$product->getServiceCategory():'',
                 "store_fulfilment_mode" => ($product->getAttributeText('store_fulfilment_mode') !='')?$product->getAttributeText('store_fulfilment_mode'):'',
                 "express_enabled" => false,
                 "per_unit_price_applicable" => (trim($product->getPerUnitPriceApplicable()) == 'Yes')?true:false,
                 "per_unit_price_divisor" => $this->getPerUnitPriceDivisor($product),
                 "per_unit_price_unit" => ($product->getPerUnitPriceUnit() != '')? $product->getPerUnitPriceUnit():'',
                 "sort_order" => ($product->getSortOrder() !='')?(int)$product->getSortOrder():'',
                // "related_products" => [
                //     ""
                // ],
                "key_features" => [
                  ($product->getKeyFeatures() != '')?$product->getKeyFeatures():''
                ],
                "barcodes" => $this->getBarcodes($product),
                "brand_info" => [
                    "brand_collection" => ($product->getBrandCollection() !='')?$product->getBrandCollection() :'',
                    "brand_color" => ($product->getAttributeText('brand_color') !='')?trim($product->getAttributeText('brand_color')):"",
                    "brand_grading" => ($product->getAttributeText('brand_grading') != '')?$product->getAttributeText('brand_grading'):'',
                    "brand_id" => $brandId,
                    "brand_model" => ($product->getBrandModelNumber() !='')?$product->getBrandModelNumber():'',
                    "brand_title" => ($product->getBrandVariantName() !='')?$product->getBrandVariantName():''
                ],
                // "bom"=> [
                //       "esin" => "string",
                //       "quantity"=> [
                //         "quantity_number" => 0,
                //         "quantity_uom" => "string"
                //       ]

                //       ],
                "scm_info" => [
                        "case_config" => ($product->getCaseConfig() != '')?$product->getCaseConfig():'',
                        "has_shelf_life" => ($product->getHasShelfLife())?true:false,
                        "is_dangerous" => ($product->getIsLotDangerous())?true:false,
                        "is_fragile" => ($product->getIsFragile())?true:false,
                        "is_lot_controlled" => ($product->getIsLotControlled())?true:false,
                        "lot_control_parameters" => array_map('trim', explode(",", $product->getLotControlParameters())),
                        "carrier_type" => ($product->getCourierType())?$product->getCourierType():'F',
                        "package_dimensions" => [
                            "height_in_cm" => ($product->getPackageHeightInCm() != '')? (int) $product->getPackageHeightInCm() : null,
                            "is_verified" => ($product->getPackageDimensionVerified())?true:false,
                            "length_in_cm" => ($product->getPackageLengthInCm() != '') ? (int) $product->getPackageLengthInCm() :null,
                            "weight_in_kg" => ($product->getPackageWeightInKg() != '')?(int) $product->getPackageWeightInKg():null,
                            "width_in_cm" => ($product->getPackageWidthInCm() !='')? (int)$product->getPackageWidthInCm():null
                        ],
                        "scm_class" => $this->getScmClass($product),
                        "shelf_life_on_pick" => ($product->getShelfLifeOnPick() != '')?(int)$product->getShelfLifeOnPick():'',
                        "shelf_life_on_receipt" => ($product->getShelfLifeOnReceipt() != '')?(int)$product->getShelfLifeOnReceipt():'',
                        "total_shelf_life" => ($product->getTotalShelfLife() !='')?(int)$product->getTotalShelfLife():''
                    ],
                    "purchase_uom" => ($product->getAttributeText('sale_uom') != '')?$product->getAttributeText('sale_uom'):'',
                    "sale_uom" => ($product->getAttributeText('sale_uom') != '')?$product->getAttributeText('sale_uom'):'',

                        "seo_info" => [
                            //"canonical_url" => ($product->getUrlInStore() !='')?$product->getUrlInStore():"",
                            "canonical_url" => $this->getProductCanonicalUrl($product->getSlug(),$product->getEsin()),
                            "keyword" => ($product->getMetaKeyword() != '')?$product->getMetaKeyword():'',
                            "meta" => [
                                "description" => ($product->getMetaDescription() !='')?$product->getMetaDescription():'',
                                "title" => ($product->getMetaTitle() !='')?$product->getMetaTitle():''
                            ],
                            "slug" => ($product->getSlug() != '')?$product->getSlug():'',
                        ],
                         "replenishability_action" => $replensibilityActionValue,
                        "hsn_code" => ($product->getHsnCode() !='')?$product->getHsnCode():'',
                        "label_info" => [
                            "customer_care_address" => ($product->getCustomerCareAddress() != '')?$product->getCustomerCareAddress():'',
                            "imported_by" => ($product->getImportedBy() !='')?$product->getImportedBy():'',
                            "manufactured_by" => ($product->getManufacturedBy() !='')?$product->getManufacturedBy():'',
                            "marketed_by" => ($product->getMarketedBy() !='')?$product->getMarketedBy():'',
                            "packed_by" => ($product->getPackedBy() !='')?$product->getPackedBy():''
                        ],
                        "media" => $this->getMedia($product),
                        "attributes" => $this->getProductAttributes($product),
                        "non_catalog" => ($product->getNonCatalog()) ? true : false,
                        "is_active" => $status,
                        "is_published" => ($product->getIsPublished()) ? true:false,
                        "catalog_version" => "ONLINE"
                    ];

           //$this->facadeHelper->addLog('Original Product Data :'.json_encode($data,true));
           foreach ($data as $key => $value) {
            if(!is_array($value)) {
               if(($value === '' || $value === null)) {
                   unset($data[$key]);
               }
            }
           }
            if($data['scm_info']['shelf_life_on_pick'] === '') {
               unset($data['scm_info']['shelf_life_on_pick']);
            }
            if($data['scm_info']['shelf_life_on_receipt'] === '') {
            unset($data['scm_info']['shelf_life_on_receipt']);
            }
            if($data['scm_info']['total_shelf_life'] === '') {
                unset($data['scm_info']['total_shelf_life']);
            }
            if(count($data['scm_info']['scm_class']) == 0) {
                unset($data['scm_info']['scm_class']);
            }
            if(count($data['barcodes']) == 0) {
                unset($data['barcodes']);
            }

           //$this->facadeHelper->addLog('Product Data :'.json_encode($data,true));
           $this->sendRequest($product, $data);
        } catch (Exception $e) {
            echo "There is some error: " . $e->getMessage();
        }
    }

    /**
     * GET product sku's as offer id
     *@return string[]
     */
    public function getMerchandiseProductsSku($offer_ids){
        
            try{
                
                if(count($offer_ids) > 0){

                    $collection = $this->productCollection->create()
                        ->addAttributeToFilter('sku',array($offer_ids));
                    
                    foreach($collection->getData() as $skuVal){
                        $this->prepareAndSendData($skuVal['entity_id']); 
                    }
                }else{
                    throw new Exception("Product Sku's Array should not be blank");
                }
            
            }catch (Exception $e) {
                echo "There is some error: " . $e->getMessage();
            }
    }

    private function getPerUnitPriceDivisor($product) {
        $prodData = trim($product->getPerUnitPriceDivisor());
        $coverageData = '';
        if($prodData != '') {
            $coverageData = (float)$product->getCoverage();
        }
        return $coverageData;
    }

    private function getProductCanonicalUrl($slug,$esin) {
        if(($slug != '') && ($esin != '')) {
            $baseuRl = $this->scopeConfig->getValue("ibo_google_feed/google_feed_settings/feed_product_base_url");
            $canonicalUrl = $baseuRl.$slug.'/p/'.$esin;
        } else {
            $canonicalUrl = '';
        }
        return $canonicalUrl;
    }

    private function getNavigationCatIds($product) {

        $excludeCategoryIds = [];
        $excludeCategories = $this->scopeConfig->getValue("cate/cat_config/exclude_cagegory");
        if ($excludeCategories!='') {
           $excludeCategoryIds =  explode(',', $excludeCategories);
        }

        $emptyArray[] = '';
        $navCatId = [];
        $catIds = $product->getCategoryIds();
        $rootId = $this->storeManager->getStore(1)->getRootCategoryId();
        $connection = $this->resource->getConnection();
        if(count($product->getCategoryIds()) > 0) {
        	foreach ($catIds as $catId) {
        		$categoryQuery = "SELECT entity_id,path FROM `catalog_category_entity` AS `e` WHERE ((`e`.`entity_id` = '$catId') AND (`e`.`path` LIKE '1/$rootId/%'))";
            	$cateId = $connection->fetchAll($categoryQuery);
            	if(count($cateId) > 0) {
                    $path = explode('/',$cateId[0]['path']);
                    if(count(array_intersect($path, $excludeCategoryIds)) == 0) {
            		    $navCatId[] = $cateId[0]['entity_id'];
                    }
            	}
        	}
        }

        if(count($navCatId) > 0){
        	return $navCatId;
        } else {
            return $emptyArray;
        }
    }

    private function getScmClass($product) {
        $scmClass = [];
        if ($product->getAttributeText('scm_class') !='') {
            $scmClass[] = $product->getAttributeText('scm_class');
        }
        return $scmClass;
    }

    private function getBarcodes($product) {
        $barcode = [];
        if (trim($product->getBarcode()) !='') {
            $barcode[] = trim($product->getBarcode());
        }
        return $barcode;
    }

    private function getOfferBarcodes($product) {
        $barcode = [];
        if (trim($product->getEan()) !='') {
            $barcode[] = trim($product->getEan());
        }
        return $barcode;
    }

    private function getProductAttributes($product)
    {
        $excludeAttributes = trim($this->scopeConfig->getValue("catalog_service/general/exclude_attribute_list"));
        $excludeAttributeslist = [];
        if($excludeAttributes != '') {
            $excludeAttributeslist = explode(',',$excludeAttributes);
        }

        $attributeSetId = $product->getData('attribute_set_id');
        $groupIds = [];
        $attributeData = [];
        $groupCollection = $this->attributeGroupCollection->create()
            ->setAttributeSetFilter($attributeSetId)
            ->load(); // product attribute group collection
        foreach ($groupCollection as $group) {
             $groupIds[$group->getData('attribute_group_id')] = $group->getData('attribute_group_name');

            $groupAttributesCollection = $this->productAttributeCollection->create()
                ->setAttributeGroupFilter($group->getId())
                ->addVisibleFilter()
                ->load(); // product attribute collection
            foreach ($groupAttributesCollection->getItems() as $attribute) {
                if(is_array($excludeAttributeslist) && !in_array($attribute->getAttributeCode(), $excludeAttributeslist))
                {
                        $values = '';
                        // if(is_array($attribute->getFrontend()->getValue($product))){
                        //     $values = $attribute->getFrontend()->getValue($product);
                        //     $attributeData[] = [
                        //         "group" => $groupIds[$attribute->getData('attribute_group_id')],
                        //         "code" => $attribute->getData('attribute_code'),
                        //         "values" => $values
                        //     ];
                        // }
                        if(!is_array($attribute->getFrontend()->getValue($product)) && $product->getData($attribute->getAttributeCode()) !== '' && $product->getData($attribute->getAttributeCode()) !== null){

                            if(in_array($attribute->getData('frontend_input'), ["select", "multiselect","boolean"]))
                            {
                                if(is_object($product->getAttributeText($attribute->getAttributeCode()))){

                                    $values = trim($product->getAttributeText($attribute->getAttributeCode())->getText());
                                }else
                                {
                                    $values = trim($product->getAttributeText($attribute->getAttributeCode()));
                                }
                            }else{
                                $values = trim($product->getData($attribute->getAttributeCode()));
                            }

                            $attributeData[] = [
                                "group" => $groupIds[$attribute->getData('attribute_group_id')],
                                "code" => $attribute->getData('attribute_code'),
                                "values" => [$values]
                            ];
                        }
                }
            }
        }
        /*echo "<pre>";
        print_r($attributeData) ;
        exit;*/
        return $attributeData;
    }

    /**
     * Get product category name.
     *
     * @param type $categoryIds
     * @return type
     */
    private function getCategoryName($categoryIds)
    {
        rsort($categoryIds);
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            //If category doesn't have children then it's 'subclass'
            if (!$category->getChildren()) {
                return $category->getName();
            }
        }
        return '';
    }

    /**
     * Get product media data.
     *
     * @param type $product
     * @return type
     */
    private function getMedia($product)
    {
        $media = [];
        $i = 0;
        if($product->getBaseImageCustom() != '') {

            $media_url = str_replace('https://','',trim($product->getBaseImageCustom()));
            $media_url = explode('/',$media_url);
            $media_id = $media_url[5];

            if(isset($media_url[6])) {
                $media_position = explode('.',$media_url[6]);
                $media_position = explode('-',$media_position[0]);
                $posCount = count($media_position);
                $mediaPosition = $media_position[$posCount -1];
            } else {
                $mediaPosition = 0;
            }

             $media[$i]['media_id'] = $media_id;
             $media[$i]['media_entity'] = 'PRODUCT';
             $media[$i]['media_type'] = 'IMAGE';
             $media[$i]['media_extension'] = (string) pathinfo($product->getBaseImageCustom(),PATHINFO_EXTENSION);
             $media[$i]['alt_text'] = '';
             $media[$i]['position'] = (int) $mediaPosition;
             $media[$i]['title'] = '';
             $media[$i]['is_primary_for_store'] = true;
             $media[$i]['url'] = $product->getBaseImageCustom();
             $i++;
        }

        if($product->getMediaGalleryCustom() != '') {
            $mediaData = explode(',',$product->getMediaGalleryCustom());
            foreach($mediaData as $data) {
                $media_url = str_replace('https://','',trim($data));
                $media_url = explode('/',$media_url);
                $media_id = $media_url[5];

                if(isset($media_url[6])) {
                    $media_position = explode('.',$media_url[6]);
                    $media_position = explode('-',$media_position[0]);
                    $posCount = count($media_position);
                    $mediaPosition = $media_position[$posCount -1];
                } else {
                    $mediaPosition = 0;
                }

                $media[$i]['media_id'] = $media_id;
                $media[$i]['media_entity'] = 'PRODUCT';
                $media[$i]['media_type'] = 'IMAGE';
                $media[$i]['media_extension'] = (string) pathinfo($data,PATHINFO_EXTENSION);
                $media[$i]['alt_text'] = '';
                $media[$i]['position'] = (int) $mediaPosition;
                $media[$i]['title'] = '';
                $media[$i]['is_primary_for_store'] = false;
                $media[$i]['url'] = $data;
                $i++;
            }
        }

        if($product->getVideoUrl() != '') {
            $videoThumbnail = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'video_thumbnail.jpg';
             $media[$i]['media_entity'] = 'PRODUCT';
             $media[$i]['media_type'] = 'VIDEO';
             $media[$i]['thumbnail_url'] = $videoThumbnail;
             $media[$i]['alt_text'] = '';
             $media[$i]['position'] = 0;
             $media[$i]['title'] = '';
             $media[$i]['url'] = $product->getVideoUrl();
            $i++;
        }

        if($product->getPdfPath() != '') {
            $pdfUrl = explode(',',$product->getPdfPath());
            $pdfLabel = [];
            if($product->getPdfLabel() != '') {
                $pdfLabel = explode(',',$product->getPdfLabel());
            }
            $fileExt = '';
            $j = 0;
            foreach ($pdfUrl as $pdfData) {
                $fileExt = 'pdf';
                $media[$i]['media_entity'] = 'PRODUCT';
                $media[$i]['media_type'] = 'DOCUMENT';
                $media[$i]['media_extension'] = (string) (pathinfo($pdfData,PATHINFO_EXTENSION)) ? pathinfo($pdfData,PATHINFO_EXTENSION) : $fileExt;
                $media[$i]['alt_text'] = (isset($pdfLabel[$j]))?trim($pdfLabel[$j]):'';
                $media[$i]['position'] = 0;
                $media[$i]['title'] = (isset($pdfLabel[$j]))?trim($pdfLabel[$j]):'';
                $media[$i]['url'] = trim($pdfData);
                $i++;
                $j++;
            }
       }
       if(count($media) == 0) {
            $productMedia = '';
        } else {
            $productMedia = $media;
        }

        return $productMedia;
    }

    /**
     * Send request to facade
     *
     * @param type $data
     */
    private function sendRequest($product, $data)
    {
        $responseStatus = $this->facadeHelper->send(json_encode($data),$product);
        $catalogServiceSyncCount = 100;
        if ($responseStatus !== 200) {
            $this->updateProductSyncCount($product->getId(), $product->getSku(),'failure');
        }
        if($responseStatus === 200) {
            // Code to call Offer API
            try {
                $offerData = $this->prepareDataForOffer($product);
                //print_r(json_decode($offerData),true);
                $this->sendOfferRequest($offerData);
                $this->facadeHelper->addLog('Offer Request Completed');
            } catch (\Exception $e) {
                $this->facadeHelper->addLog("There is some error in Offer Request"."=======".$e->getMessage());
            }
            // Code to call store catalog push
            try {
                $storeData = $this->prepareDataForStoreCatalogPush($product);
                $this->facadeHelper->sendStoreStockData($storeData);
                $this->facadeHelper->addLog('Store Catalog Push Request Completed');
            } catch (\Exception $e) {
                $this->facadeHelper->addLog("There is some error in Store Catalog push Request"."=======".$e->getMessage());
            }
            
            $this->updateProductSyncCount($product->getId(), $product->getSku(),'success');
        }
        
    }

    protected function updateProductSyncCount($productId,$sku,$status) {
        // $product = $this->productFactory->create()->load($productId);
        // $product->setData('catalog_service_sync_count', $catalogServiceSyncCount);
        // $product->getResource()->saveAttribute($product, 'catalog_service_sync_count');
        $connection = $this->resource->getConnection();
        $catalogServiceTable = $this->resource->getTableName('catalog_service_product_push');
        $sql = "SELECT * FROM $catalogServiceTable Where product_id = $productId";

        $catalogProductData = $connection->query($sql)->fetchAll();

        if($status == 'success') {
            $synCnt = 100;
        } else {

            if(count($catalogProductData) > 0) {
                $synCnt = $catalogProductData[0]['status_flag'] + 1;
            } else {
                $synCnt = 1;
            }
        }

        $data = ["product_id"=>$productId,"status_flag"=> $synCnt,"product_sku"=>$sku]; // Key_Value Pair
        $connection->insertOnDuplicate($catalogServiceTable, $data);
    }

    protected function prepareDataForOffer($product) {

        $prodStatus = $product->getStatus();

        if($prodStatus == 1) {
            $status = true;
        } else {
            $status = false;
        }

        $data = [
            "offer_id" => ($product->getSku())?$product->getSku():'',
            "esin" => ($product->getEsin() != '')?$product->getEsin():'',
            "seller_id" => ($product->getSellerId() != '')?$product->getSellerId():'1',
            "seller_sku_id" => ($product->getSellerSkuId() != '')?$product->getSellerSkuId():$product->getSku(),
            "seller_hsn_code" => ($product->getSellerHsnCode() != '')?$product->getSellerHsnCode():'',
            "barcodes" => $this->getOfferBarcodes($product),
            "lead_time_in_days" => ($product->getLeadTimeInDays())? (int)$product->getLeadTimeInDays():'',
            "fulfillment_method" => ($product->getFulfillmentMethod() != '')?$product->getFulfillmentMethod():'',
            "pod_eligible" => ($product->getPodEligible())?true:false,
            "is_returnable" => ($product->getIsReturnable())?true:false,
            "return_window_in_days" => ($product->getReturnWindowInDays() !='')?(int)$product->getReturnWindowInDays():'',
            "start_datetime" => ($product->getStartDate() !='')?$this->dateTime->formatDate($product->getStartDate()):'',
            "end_datetime" => "",
            "is_active" =>$status,
            "is_published" => ($product->getIsPublished()) ? true:false,
            "catalog_version" => "ONLINE"
        ];

        if ($product->getIsBom()) {
            $data["is_bom"] = true;
            $data["bom_info"]["inventory_basis"] = $product->getAttributeText("inventory_basis");

            if(!empty($product->getBaseOfferId()))
            {
                $quantityUom = '';
                $quantityUom = $this->getBaseOfferIdQuantityUom($product->getBaseOfferId());
                if(!is_null($quantityUom)){
                   $data["bom_info"]['offer_ids'][0] = [
                                    "offer_id" => $product->getBaseOfferId(),
                                    "is_base"  => true,
                                    "quantity" => [
                                        'quantity_number' => 1,
                                        'quantity_uom'    => $quantityUom
                                        ]
                                ];
                }else{
                    $this->facadeHelper->addLog("BaseOfferId QuantityUom is null");
                }

            }
            if(!empty($product->getSecondaryOfferId())){
                $i = 1;
                foreach (json_decode($product->getSecondaryOfferId(), true) as $key => $value) {

                    $data["bom_info"]['offer_ids'][$i] = [
                    "offer_id" => $value["offer_id"],
                    "is_base"  => false,
                    "quantity" => [
                        'quantity_number' => $value["quantity"],
                        'quantity_uom'    => $value["quantity_uom"]
                        ]
                    ];
                    $i ++;
                }
            }
        } else {
            $data["is_bom"] = false;
        }
        if ($product->getIsLooseItem()) {
            $data["is_loose_item"] = true;
            $data["bom_info"]['loose_item_parent_offer_id'] = ($product->getLooseItemParentOfferId() != '') ? $product->getLooseItemParentOfferId() : '';
            $data["bom_info"]['loose_item_parent_conversion_factor'] = ($product->getLooseItemParentConversionFactor() != '') ? (float) $product->getLooseItemParentConversionFactor():'';
            if($data["bom_info"]['loose_item_parent_offer_id'] == '') {
                unset($data["bom_info"]['loose_item_parent_offer_id']);
            }
            if($data["bom_info"]['loose_item_parent_conversion_factor'] == '') {
                unset($data["bom_info"]['loose_item_parent_conversion_factor']);
            }

        } else {
            $data["is_loose_item"] = false;
        }
        if(count($data['barcodes']) == 0) {
            unset($data['barcodes']);
        }
        foreach ($data as $key => $value) {
            if(!is_array($value)) {
               if(($value === '' || $value === null)) {
                   unset($data[$key]);
               }
            }
           }
        /*echo "<pre>";
        print_r(json_encode($data,true));exit;*/
        return json_encode($data);
    }

    protected function prepareDataForStoreCatalogPush($product) {

        $data = [
            "offer_id" => ($product->getSku() != '')?$product->getSku():'',
            "esin" => ($product->getEsin() != '')?$product->getEsin():''
        ];
        $eboGrading = $product->getEboGrading();
        if(trim($eboGrading) == '') {
            $eboGrading = 'ST002:Non-Core|ST003:Non-Core|ST004:Non-Core'; 
        }
        $eboGradingData = explode('|',$eboGrading);
        $replenishabilityText = $product->getReplenishability();
        if(trim($replenishabilityText) == '') {
            $replenishabilityText = 'ST002:I|ST003:I|ST004:I'; 
        }
        $replenishabilityTextData = explode('|',$replenishabilityText);
        $data['nodes'] = [];
        
        $i = 0;
        $j = 0;
        if(count($eboGradingData) > 0) {
            foreach($eboGradingData as $dataEbo) {
                $eboData = explode(':',$dataEbo);
                $data['nodes'][$i]['node_id'] = (trim($eboData[0]) != '')?trim($eboData[0]):'';
                $data['nodes'][$i]['node_type'] = 'STORE';
                $data['nodes'][$i]['ebo_grading'] = (trim($eboData[1]) != '')?trim($eboData[1]):'';;
                $i++;
            }
            foreach($replenishabilityTextData as $dataReplensibility) {
                $replData = explode(':',$dataReplensibility);
                $data['nodes'][$j]['replenishability'] = (trim($replData[1]) != '')?trim($replData[1]):'';
                $j++;
            }
        }

        foreach ($data as $key => $value) {
            if(!is_array($value)) {
               if(($value === '' || $value === null)) {
                   unset($data[$key]);
               }
            }
           }
        /*echo "<pre>";
        print_r(json_encode($data,true));exit;*/
        return json_encode($data);
    }

    public function sendOfferRequest($data) {
        $responseStatus = $this->facadeHelper->sendOfferData($data);
    }

    public function getBaseOfferIdQuantityUom($sku)
    {
        $saleUom = null;
        $product = $this->productRepository->get($sku);
        if($product) {
            $saleUom = $product->getAttributeText('sale_uom');
        }
        return $saleUom;
    }

}

