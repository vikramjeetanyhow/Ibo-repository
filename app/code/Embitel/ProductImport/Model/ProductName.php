<?php

namespace Embitel\ProductImport\Model;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\ProductImport\Model\Import\ProductFieldProcessor;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Framework\App\ResourceConnection;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;
use Embitel\Catalog\Helper\Data as CatalogHelper;

/**
 * This class to get products to regenerate product title.
 */
class ProductName
{
    /**
     * Cron status path
     */
    const TITLE_FROM_CATEGORY_CONFIG_PATH = 'ebo/ebo_product_title/title_from_category';

    /**
     * Cron status path
     */
    const CRON_LOG_STATUS_PATH = 'ebo/ebo_product_title/log_active';

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductFieldProcessor
     */
    protected $productFieldProcessor;

    /**
     * @var Action
     */
    protected $action;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    protected $connection = null;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $titleRules = [];

    protected $attributeFrontendInput = [];

    protected $configValue = [];

    protected $brandOptions = [];

    /**
     * @param CategoryFactory $categoryFactory
     * @param ProductFactory $productFactory
     * @param ProductFieldProcessor $productFieldProcessor
     * @param Action $action
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        ProductFactory $productFactory,
        ProductFieldProcessor $productFieldProcessor,
        Action $action,
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        ProductPushHelper $productPushHelper,
        CatalogHelper $catalogHelper
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->productFactory = $productFactory;
        $this->productFieldProcessor = $productFieldProcessor;
        $this->action = $action;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->scopeConfig = $scopeConfig;
        $this->productPushHelper = $productPushHelper;
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Get config field value by path
     *
     * @param type $path
     * @return type
     */
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Generate product title based on given data
     *
     * @param type $productData
     * @return string
     */
    public function getProductName($productData)
    {
        //If title create from category field is disable then return empty title.
        if (!$this->getConfig(self::TITLE_FROM_CATEGORY_CONFIG_PATH)) {
            return '';
        }

        //Manual title START
        if (isset($productData['manual_title']) && trim($productData['manual_title']) != '') {
            return trim($productData['manual_title']);
        }
        if (isset($productData['sku'])) {
            $product = $this->productFactory->create()->loadByAttribute('sku', $productData['sku']);
            if ($product && $product->getIsProductHavingManualTitle()) {
                return $product->getName();
            }
        }
        //Manual title END

        $titleFields = [];
        if (isset($productData['category_ids']) && !empty($productData['category_ids'])) {
            $titleFields = $this->getTitleRuleFields($productData['category_ids']);
        } else {
            $titleFields = $this->getTitleRuleFields($product->getCategoryIds());
        }

        //If category attribute "Title Name Rule" is empty then return
        if (empty($titleFields)) {
            return '';
        }

        $title = [];
        foreach ($titleFields as $titleField) {
            $titleField = trim($titleField);
            if (strpos($titleField, '[') !== false || strpos($titleField, ']') !== false) {
                $titleField = str_replace("[", "", $titleField);
                $titleField = str_replace("]", "", $titleField);
                $title[] = trim($titleField);
            } else {
                if (isset($productData[$titleField])) {
                    if ($titleField == 'brand_Id') {
                        $brandName = $this->productFieldProcessor->getBrandNameById($productData[$titleField]);
                        $title[] = trim($brandName);
                    } else {
                        $title[] = trim($productData[$titleField]);
                    }
                }
            }
        }

        $titleString = implode(" ", $title);
        $titleString = str_replace("   ", " ", $titleString);
        $titleString = str_replace("  ", " ", $titleString);
        $titleString = str_replace("  ", " ", $titleString);
        return trim($titleString);
    }

    /**
     * Check if title creation using category field is enable in configuration.
     *
     * @return type
     */
    public function isTitleFromCategoryEnable()
    {
        if (!array_key_exists('title_from_config', $this->configValue)) {
            $titleFromCat = $this->getConfig(self::TITLE_FROM_CATEGORY_CONFIG_PATH);
            $this->configValue['title_from_config'] = ($titleFromCat) ? $titleFromCat : 0;
        }
        return $this->configValue['title_from_config'];
    }

    /**
     * Check if title creation using category field is enable in configuration.
     *
     * @return type
     */
    public function isLogActive()
    {
        if (!array_key_exists('is_log_enable', $this->configValue)) {
            $logStatus = $this->getConfig(self::CRON_LOG_STATUS_PATH);
            $this->configValue['is_log_enable'] = ($logStatus) ? $logStatus : 0;
        }
        return $this->configValue['is_log_enable'];
    }

