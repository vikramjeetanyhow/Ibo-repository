<?php

namespace Embitel\Facade\Model;

use Embitel\Facade\Model\FacadeHistory;
use Embitel\Facade\Model\FacadeHistoryFactory;
use Embitel\Facade\Model\ResourceModel\FacadeHistory as FacadeHistoryResourceModel;
use Embitel\Facade\Model\ResourceModel\FacadeHistory\Collection;
use Embitel\Facade\Model\ResourceModel\FacadeHistory\CollectionFactory as FacadeCollectionFactory;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Embitel\Facade\Model\Api;
use Embitel\Facade\Model\OodoApiHelper;
use Magento\TestFramework\Exception\NoSuchActionException;
use Psr\Log\LoggerInterface;
use Zend_Http_Client;

class ProductSync extends AbstractModel
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

    protected $baseMediaUrl;

    protected $facadeCleanUpList = null;

    /**
     * @var Api
     */
    protected $facadeApi;
    private LoggerInterface $logger;
    private OodoApiHelper $oodoApiHelper;
    private \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement;
    private Configurable $productTypeInstance;
    private Collection $facadeHistoryCollection;
    private FacadeCollectionFactory $facadeHistoryCollectionFactory;
    private \Embitel\Facade\Model\FacadeHistoryFactory $facadeHistoryFactory;
    private FacadeHistoryResourceModel $facadeHistoryResourceModel;
    private ResourceConnection $resourceConnection;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $productCollection
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Image $imageHelper
     * @param Api $facadeApi
     * @param LoggerInterface $logger
     * @param OodoApiHelper $oodoApiHelper
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement
     * @param Configurable $productTypeInstance
     * @param FacadeHistoryFactory $facadeHistoryFactory
     * @param FacadeHistoryResourceModel $facadeHistoryResourceModel
     * @param FacadeCollectionFactory $facadeHistoryCollectionFactory
     * @param ObjectManager $objectManager
     */
    public function __construct(
        ScopeConfigInterface                                                       $scopeConfig,
        CollectionFactory                                                          $productCollection,
        ProductFactory                                                             $productFactory,
        ProductRepositoryInterface                                                 $productRepository,
        CategoryRepositoryInterface                                                $categoryRepository,
        Image                                                                      $imageHelper,
        Api                                                                        $facadeApi,
        LoggerInterface                                                            $logger,
        OodoApiHelper                                                              $oodoApiHelper,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productLinkManagement,
        Configurable                                                               $productTypeInstance,
        FacadeHistoryFactory                                                       $facadeHistoryFactory,
        FacadeHistoryResourceModel                                                 $facadeHistoryResourceModel,
        FacadeCollectionFactory                                                    $facadeHistoryCollectionFactory,
        ResourceConnection $resourceConnection
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->productCollection = $productCollection;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->imageHelper = $imageHelper;
        $this->facadeApi = $facadeApi;
        $this->logger = $logger;
        $this->oodoApiHelper = $oodoApiHelper;
        $this->productLinkManagement = $productLinkManagement;
        $this->productTypeInstance = $productTypeInstance;
        $this->facadeHistoryFactory = $facadeHistoryFactory;
        $this->facadeHistoryResourceModel = $facadeHistoryResourceModel;
        $this->facadeHistoryCollectionFactory = $facadeHistoryCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Sync product to facade
     *
     */
    public function syncProducts()
    {
        try {
            $this->syncProductsToFacade();
        } catch (Exception $exception) {
            $this->logger->info($exception->getMessage());
        }
    }

    /**
     * @return void
     */
    protected function syncProductsToFacade(): void
    {
        if (!$this->facadeApi->isEnabled()) {
            return;
        }

        $config = $this->facadeApi->getConfig();
        $productSyncApi = $config->getProductSyncApi();
        if (!$productSyncApi) {
            $this->facadeApi->addLog("Product sync API endpoint is not added in the configuration.");
            return;
        }

        $limit = (int)$this->facadeApi->getConfig()->getFailureHitLimit();
        $batchSize = (int)$this->facadeApi->getConfig()->getBatchSize();

        $facadeHistoryCollection = $this->facadeHistoryCollectionFactory->create()
            ->addFieldToSelect('sku')
            ->addFieldToFilter('hits', ['lt' => $limit])->setPageSize($batchSize);

        foreach ($facadeHistoryCollection->getData() as $data) {
             $result =  $this->prepareAndSendData($data['sku']);
             if ($result == 200) {
                 $this->facadeCleanUpList[] = $data['sku'];
             }
        }

        if (null !== $this->facadeCleanUpList) {
            $skus = implode("','", $this->facadeCleanUpList);
            $connection = $this->resourceConnection->getConnection();
            $sql = "delete from ebo_facade_log where sku in ('$skus');";
            $connection->query($sql);
        }

        unset($this->facadeCleanUpList);
    }

    /**
     * Prepare product data to send to facade
     *
     * @param $sku
     */
    public function prepareAndSendData($sku): int
    {
        try {
            $product = $this->productRepository->get($sku);
            $data = [
                "brand_info" => [
                    "collection" => $product->getBrandCollection(),
                    "color" => $product->getBrandColor(),
                    "grading" => $product->getAttributeText('brand_grading'),
                    "id" => $product->getAttributeText('brand_Id'),
                    "name" => '',
                    "title" => ''
                ],
                "category_id" => $this->getCategoryId($product->getCategoryIds()),
                "category_name" => $this->getCategoryName($product->getCategoryIds()),
                "class" => $product->getClass(),
                "country_of_origin" => $product->getAttributeText('country_of_origin'),
                "department" => $product->getDepartment(),
                "description" => $product->getDescription(),
                "display_name" => "",
                "esin" => $product->getEsin(),
                "hsn_code" => $product->getHsnCode(),
                "identifier" => "",
                "is_active" => $product->getStatus(),
                "is_bom" => $product->getIsBom(),
                "is_published" => $product->getIsPublished(),
                "is_returnable" => $product->getIsReturnable(),
                "label_info" => [
                    "customer_care_address" => $product->getCustomerCareAddress(),
                    "imported_by" => $product->getImportedBy(),
                    "manufactured_by" => $product->getManufacturedBy(),
                    "marketed_by" => $product->getMarketedBy(),
                    "packed_by" => $product->getPackedBy()
                ],
                "media" => $this->getMedia($product),
                "navigation_category_id" => "2", //I assumed root category here
                "non_catalog" => "",
                "display_name" => $product->getName(),

                "offerings" => [
                    [
                        "barcode" => [
                            "data" => $product->getBarcode(),
                            "type" => $product->getBrandIdentifier(),
                        ],
                        "end_date" => "",
                        "esin" => $product->getEsin(),
                        "fulfillment_method" => $product->getFulfillmentMethod(),
                        "is_active" => $product->getIsActiveForSale(),
                        "lead_time_in_days" => $product->getLeadTimeInDays(),
                        "offer_id" => $product->getOfferid(),
                        "pod_eligible" => $product->getPodEligible(),
                        "start_date" => $product->getStartDate(),
                        "status" => $product->getStatus(),
                        "variant_id" => $product->getEsin()
                    ]
                ],

                "org_core_attributes" => [
                    "color" => $product->getPackedBy(),
                    "grading" => $product->getPackedBy(),
                    "org_name" => "",
                    "product_type" => $product->getTypeId(),
                    "title" => ""
                ],
                "product_id" => $product->getId(),
                "product_origin" => "",
                "product_type" => $product->getTypeId(),
                "related_products" => [
                    ""
                ],
                "replenishability" => false,
                "requires_shipping" => "",
                "return_window_in_days" => $product->getReturnWindowInDays(),
                "sale_uom" => $product->getSaleUom(),
                "scm_info" => [
                    "case_config" => $product->getCaseConfig(),
                    "has_shelf_life" => $product->getHasShelfLife(),
                    "is_dangerous" => $product->getIsLotDangerous(),
                    "is_lot_controlled" => $product->getIsLotControlled(),
                    "lot_control_parameters" => array_map('trim', explode(",", $product->getLotControlParameters())),
                    "package_dimension" => [
                        "height_in_cm" => $product->getPackageHeightInCm(),
                        "is_verified" => $product->getPackageDimensionVerified(),
                        "length_in_cm" => $product->getPackageLengthInCm(),
                        "weight_in_kg" => $product->getPackageWeightInKg(),
                        "width_in_cm" => $product->getPackageWidthInCm()
                    ],
                    "scm_class" => [
                        $product->getAttributeText('scm_class')
                    ],
                    "shelf_life_on_pick" => $product->getShelfLifeOnPick(),
                    "shelf_life_on_receipt" => $product->getShelfLifeOnReceipt(),
                    "total_shelf_life" => $product->getTotalShelfLife()
                ],
                "seo_info" => [
                    "canonical_url" => $product->getUrlInStore(),
                    "keyword" => $product->getMetaKeyword(),
                    "meta" => [
                        "description" => $product->getMetaDescription(),
                        "title" => $product->getMetaTitle()
                    ],
                    "slug" => $product->getSlug(),
                ],
                "short_name" => "",
                "start_date" => $product->getStartDate(),
                "sub_class" => $product->getSubclass(),
                "unique_group_id" => $product->getUniqueGroupId(),
                "warranty_in_months" => $product->getWarrantyInMonths()
            ];

         return $this->sendRequest($product, $data);
        } catch (Exception $e) {
            echo "There is some error: " . $e->getMessage();
        }
    }

    /**
     * @param $sku
     * @return void
     */
    public function prepareAndUpdateData($sku)
    {

        try {
            $product = $this->productRepository->get($sku);
            $updateAttributes = explode(',', $product->getOodoSyncUpdate());

            $data = [];

            $configurable_attributes = [];

            try {

                $parentList = $this->productLinkManagement->getParentIdsByChild($product->getId());

                if (count($parentList)) {
                    /** @var Product $configurableProduct */
                    $configurableProduct = $this->productRepository->getById($parentList[0]);

                    $productAttributeOptions = $this->productTypeInstance->getConfigurableOptions($configurableProduct);

                    foreach ($productAttributeOptions as $configs) {
                        foreach ($configs as $config) {
                            if ($config['sku'] == $product->getSku()) {
                                $config_data = [];
                                $config_data['name'] = $config['super_attribute_label'];
                                $config_data['value'] = $config['option_title'];
                                $config_data['technical_name'] = $config['attribute_code'];
                                $configurable_attributes[] = $config_data;
                            }
                        }
                    }

                }


            } catch (NoSuchEntityException $exception) {
                $this->oodoApiHelper->addLog($exception->getMessage());
            }



            $data["sku"] = $product->getSku();
            $data['magento_id'] = $product->getId();

            if (in_array("name", $updateAttributes)) {
                $data["name"] = $product->getName();
            }
            if (in_array("unique_group_id", $updateAttributes))
            {
                $data["unique_group_id"] = $product->getUniqueGroupId();
            }
            if (in_array("mrp", $updateAttributes)) {
                $data["mrp"] = $product->getMrp();
            }
            if (in_array("hsn_code", $updateAttributes)) {
                $data["hsn_code"] = $product->getHsnCode();
            }
            if (in_array("barcode", $updateAttributes)){
                $data["barcode"] = $product->getBarcode();
            }
            if (in_array("esin", $updateAttributes)) {
                $data["esin"] = $product->getEsin();
            }
            if (in_array("sale_uom", $updateAttributes)) {
                $data["sale_uom"] = $product->getAttributeText('sale_uom');
                $data["purchase_uom"] = $product->getAttributeText('sale_uom');
            }
            if (in_array("department", $updateAttributes)) {
                $data["department"] = [
                    "categ_id" => substr($product->getData('ibo_category_id'), 0, 2),
                    "categ_name" => $product->getDepartment(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getDepartment())),
                ];
            }
            if (in_array("class", $updateAttributes)) {
                $data["class"] = [
                    "categ_id" => substr($product->getData('ibo_category_id'), 0, 4),
                    "categ_name" => $product->getClass(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getClass())),
                ];
            }
            if (in_array("subclass", $updateAttributes)) {
                $data["sub_class"] = [
                    "categ_id" => $product->getData('ibo_category_id'),
                    "categ_name" => $product->getSubclass(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getSubclass())),
                ];
            }

            if (in_array("brand_Id", $updateAttributes)) {
                $brandId = $product->getData('brand_Id');
                $brandValue = $product->getAttributeText('brand_Id');
                $brandLabel = $this->getBrandLabel($brandValue);
                $data["brand_id"] = $brandId;
                $data["brand_name"] = $brandLabel;
            }

            if (in_array("replenishability", $updateAttributes)) {
                //$data['replenishability'] = $product->getAttributeText('replenishability');
                $data['replenishability'] = "A";
            }

            if (in_array("replenishability_action", $updateAttributes)) {
                $data['replenishability_action'] = $product->getAttributeText('replenishability_action');
            }

            if (in_array("is_active_for_purchase", $updateAttributes)) {
                $data["purchase_ok"] = $product->getIsActiveForPurchase() ? true : false;
            }

            if (in_array("is_lot_controlled", $updateAttributes)) {
                $data['is_lot_control'] = $product->getData('is_lot_controlled') ? true : false;
                $data['lot_control_parameters'] = $product->getData('lot_control_parameters');
            }

            if (in_array("lot_control_parameters", $updateAttributes)) {
                $data['is_lot_control'] = $product->getData('is_lot_controlled') ? true : false;
                $data['lot_control_parameters'] = $product->getData('lot_control_parameters');


            }     if (in_array("is_catalog_sales", $updateAttributes)) {
                $data['is_catelogue'] = $product->getData('is_catalog_sales') ? true : false;
            }

            if (in_array("non_catalog", $updateAttributes)) {
                $data["non_catalog"] = $product->getNonCatalog() ? true : false;
            }

            $data["configurable_attributes"] = $configurable_attributes;

            if ($product->getIsBom()) {
                $data["is_bom"] = true;
                    $data["secondary_offer_ids"] = json_decode($product->getSecondaryOfferId(), true);
                $data["base_offer_id"] = $product->getBaseOfferId();
                $data["inventory_basis"] = $product->getAttributeText("inventory_basis");
            } else {
                $data["is_bom"] = false;
            }

            $this->sendUpdateOodoRequest($product, $data);


        } catch (NoSuchEntityException $exception) {
            $this->oodoApiHelper->addLog("Product Sku: " . $sku . " Message: " . $exception->getMessage());
        }

    }

    private function sendUpdateOodoRequest($product, $data) {

        $productUpdateApi = $this->oodoApiHelper->getConfig()->getProductUpdateApi();
        $responseStatus = $this->oodoApiHelper->curlSend($productUpdateApi, Zend_Http_Client::PUT, json_encode($data));
        if ($responseStatus != 200) {
            $oodo = ($product->getOodoSyncCount()) ? $product->getOodoSyncCount() + 1 : 1;
            $this->updateOodoProductSyncCount($product->getId(), $oodo);
        }
        if ($responseStatus == 200) {
            $this->updateOodoProductSyncCount($product->getId(), 100, true);
        }
    }


    /**
     * Get product category id.
     *
     * @param type $categoryIds
     * @return type
     */
    private function getCategoryId($categoryIds)
    {
        rsort($categoryIds);
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            //If category doesn't have children then it's 'subclass'
            if (!$category->getChildren()) {
                $categoryEBOID = $category->getCategoryId();
                return $categoryEBOID;
            }
        }
        //If there are no categories without children then return last node of original array.
        return (isset($categoryIds[0])) ? $categoryIds[0] : '';
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
        $customImageSource = $this->scopeConfig->getValue("core_media/service/use_custom_source");

        $media["manuals"][] = [
            "document_type" => $product->getUserManualType(),
            "document_url" => $product->getUserManualUrl()
        ];
        if($customImageSource) {
            $productimages = $product->getMediaGalleryCustom();
            $productimages = explode(',',$productimages);
            foreach ($productimages as $gallerImage) {
                $media["additional_images"][] = [
                    "alt_text" => '',
                    "image_url" => ($gallerImage) ? $gallerImage : ''
                ];
            }

            if ($product->getBaseImageCustom()) {
                $media["primary_image"] = [
                    "alt_text" => "",
                    "image_url" => $product->getBaseImageCustom()
                ];
            }
        } else {
            $productimages = $product->getMediaGalleryImages();
            foreach ($productimages as $productimage) {
                $media["additional_images"][] = [
                    "alt_text" => $productimage['label'],
                    "image_url" => $productimage['url']
                ];
            }

            if ($product->getImage()) {
                $media["primary_image"] = [
                    "alt_text" => "",
                    "image_url" => $this->productFactory->create()->getMediaConfig()->getMediaUrl($product->getImage())
                ];
            }
        }

        $media["videos"][] = [
            "alt_text" => "",
            "video_url" => $product->getVideoUrl()
        ];

        return $media;
    }

    /**
     * Send request to facade
     *
     * @param $data
     */
    private function sendRequest($product, $data): int
    {
        $productSyncApi = $this->facadeApi->getConfig()->getProductSyncApi();
        $responseStatus = $this->facadeApi->send($productSyncApi, Zend_Http_Client::POST, json_encode($data));

        try {
            $facadeHistory = $this->facadeHistoryFactory->create();
            $this->facadeHistoryResourceModel->load($facadeHistory, $product->getSku(), 'sku');

            if ($facadeHistory->getId()) {
                if ($responseStatus != 200) {
                    $facadeHistory->setHits($facadeHistory->getHits() + 1);
                    $this->facadeHistoryResourceModel->save($facadeHistory);
                }
            }
        } catch (\Exception $exception) {
            $this->facadeApi->log('For SKU: ' . $product->getSku() . ', Error Message: ' . $exception->getMessage());
        }

        return $responseStatus;
    }

    /**
     * Update product sync count.
     *
     * @param type $productId
     * @param type $count
     */
    protected function updateProductSyncCount($productId, $count)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setData('facade_sync_count', $count);
        $product->getResource()->saveAttribute($product, 'facade_sync_count');
    }

    public function syncOdooProducts()
    {
        $this->oodoApiHelper->addLog("Odoo Sync cron Started");
        try {
            $this->syncProductsToOdoo();
        } catch (Exception $exception) {
            $this->logger->info($exception->getMessage());
        }
        $this->oodoApiHelper->addLog("Odoo Sync cron Stopped");
    }

    public function updateOdooProducts() {
        if (!$this->oodoApiHelper->isEnabled()) {
            return;
        }
        $this->oodoApiHelper->addLog("Odoo Update cron Started");
        try {
            $this->updateProductsToOdoo();
        } catch (Exception $exception) {
            $this->logger->info($exception->getMessage());
        }
        $this->oodoApiHelper->addLog("Odoo Update cron Stopped");
    }

    protected function updateProductsToOdoo(): void
    {
        if (!$this->oodoApiHelper->isEnabled()) {
            return;
        }
        $config = $this->oodoApiHelper->getConfig();
        $OodoProductSycnApi = $config->getProductUpdateApi();
        if (!$OodoProductSycnApi) {
            $this->oodoApiHelper->addLog("Product update API endpoint is not added in the configuration");
            return;
        }

        $limit = 100 + (int)$this->oodoApiHelper->getConfig()->getUpdateFailureHitLimit();
        $pageSize = (int)$this->oodoApiHelper->getConfig()->getBatchSize();

        $productCollection = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('oodo_sync_update', ['neq' => ""])
            ->addAttributeToFilter('oodo_sync_count', ['gteq' => 100])
            ->addAttributeToFilter('oodo_sync_count', ['lt' => $limit])
            ->setPageSize($pageSize)->setCurPage(1);

        foreach ($productCollection as $product) {
            $this->prepareAndUpdateData($product->getSku());
        }
    }

    protected function syncProductsToOdoo(): void
    {
        if (!$this->oodoApiHelper->isEnabled()) {
            return;
        }
        $config = $this->oodoApiHelper->getConfig();
        $OodoProductSycnApi = $config->getProductCreateApi();
        if (!$OodoProductSycnApi) {
            $this->oodoApiHelper->addLog("Product sync API endpoint is not added in the configuration");
            return;
        }

        $limit = (int)$this->oodoApiHelper->getConfig()->getFailureHitLimit();
        $pageSize = (int)$this->oodoApiHelper->getConfig()->getBatchSize();


        $productCollection = $this->productCollection->create()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter([
                ['attribute' => 'oodo_sync_count', 'null' => true],
                ['attribute' => 'oodo_sync_count', 'lt' => $limit]
            ])->setPageSize($pageSize)->setCurPage(1);

        foreach ($productCollection as $product) {
            $this->prepareOdooDataAndSend($product->getSku());
        }
    }

    public function prepareOdooDataAndSend($sku)
    {
        //Odoo product create
        try {
            $product = $this->productRepository->get($sku);

            $configurable_attributes = [];

            try {

                $parentList = $this->productLinkManagement->getParentIdsByChild($product->getId());

                if (count($parentList)) {
                    /** @var Product $configurableProduct */
                    $configurableProduct = $this->productRepository->getById($parentList[0]);

                    $productAttributeOptions = $this->productTypeInstance->getConfigurableOptions($configurableProduct);

                    foreach ($productAttributeOptions as $configs) {
                        foreach ($configs as $config) {
                            if ($config['sku'] == $product->getSku()) {
                                $config_data = [];
                                $config_data['name'] = $config['super_attribute_label'];
                                $config_data['value'] = $config['option_title'];
                                $config_data['technical_name'] = $config['attribute_code'];
                                $configurable_attributes[] = $config_data;
                            }
                        }
                    }

                }


            } catch (NoSuchEntityException $exception) {
                $this->oodoApiHelper->addLog($exception->getMessage());
            }
            $brandId = $product->getData('brand_Id');
            $brandValue = $product->getAttributeText('brand_Id');
            $brandLabel = $this->getBrandLabel($brandValue);

            $saleUom = $product->getAttributeText('sale_uom');
            if (!$saleUom) {
                $saleUom = $product->getData('sale_uom');
            }

            $data = [
                "name" => $product->getName(),
                "type" => "product",
                "magento_id" => $product->getId(),
                "sku" => $product->getSku(),
                "unique_group_id" => $product->getUniqueGroupId(),
                "mrp" => $product->getPrice(),
                "hsn_code" => $product->getHsnCode(),
                "hsn_description" => $product->getDescription(),
                "barcode" => $product->getBarcode(),
                "trading_option" => "traded_goods",
                "esin" => $product->getEsin(),
                "sale_uom" => $saleUom,
                "purchase_uom" => $saleUom,
                "department" => [
                    "categ_id" => substr($product->getData('ibo_category_id'), '0', 2),
                    "categ_name" => $product->getDepartment(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getDepartment())),
                ],
                "class" => [
                    "categ_id" => substr($product->getData('ibo_category_id'), '0', 4),
                    "categ_name" => $product->getClass(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getClass())),
                ],
                "sub_class" => [
                    "categ_id" => $product->getData('ibo_category_id'),
                    "categ_name" => $product->getSubclass(),
                    "categ_code" => str_replace(' ', '_', strtolower($product->getSubclass())),
                ],
                "brand_id" => $brandId,
                "brand_name" => $brandLabel,
                "sale_ok" => true,
                "purchase_ok" => $product->getIsActiveForPurchase() ? true : false,
                "purchase_method" => "receive",
                "invoice_policy" => "order",
                "configurable_attributes" => $configurable_attributes,
                "is_lot_control" => $product->getData('is_lot_controlled') ? true : false,
                "lot_control_parameters" => $product->getData('lot_control_parameters'),
                "is_catelogue" => $product->getData('is_catalog_sales') ? true : false,
                "replenishability" => "A", //$product->getAttributeText("replenishability"),
                "replenishability_action" => $product->getAttributeText("replenishability_action"),
                "non_catalog" => $product->getNonCatalog() ? true : false
            ];
            if ($product->getIsBom()) {
                $data["is_bom"] = true;
                    $data["secondary_offer_ids"] = json_decode($product->getSecondaryOfferId(), true);
                $data["base_offer_id"] = $product->getBaseOfferId();
                $data["inventory_basis"] = $product->getAttributeText("inventory_basis");
            } else {
                $data["is_bom"] = false;
            }

            $dataCPandSP = $this->getOdooValues($product->getSku());
            if (!empty($dataCPandSP) && array_key_exists("cp_json", $dataCPandSP) && $dataCPandSP['cp_json'] != '') {
                $data['vendor_pricelist'] = [json_decode($dataCPandSP['cp_json'], true)];
            }
            if (!empty($dataCPandSP) && array_key_exists("sp_json", $dataCPandSP) && $dataCPandSP['sp_json'] != '') {
                $data['customer_pricelist'] = [json_decode($dataCPandSP['sp_json'], true)];
            }
            $this->sendOodoRequest($product, $data);
        } catch (Exception $exception) {
            $this->oodoApiHelper->addLog($exception->getMessage());
        }

    }

    private function sendOodoRequest(ProductInterface $product, array $data)
    {
        $productSyncApi = $this->oodoApiHelper->getConfig()->getProductCreateApi();
        $responseStatus = $this->oodoApiHelper->send($productSyncApi, Zend_Http_Client::POST, json_encode($data));
        $odooSyncCount = 100;

        if ($responseStatus != 200) {
            $odooSyncCount = ($product->getOodoSyncCount()) ? $product->getOodoSyncCount() + 1 : 1;
        }
        $this->updateOodoProductSyncCount($product->getId(), $odooSyncCount);
    }

    /**
     * Update product sync count.
     *
     * @param int $productId
     * @param int $count
     */
    protected function updateOodoProductSyncCount(int $productId, int $count, $update = false)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setData('oodo_sync_count', $count);
        if ($update) {
            $product->setData('oodo_sync_update', "");
            $product->getResource()->saveAttribute($product, 'oodo_sync_update');
        }
        $product->getResource()->saveAttribute($product, 'oodo_sync_count');
    }

    /**
     * @param $brandId
     * @param string $brandLabel
     * @return string
     */
    private function getBrandLabel($brandId, string $brandLabel=''): string
    {
        if (is_null($brandId) || $brandId != false || !empty($brandId)) {
            $brandIdFormat = explode("_", $brandId);
            if (count($brandIdFormat) == 0) {
                $brandLabel = ucfirst($brandIdFormat);
            } else {
                $count = 0;
                foreach ($brandIdFormat as $char) {
                    if ($count) {
                        $brandLabel .= " " . ucfirst($char);
                    } else {
                        $brandLabel = ucfirst($char);
                        $count++;
                    }

                }
            }
        }
        return $brandLabel;
    }

    protected function getMediaBasePath() {
        if(!$this->baseMediaUrl) {
            $this->baseMediaUrl = trim($this->scopeConfig->getValue("core_media/service/get_media_api"));
        }
        return $this->baseMediaUrl;
    }

    public function getOdooValues($sku) {
        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from('embitel_quotation_odoo_values')
                ->where('sku =? ', $sku);

            $result = $connection->fetchRow($select);

            return $result;
        } catch (\Exception $ex) {
            $this->productMetadata->log("Error in Get Odoo values: " . $ex->getMessage());
        }
    }
}
