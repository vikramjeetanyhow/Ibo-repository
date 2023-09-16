<?php

namespace Ibo\Quotation\Model;

use Ibo\Quotation\Model\CategoryData;
use Ibo\Quotation\Model\ProductFields;
use Embitel\ProductImport\Model\Import\ProductImportHandler;
use Ibo\RegionalPricing\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Embitel\Facade\Model\FacadeHistoryFactory;
use Embitel\Facade\Model\ProductServiceSync;
use Embitel\Facade\Model\ProductSync;
use Embitel\Facade\Model\ResourceModel\FacadeHistory as FacadeHistoryResourceModel;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\ProductImport\Model\Import\ProductFieldProcessor;
class ProductMetadata
{
    /**
     * IBO Module Status Config Path
     */
    public const XML_PATH_QUOTATION_MODULE_STATUS = 'ibo_quotation/config/status';

    /**
     * IBO Module Log Status
     */
    public const XML_PATH_QUOTATION_LOG_ENABLE = 'ibo_quotation/config/log_enable';

    /**
     * Store Allowed Values
     */
    const XML_PATH_AVAILABLE_STORE_VALUES = 'ebo/ebo_product_create/current_available_stores';

    /**
     * @var CategoryData
     */
    protected $categoryData;

    /**
     * @var ProductFields
     */
    protected $productFields;

    /**
     * @var ProductImportHandler
     */
    protected $productImportHandler;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var FacadeHistoryFactory
     */
    protected $facadeHistoryFactory;

    /**
     * @var FacadeHistoryResourceModel
     */
    protected $facadeHistoryResourceModel;

    /**
     * @var ProductServiceSync
     */
    protected $productServiceSync;

    /**
     * @var ProductSync
     */
    protected $productSync;

    /**
     * @param CategoryData $categoryData
     * @param ProductFields $productFields
     * @param ProductImportHandler $productImportHandler
     * @param AttributeRepository $attributeRepository
     * @param CollectionFactory $productCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param FacadeHistoryFactory $facadeHistoryFactory
     * @param FacadeHistoryResourceModel $facadeHistoryResourceModel
     * @param ProductServiceSync $productServiceSync
     * @param ProductSync $productSync
     */
    public function __construct(
        CategoryData $categoryData,
        ProductFields $productFields,
        ProductImportHandler $productImportHandler,
        AttributeRepository $attributeRepository,
        CollectionFactory $productCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        FacadeHistoryFactory $facadeHistoryFactory,
        FacadeHistoryResourceModel $facadeHistoryResourceModel,
        ProductServiceSync $productServiceSync,
        ProductSync $productSync,
        ProductFieldProcessor $productFieldProcessor
    ) {
        $this->categoryFields = $categoryData;
        $this->productFields = $productFields;
        $this->productImportHandler = $productImportHandler;
        $this->attributeRepository = $attributeRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->facadeHistoryFactory = $facadeHistoryFactory;
        $this->facadeHistoryResourceModel = $facadeHistoryResourceModel;
        $this->productServiceSync = $productServiceSync;
        $this->productSync = $productSync;
        $this->productFieldProcessor = $productFieldProcessor;
    }