    /**
     * Regenerate product title while product update.
     *
     * @param type $product
     * @param type $data
     * @param type $updateByCommand
     * @return type
     */
    public function getUpdatedProductName($product, $data = [], $updateByCommand = false)
    {
        if (empty($data) && !$updateByCommand) {
            return '';
        }

        //If title create from category field is disable then return empty title.
        if ($this->isTitleFromCategoryEnable() == 0) {
            $this->log("Product title generate functionality is disable.");
            return '';
        }

        //For product create: if product is is_catalog_sales then return vendor_sku_title
        //For product create: if product is having manual_title then return manual_title
        if (!$product) {
            if (isset($data['is_catalog_sales']) && ($data['is_catalog_sales'] == '1' || $data['is_catalog_sales'] == 1 || strtolower($data['is_catalog_sales']) == 'yes') && isset($data['vendor_sku_title'])) {
                return trim($data['vendor_sku_title']);
            } else if (isset($data['manual_title']) && trim($data['manual_title']) != '') {
                return trim($data['manual_title']);
            }
        }

        //If product having manual_title then return
        $manualTitleUpdate = false;
        if ($product && $product->getIsProductHavingManualTitle()) {
            if (array_key_exists("is_product_having_manual_title", $data)
                && in_array(strtolower($data['is_product_having_manual_title']), [0, '0', 'no'])) {
                $manualTitleUpdate = true;
            } else {
                return '';
            }
        }

        //Get ibo category ID from existing data or product.
        $iboCategoryId = (isset($data['ibo_category_id']) && !empty($data['ibo_category_id'])) ? $data['ibo_category_id'] : $product->getIboCategoryId();
        $titleFields = $this->getTitleRuleFieldsByIboCategoryId($iboCategoryId);

        //If category attribute "Title Name Rule" is empty then return
        if (empty($titleFields)) {
            return '';
        }

        if (array_key_exists('brand_Id', $data)) {
            $data['brand_Id'] = $this->getBrandName($data['brand_Id']);
        }

        $dataKeys = array_keys($data);
        $diff = array_diff($dataKeys, $titleFields);
        if ($manualTitleUpdate || $updateByCommand || count($dataKeys) > count($diff)) {
            $title = [];
            foreach ($titleFields as $titleField) {
                $titleField = trim($titleField);
                if (strpos($titleField, '[') !== false || strpos($titleField, ']') !== false) {
                    $titleField = str_replace("[", "", $titleField);
                    $titleField = str_replace("]", "", $titleField);
                    $title[] = trim($titleField);
                } else {
                    if (isset($data[$titleField])) {
                        $title[] = trim($data[$titleField]);
                    } else {
                        $title[] = $this->getAttributeValueLabel($product, $titleField);
                    }
                }
            }

            $productName = implode(" ", $title);
            $productName = str_replace("   ", " ", $productName);
            $productName = str_replace("  ", " ", $productName);
            $productName = str_replace("  ", " ", $productName);
            $productName = trim($productName);

            if ($product) {
                $this->log("ID: " . $product->getId());
                $this->log("Old title=>" . $product->getName());
                $this->log("New title=>" . $productName);
                if ($productName != '' && $productName != $product->getName()) {
                    return $productName;
                }
            } else {
                return $productName;
            }
        }
        return '';
    }

    /**
     * Update product name for given products.
     *
     * @param type $products
     */
    public function updateProductName($products)
    {
        foreach ($products as $_product) {
            $id = $_product->getId();
            $product = $this->productFactory->create()->load($id);
            $productName = $this->getUpdatedProductName($product, [], true);

            if ($productName && trim($product->getName()) != $productName) {
                if (empty($product->getData('oodo_sync_update'))) {
                    $oodoSyncUpdateAttruburteValue = "name";
                } else {
                    $oodoSyncUpdateAttruburteTemp = explode(",", $product->getData('oodo_sync_update'));
                    if (!in_array("name", $oodoSyncUpdateAttruburteTemp)) {
                        $oodoSyncUpdateAttruburteTemp[] = "name";
                    }
                    $oodoSyncUpdateAttruburteValue = implode(",", $oodoSyncUpdateAttruburteTemp);
                }

                $this->action->updateAttributes([$id], ['name' => $productName, 'meta_title' => $productName, 'oodo_sync_update' => $oodoSyncUpdateAttruburteValue], 0);
                 $this->productPushHelper->updateCatalogServicePushData($_product->getId());
                 $this->catalogHelper->addLog('Import data in the Product name');
                 $data['name'] = $productName;
                $this->catalogHelper->updateSeldate($product->getSku(),$data);

            }
        }
    }

