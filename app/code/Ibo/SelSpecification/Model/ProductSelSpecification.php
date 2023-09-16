<?php

namespace Ibo\SelSpecification\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Embitel\ProductImport\Model\Import\ProductImportHandler;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State as AppState;

/**
 * This classs to update products SEL specifications.
 */
class ProductSelSpecification
{
    /**
     * Cron status path
     */
    const XML_PATH_CRON_STATUS = 'sel_specification/setting/cron_status';

    /**
     * Default SEL Specification rule
     */
    const XML_PATH_DEFAULT_RULE = 'sel_specification/setting/default_sel_rule';

    /**
     * Log Status
     */
    const XML_PATH_LOG_STATUS = 'sel_specification/setting/log_active';

    /**
     * Product updated hours
     */
    const XML_PATH_PRODUCTS_UPDATED_HOURS = 'sel_specification/setting/products_updated_hours';

    /**
     * Product updated hours
     */
    const XML_PATH_PRODUCT_BATCH_SIZE = 'sel_specification/setting/batch_size';

    /**
     * @var AttributeSetRepositoryInterface
     */
    protected $attributeSetRepository;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductImportHandler
     */
    protected $productImportHandler;

    /**
     * @var Configurable
     */
    protected $configurable;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductResourceModel
     */
    protected $productResourceModel;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var AppState
     */
    protected $appState;

    protected $brandIdOptions = [];

    protected $iboCategoryIds = [];

    protected $defaultSelRule = [];

    protected $attributeFrontInput = [];

    /**
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param AttributeRepository $attributeRepository
     * @param CategoryFactory $categoryFactory
     * @param ProductImportHandler $productImportHandler
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param ProductResourceModel $productResourceModel
     * @param ScopeConfigInterface $scopeConfig
     * @param AppState $appState
     */
    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        AttributeRepository $attributeRepository,
        CategoryFactory $categoryFactory,
        ProductImportHandler $productImportHandler,
        Configurable $configurable,
        ProductFactory $productFactory,
        ProductResourceModel $productResourceModel,
        ScopeConfigInterface $scopeConfig,
        AppState $appState
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->attributeRepository = $attributeRepository;
        $this->categoryFactory = $categoryFactory;
        $this->productImportHandler = $productImportHandler;
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->productResourceModel = $productResourceModel;
        $this->scopeConfig = $scopeConfig;
        $this->appState = $appState;
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
     * Check if cron is active or not.
     */
    public function isCronActive()
    {
        return $this->getConfig(self::XML_PATH_CRON_STATUS);
    }

    /**
     * Get default SEL Specification Rule.
     */
    public function getDefaultSelSpecificationRule()
    {
        if (empty($this->defaultSelRule)) {
            $this->defaultSelRule = trim($this->getConfig(self::XML_PATH_DEFAULT_RULE));
        }
        return $this->defaultSelRule;
    }

    /**
     * Get configuration to get hours count of product update.
     */
    public function getProductUpdatedInLastHours()
    {
        return $this->getConfig(self::XML_PATH_PRODUCTS_UPDATED_HOURS);
    }

    /**
     * Get configuration to get hours count of product update.
     */
    public function getProductBatchSize()
    {
        return $this->getConfig(self::XML_PATH_PRODUCT_BATCH_SIZE);
    }

    /**
     * Update product sel specifications.
     *
     * @param type $categoryId
     */
    public function setSelSpecification($categoryId)
    {
        //Check for cron status.
        if ($this->appState->getAreaCode() == 'crontab' && !$this->isCronActive()) {
            $this->log("Cron is disabled.");
            return;
        }

        //Check for category id.
        if (!$this->isCategoryIdValid($categoryId)) {
            if ($this->appState->getAreaCode() == 'crontab') {
                $this->log("Please probide valid IBO Category ID.");
            }
            return "Please probide valid IBO Category ID: {$categoryId}.";
        }

        //Check for product of given category id.
        $products = $this->getCategoryProducts($categoryId);
        if ($products->getSize() == 0) {
            if ($this->appState->getAreaCode() == 'crontab') {
                $this->log("There is no product found with given IBO Category ID: {$categoryId}.");
            }
            return "There is no product found with given IBO Category ID.";
        }

        $this->updateProducts($products);
        return "SEL Specification updated successfully.";
    }