    /**
     * Get category data using ibo category id
     *
     * @param type $productData
     * @return type
     */
    public function getProductData($productData)
    {
        $this->log("Prepare product data - START");
        try {
            $categoryData = $this->categoryFields->getCategoryFields($productData['ibo_category_id']);
            if (array_key_exists('error', $categoryData)) {
                return ['error_in_create' => $categoryData['error']];
            }

            //Get tax class by hsn code
            $this->log("Before get tax class.");
            //$taxClass = $this->productFields->getTaxClassId($productData);
            $taxClass = $this->productFieldProcessor->getTaxClassId($productData['hsn_code']);
            if ($taxClass) {
                $productData['tax_class_id'] = $taxClass;
            } else {
                return ['error_in_create' => 'Tax class does not exist with given hsn code.'];
            }
            $this->log("after get tax class.");

            $defaultValues = $this->productFields->getDefaultValues($categoryData['attribute_set_id']);
            $productData['unique_group_id'] = $this->getUniqueGroupId($productData);

            //Update brand_Id key for magento attribute
            $brandId = $productData['brand_id'];
            unset($productData['brand_id']);
            $productData['brand_Id'] = $brandId;

            //Replenishability value
            $replenishability = $this->getReplenishabilityValues();
            if ($replenishability == '') {
                return ['error_in_create' => 'Replenishability is not valid. Please check store values in configuration.'];
            }
            $productData['replenishability'] = $replenishability;

            //EBO Grading value
            $eboGrading = $this->getEboGradingValues();
            if ($eboGrading == '') {
                return ['error_in_create' => 'EBO Grading is not valid. Please check store values in configuration.'];
            }
            $productData['ebo_grading'] = $eboGrading;

            $productData['name'] = $this->getProductName($productData, $categoryData['subclass']);

            if (array_key_exists('mrp', $productData)) {
                $productData['price'] = $productData['mrp'];
            } else {
                $productData['price'] = 0;
                $productData['mrp'] = 0;
            }

            if (!array_key_exists('vendor_id', $productData) || trim($productData['vendor_id']) == '') {
                $productData['vendor_id'] = 1;
            }
            if (array_key_exists('vendor_sku_id', $productData) && trim($productData['vendor_sku_id']) != '') {
                $productData['seller_sku_id'] = $productData['vendor_sku_id'];
                unset($productData['vendor_sku_id']);
            }

            $sku = $this->productImportHandler->genSku();
            $productData['sku'] = $sku;
            $productData['url_key'] = $sku;
            $productData['barcode'] = $sku;
            $productData['offerid'] = $sku;
            $productData['meta_title'] = $productData['name'];
            $productData['esin'] = $this->productImportHandler->genEsin();
            $productData['slug'] = $productData['esin'];
            $productData['production_start_date'] =  date("m/d/Y");

            $productData['type_id'] = 'simple';
            $productData['website_ids'] = [1];
            $productData['store_id'] = 0;
            $productData['seller_id'] = 1;
            $productData['approval'] = 2;
            $productData['is_published'] = 0;
            $productData['visibility'] = Visibility::VISIBILITY_BOTH;
            $productData['category_ids'] = $categoryData['category_id'];
            $this->log("Prepare product data - END");
            return array_merge($productData, $defaultValues, $categoryData);
        } catch (Exception $ex) {
            return ['error_in_create' => 'Product create error: ' . $ex->getMessage()];
        }
    }

    /**
     * Product data to update
     *
     * @param type $params
     * @param type $product
     */
    public function getProductDataToUpdate($params, $product)
    {
        unset($params['sku']);
        $allowedFieldsForUpdate = $this->productFields->getAllowedFieldsForUpdate();
        $data = [];
        foreach ($params as $attributeCode => $attributeValue) {
            if (!in_array($attributeCode, $allowedFieldsForUpdate)) {
                $data[] = $attributeCode;
            } elseif ($attributeCode == 'hsn_code') {
                //$taxClass = $this->productFields->getTaxClassId($params);
                $taxClass = $this->productFieldProcessor->getTaxClassId($params['hsn_code']);
                if ($taxClass) {
                    $params['tax_class_id'] = $taxClass;
                } else {
                    $message = "Tax class dose not exist with given hsn code: " . $params['hsn_code'];
                    throw new \Magento\Framework\Webapi\Exception(__($message));
                }
            }
        }

        if (!empty($data)) {
            $message = "Some fields are not allowed to update are: " . implode(", ", $data);
            throw new \Magento\Framework\Webapi\Exception(__($message));
        }

        //Odoo sync
        if ($product->getOodoSyncCount() > 99) {
            $odooUpdateFields = $this->productFields->getOdooUpdateFields();
            if (array_intersect(array_keys($params), $odooUpdateFields)) {
                $odooSyncUpdate = '';
                if (is_array($params) && count($params) > 0) {
                    $odooSyncUpdateAttributes = array_filter($params, 'strlen');
                    unset($odooSyncUpdateAttributes['tax_class_id']);
                    $odooSyncUpdate = implode(",", array_keys($odooSyncUpdateAttributes));
                    $odooSyncUpdate = trim($odooSyncUpdate);
                }
                if ($odooSyncUpdate != '' && $odooSyncUpdate != $product->getOdooSyncUpdate()) {
                    $existingFields = explode(",", $product->getOodoSyncUpdate());
                    $newFields = explode(",", $odooSyncUpdate);
                    $updateFields = array_unique(array_merge($existingFields, $newFields));
                    $updateFields = array_filter($updateFields, 'strlen');
                    $params['oodo_sync_update'] = implode(",", $updateFields);
                    $params['oodo_sync_count'] = 100;
                }
            }
        }

        return $params;
    }