    /**
     * Get attribute option label
     *
     * @param type $product
     * @param type $attributeCode
     * @return string
     */
    public function getAttributeValueLabel($product, $attributeCode)
    {
        if ($attributeCode == '') {
            return;
        }

        $this->getAttributeFrontendInput($attributeCode);

        $frontendInput = $this->attributeFrontendInput[$attributeCode];
        if ($frontendInput == '') {
            return;
        }

        if ($frontendInput == 'select') {
            if ($attributeCode == 'brand_Id') {
                if ($product->getAttributeText($attributeCode)) {
                    return $this->getBrandName($product->getAttributeText($attributeCode));
                }
            } else {
                try {
                    return $product->getAttributeText($attributeCode);
                } catch(\Exception $exception) {

                }
            }
        } elseif ($frontendInput == 'boolean') {
            return ($product->getData($attributeCode)) ? "Yes" : "No";
        } else {
            return $product->getData($attributeCode);
        }
        return;
    }

    /**
     * Get brand name by brand id.
     *
     * @param type $brandId
     * @return type
     */
    public function getBrandName($brandId)
    {
        $brandOptions = $this->getBrandOptions();
        return (array_key_exists($brandId, $brandOptions)) ? $brandOptions[$brandId] : $brandId;
    }

    /**
     * Get all brands
     *
     * @return type
     */
    public function getBrandOptions()
    {
        if (empty($this->brandOptions)) {
            $this->brandOptions = $this->productFieldProcessor->getBrandNameOptions();
        }
        return $this->brandOptions;
    }

    /**
     * Get attribute frontend input value
     *
     * @param type $attributeCode
     */
    public function getAttributeFrontendInput($attributeCode)
    {
        if (!array_key_exists($attributeCode, $this->attributeFrontendInput)) {
            $tableName = $this->connection->getTableName('eav_attribute');
            $query = "SELECT frontend_input FROM {$tableName} WHERE attribute_code = '{$attributeCode}'";
            $this->attributeFrontendInput[$attributeCode] = $this->connection->fetchOne($query);
        }
    }

    /**
     * Get title name rule fields from category.
     *
     * @param type $categoryIds
     * @return type
     */
    public function getTitleRuleFields($categoryIds)
    {
        foreach ($categoryIds as $categoryId) {
            if (isset($this->titleRules[$categoryId])) {
                return $this->titleRules[$categoryId];
            }

            $category = $this->categoryFactory->create()->load($categoryId);
            if ($category->getCategoryType() == 'MERCHANDISING' && $category->getHierarchyType() == 'SUBCLASS') {
                $titleFields = $category->getTitleNameRule();
                if ($titleFields) {
                    $this->titleRules[$categoryId] = explode(",", $titleFields);
                    return $this->titleRules[$categoryId];
                }
            }
        }
        return [];
    }

    /**
     * Get title name rule fields from ibo category id.
     *
     * @param type $iboCategoryId
     * @return type
     */
    public function getTitleRuleFieldsByIboCategoryId($iboCategoryId)
    {
        if (isset($this->titleRules[$iboCategoryId])) {
            return $this->titleRules[$iboCategoryId];
        }

        $categoryCollection = $this->categoryFactory->create()->getCollection()
            ->addAttributeToSelect("title_name_rule")
            ->addAttributeToFilter("category_id", $iboCategoryId)
            ->addAttributeToFilter("category_type", 'MERCHANDISING')
            ->addAttributeToFilter("hierarchy_type", 'SUBCLASS');

        if ($categoryCollection->getSize() > 0) {
            $category = $categoryCollection->getFirstItem();
            $titleFields = $category->getTitleNameRule();
            if ($titleFields) {
                //$this->titleRules[$iboCategoryId] = explode(",", $titleFields);
                $this->titleRules[$iboCategoryId] = array_map('trim', explode(",", $titleFields));
                return $this->titleRules[$iboCategoryId];
            }
        }
        return [];
    }

    public function log($message)
    {
        if (!$this->isLogActive()) {
            return;
        }
        $logFileName = BP . '/var/log/product_title.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
    }
}