    /**
     * Check IBO Category ID is valid for merchandising subclass.
     *
     * @param type $categoryId
     * @return bool
     */
    private function isCategoryIdValid($categoryId)
    {
        if (isset($this->iboCategoryIds[$categoryId])) {
            return true;
        }

        $catColl = $this->categoryFactory->create()->getCollection()
            ->addAttributeToSelect('sel_specification_rule')
            ->addAttributeToFilter('category_id', $categoryId)
            ->addAttributeToFilter('hierarchy_type', 'SUBCLASS')
            ->addAttributeToFilter('category_type', 'MERCHANDISING');

        if ($catColl->getSize() > 0) {
            $categorySelRule = (trim($catColl->getFirstItem()->getSelSpecificationRule()))
                ? trim($catColl->getFirstItem()->getSelSpecificationRule())
                : $this->getDefaultSelSpecificationRule();

            $this->log("SEL Specification Rule for IBO category ID '{$categoryId}' is:");
            if (!$categorySelRule) {
                $this->log("does not exist.");
                return false;
            }

            $this->log($categorySelRule);
            $this->iboCategoryIds[$categoryId] = explode("|", $categorySelRule);

            return true;
        }

        $this->log("IBO category ID {$categoryId} not exist");
        return false;
    }

    /**
     * Get products filter by IBO Category ID
     *
     * @param type $categoryId
     */
    private function getCategoryProducts($categoryId)
    {
        $products = $this->productFactory->create()->getCollection()
            ->addFieldToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('ibo_category_id', $categoryId);

        $batchSize = (int) $this->getProductBatchSize();
        if ($batchSize > 0) {
            $products->setPageSize($batchSize);
        }

        return $products;
    }

    /**
     * Regenerate titles for products.
     *
     * @param type $products
     */
    public function updateProducts($products)
    {
        foreach ($products as $_product) {
            $this->updateProduct($_product);
        }
    }

    /**
     * Regenerate SEL product specification.
     *
     * @param type $product
     */
    public function updateProduct($_product, $argumentType = 'object')
    {
        $product = '';
        if ($argumentType == 'object') {//product object passed
            $product = $_product;
        } else if ($argumentType == 'id') {//only product id passed
            $product = $this->productFactory->create()->load($_product);
            if (!$product) {
                $this->log("product not exist for sku:" . $product->getSku());
                return;
            }
        } else if ($argumentType == 'sku') {//only product sku passed
            $product = $this->productFactory->create()->loadByAttribute('sku', $_product);
            if (!$product) {
                $this->log("product not exist for sku:" . $_product);
                return;
            }
        }

        //IBO Category ID is not in the product then skip the product
        if ($this->appState->getAreaCode() == 'crontab') {
            if (!$product->getIboCategoryId()) {
                $this->log("product ibo category id not exist for sku:" . $product->getSku());
                return;
            }

            if (!$this->isCategoryIdValid($product->getIboCategoryId())) {
                $this->log("ibo category id not valid:" . $product->getIboCategoryId());
                return;
            }
        }

        try {
            $selLabel = $this->getSelSpecificationData($product);
            $selLabel = str_replace("   ", " ", $selLabel);
            $selLabel = str_replace("  ", " ", $selLabel);
            $selLabel = str_replace("  ", " ", $selLabel);
            $selLabel = trim($selLabel);

            //If current product is not same as regenerated title then only update.
            if ($selLabel && trim($product->getSelSpecificationOutput()) != $selLabel) {
                $this->log("SKU: " . $product->getSku());
                $this->log("Old SEL Output=>" . $product->getSelSpecificationOutput());
                $this->log("New SEL Output=>" . $selLabel);
                $this->updateSelToProduct($product, $selLabel);
            }
        } catch (\Exception $ex) {
            $this->log("Error while updating product: {$product->getSku()}, Error:".$ex->getMessage());
        }
        return;
    }