    /**
     * Check if unique group id already exist in any product
     *
     * @param type $data
     * @return type
     */
    public function getIsUniqueGroupIdExist($data)
    {
        $uniqueGroupId = $this->getUniqueGroupId($data);
        $products = $this->productCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('unique_group_id', $uniqueGroupId);
        return $products;
        // return ($products->getSize() > 0) ? true : false;
    }

    /**
     * Get unique group id
     *
     * @param type $data
     * @return type
     */
    public function getUniqueGroupId($data)
    {
        return $data['ibo_category_id'] . "-" . $data['brand_model_number'] . "-" . $data['brand_id'];
    }

    /**
     * Get product name
     *
     * @param type $data
     * @param type $subclass
     * @return type
     */
    public function getProductName($data, $subclass)
    {
        $brandLabel = $this->getOptionLabel('brand_Id', $data['brand_Id']);
        return $brandLabel . " " . $data['brand_model_number'] . " " . $subclass;
    }

    /**
     * Get replenishability value for all the stores.
     *
     * @return type
     */
    public function getReplenishabilityValues()
    {
        $storeValues = $this->getConfig(self::XML_PATH_AVAILABLE_STORE_VALUES);
        if ($storeValues != '') {
            $stores = array_map('trim', explode(",", $storeValues));
            $replenishability = [];
            foreach ($stores as $store) {
                $replenishability[] = $store . ":CL-CS";
            }
            return implode("|", $replenishability);
        }
        return '';
    }

    /**
     * Get EBO Grading value for all the stores.
     *
     * @return type
     */
    public function getEboGradingValues()
    {
        $storeValues = $this->getConfig(self::XML_PATH_AVAILABLE_STORE_VALUES);
        if ($storeValues != '') {
            $stores = array_map('trim', explode(",", $storeValues));
            $eboGrading = [];
            foreach ($stores as $store) {
                $eboGrading[] = $store . ":Non-Core";
            }
            return implode("|", $eboGrading);
        }
        return '';
    }

    /**
     * Get product attribute option label
     *
     * @param type $attributeCode
     * @param type $optionId
     * @return type
     */
    public function getOptionLabel($attributeCode, $optionId)
    {
        $attribute = $this->attributeRepository->get($attributeCode);
        return $attribute->getSource()->getOptionText($optionId);
    }

    /**
     * Get product attribute option id
     *
     * @param type $attributeCode
     * @param type $optionLabel
     * @return type
     */
    public function getOptionId($attributeCode, $optionLabel)
    {
        $attribute = $this->attributeRepository->get($attributeCode);
        return $attribute->getSource()->getOptionId($optionLabel);
    }

    /**
     * Sync product to facade & odoo
     *
     * @param type $id
     * @param type $sku
     */
    public function syncProducts($id, $sku)
    {
        //$this->syncProductToFacade($sku);
        $this->syncProductToCs($id);
        return $this->productFields->getProductCsStatusSync($id);
    }

    /**
     * Sync product to catalog service
     *
     * @param type $id
     */
    public function syncProductToCs($id)
    {
        $this->log("Product sync to catalog service - START");
        $this->productServiceSync->prepareAndSendData($id);
        $this->log("Product sync to catalog service - DONE");
    }

    /**
     * Sync product create & update to oddo.
     *
     * @param type $product
     * @param type $requestType
     */
    public function syncProductToOodo($product, $requestType)
    {
        $oodoSyncCount = $product->getOodoSyncCount();
        if ($oodoSyncCount < 100) {
            $this->syncProductCreateToOodo($product->getSku());
        } elseif ($oodoSyncCount >= 100 && $requestType == 'update') {
            $this->syncProductUpdateToOodo($product->getSku());
        }
    }

    /**
     * Sync product create to oodo
     *
     * @param type $sku
     */
    public function syncProductCreateToOodo($sku)
    {
        $this->log("Product create sync to oodo - START");
        $this->productSync->prepareOdooDataAndSend($sku);
        $this->log("Product create sync to oodo - DONE");
    }

    /**
     * Sync product update to oodo
     *
     * @param type $sku
     */
    public function syncProductUpdateToOodo($sku)
    {
        $this->log("Product update sync to oodo - START");
        $this->productSync->prepareAndUpdateData($sku);
        $this->log("Product update sync to oodo - DONE");
    }

    /**
     * Sync product to facade
     *
     * @param type $sku
     */
    public function syncProductToFacade($sku)
    {
        $this->log("Product sync to facade - START");
        $facadeHistory = $this->facadeHistoryFactory->create();
        $this->facadeHistoryResourceModel->load($facadeHistory, $sku, 'sku');
        if (null == $facadeHistory->getId()) {
            $facadeHistory->setSku($sku);
            $this->facadeHistoryResourceModel->save($facadeHistory);
        } else {
            $facadeHistory->setHits(0);
            $this->facadeHistoryResourceModel->save($facadeHistory);
        }
        $this->log("Product sync to facade - DONE");
    }

    /**
     * Check if module is enabled or not
     *
     * @return type
     */
    public function isModuleEnabled()
    {
        return ($this->getConfig(self::XML_PATH_QUOTATION_MODULE_STATUS)) ? true : false;
    }

    /**
     * Check if log is enabled or not
     *
     * @return type
     */
    public function isLogEnabled()
    {
        return ($this->getConfig(self::XML_PATH_QUOTATION_LOG_ENABLE)) ? true : false;
    }

    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Log
     *
     * @param type $message
     * @return type
     */
    public function log($message)
    {
        if (!$this->isLogEnabled()) {
            return;
        }
        $this->productFields->log($message);
    }

    public function getSPConfig($params) {
        return Array(
            "price_group" => $params['price_group'] ?? $this->getConfig('ibo_quotation/sp_config/pricegroup'),
            "price_type" => $params['price_type'] ?? $this->getConfig('ibo_quotation/sp_config/price_type'),
            "min_quantity" => $params['min_quantity'] ?? $this->getConfig('ibo_quotation/sp_config/min_quantity'),
            "reason_for_pricing" => $params['reason_for_pricing'] ?? $this->getConfig('ibo_quotation/sp_config/reason_for_pricing'),
            "priority" => $params['priority'] ?? $this->getConfig('ibo_quotation/sp_config/priority'),
            "end_date" => $params['end_date'] ?? $this->getConfig('ibo_quotation/sp_config/end_date')
        );
    }

    public function getCPConfig($params) {
        return Array(
            "vendor_lead_time_in_days" => $params['vendor_lead_time_in_days'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_lead_time'),
            "vendor_mrp" => $params['vendor_mrp'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_product_mrp'),
            "vendor_list_price" => $params['vendor_list_price'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_list_price'),
            "vendor_tot_price_type" => $params['vendor_tot_price_type'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_tot_price_type'),
            "vendor_tot_margin_percentage" => $params['vendor_tot_margin_percentage'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_tot_margin_pct'),
            "vendor_moq" => $params['vendor_moq'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_moq'),
            "vendor_purchase_mode" => $params['vendor_purchase_mode'] ?? $this->getConfig('ibo_quotation/cp_config/vendor_purchase_mode')
        );
    }
}