    /**
     * Get product SEL Specification Data
     *
     * @param type $product
     * @return type
     */
    public function getSelSpecificationData($product)
    {
        $selSpecificationRule = $this->iboCategoryIds[$product->getIboCategoryId()];
        
        $selLabelFull = [];
        foreach ($selSpecificationRule as $selFieldOld) {
            $selFields = explode(",", trim($selFieldOld));
            $selLabel = [];
            foreach ($selFields as $selFieldOld) {
            $selField = trim($selFieldOld);
            if (strpos($selField, '[') !== false || strpos($selField, ']') !== false) {
                $selField = str_replace("[", "", $selField);
                $selField = str_replace("]", "", $selField);
                $selLabel[] = trim($selField);
                continue;
            }

            $attributeFrontInput = $this->getAttributeFrontInput($selField);
            if ($attributeFrontInput == '') {
                $selLabel[] = 'NOT_FOUND';
                continue;
            }

            $value = $product->getData($selField);
            if ($value == '' && $attributeFrontInput != 'boolean') {
                $selLabel[] = 'NOT_FOUND';
                continue;
            }

            //Get product attribute label
            if ($attributeFrontInput == 'select') {
                if ($selField == 'brand_Id') {
                    $selLabel[] = $product->getResource()->getAttribute('brand_Id')->setStoreId(1)->getFrontend()->getValue($product);
                } else {
                    $text = $product->getAttributeText($selField);
                    $selSelectValue = (is_object($text)) ? (string) $text : $text;
                    if (strtolower($selSelectValue) != 'na' && $selSelectValue != '') {
                        $selLabel[] = $selSelectValue;
                    }
                }
            } elseif ($attributeFrontInput == 'boolean') {
                $selLabel[] = ($product->getData($selField)) ? "Yes" : "No";
            } else {
                $selTextValue = $product->getData($selField);
                if (strtolower($selTextValue) != 'na' && $selTextValue != '') {
                    $selLabel[] = $selTextValue;
                }
            }
        }

           $selLabelFull[] = implode(" ", $selLabel);
        }

        return implode("|", $selLabelFull);
    }
    /**
     * Get attribute frontend input type.
     *
     * @param type $attributeCode
     * @return type
     */
    public function getAttributeFrontInput($attributeCode)
    {
        if (!array_key_exists($attributeCode, $this->attributeFrontInput)) {
            try {
                $attribute = $this->attributeRepository->get($attributeCode);
                $this->attributeFrontInput[$attributeCode] = $attribute->getFrontendInput();
            } catch (\Exception $ex) {
                $this->attributeFrontInput[$attributeCode] = '';
            }
        }
        return $this->attributeFrontInput[$attributeCode];
    }
    /**
     * Update SEL Specification data to product without model save.
     *
     * @param type $product
     * @param type $selLabel
     */
    private function updateSelToProduct($product, $selLabel)
    {
        try {
            $product->setData('sel_specification_output', $selLabel);
            $product->setData('store_id', 0);
            $this->productResourceModel->saveAttribute($product, 'sel_specification_output');
        } catch (\Exception $ex) {
            $this->log("There is some error to update product: " . $product->getSku());
        }
    }

    /**
     * Log the data
     *
     * @param type $message
     * @return type
     */
    public function log($message)
    {
        if (!$this->getConfig(self::XML_PATH_LOG_STATUS)) {
            return;
        }

        $logFileName = BP . '/var/log/product_sel_specification.log';
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
