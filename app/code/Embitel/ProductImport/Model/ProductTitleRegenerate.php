<?php

namespace Embitel\ProductImport\Model;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Embitel\ProductImport\Model\Import\ProductImportHandler;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\Action;

/**
 * This classs to get products to regenerate product title.
 */
class ProductTitleRegenerate
{
    /**
     * Cron status path
     */
    const CRON_STATUS_PATH = 'ebo/ebo_product_title/cron_status';

    const FULL_PRODUCT_CRON_STATUS_PATH = 'ebo/ebo_product_title/full_cron_status';

    /**
     * Cron status path
     */
    const CRON_LOG_STATUS_PATH = 'ebo/ebo_product_title/log_active';

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
     * @var Action
     */
    protected $action;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $brandIdOptions = [];

    /**
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param AttributeRepository $attributeRepository
     * @param CategoryFactory $categoryFactory
     * @param ProductImportHandler $productImportHandler
     * @param Configurable $configurable
     * @param ProductFactory $productFactory
     * @param Action $action
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        AttributeSetRepositoryInterface $attributeSetRepository,
        AttributeRepository $attributeRepository,
        CategoryFactory $categoryFactory,
        ProductImportHandler $productImportHandler,
        Configurable $configurable,
        ProductFactory $productFactory,
        Action $action,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->attributeRepository = $attributeRepository;
        $this->categoryFactory = $categoryFactory;
        $this->productImportHandler = $productImportHandler;
        $this->configurable = $configurable;
        $this->productFactory = $productFactory;
        $this->action = $action;
        $this->scopeConfig = $scopeConfig;
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
        return $this->getConfig(self::CRON_STATUS_PATH);
    }

    /**
     * Check if cron is active or not.
     */
    public function isFullProductCronActive()
    {
        return $this->getConfig(self::FULL_PRODUCT_CRON_STATUS_PATH);
    }



    /**
     * Regenerate titles for products.
     *
     * @param type $products
     */
    public function updateProducts($products, $isCron = false)
    {
        foreach ($products as $_product) {
            $this->updateProduct($_product, $isCron);
        }
    }

    /**
     * Regenerate titles for product.
     *
     * @param type $_product
     */
    public function updateProduct($_product, $isCron = false)
    {
        $product = $this->productFactory->create()->load($_product->getId());
        if (!$product) {
            $this->log("product not exist with id:" . $_product->getId());
            return;
        }

        //If manual title is enabled for the product then return current product name.
        if ($product && $product->getIsProductHavingManualTitle()) {
            return $product->getName();
        }

        $id = $_product->getId();
        $titleData = [];
        foreach ($product->getData() as $attributeCode => $value) {
            if (in_array($attributeCode, $this->getAttributesToNotUpdate())) {
                continue;
            }

            try {
                $attribute = $this->attributeRepository->get($attributeCode);
            } catch (\Exception $ex) {
                continue;
            }

            if (!$attribute || $value == '') {
                continue;
            }

            //Get product attribute label
            if ($attribute->getFrontendInput() == 'select') {
                if ($attributeCode == 'brand_Id') {
                    $brandName = $product->getAttributeText('brand_Id');
                    if ($isCron) {
                        $titleData[$attributeCode] = $brandName;
                    } else {
                        if (empty($this->brandIdOptions)) {
                            $categoryTitle = 'Brand';
                            $collection = $this->categoryFactory->create()->getCollection()->addAttributeToFilter('name', $categoryTitle)->setPageSize(1);
                            if ($collection->getData()) {
                                $brandcategoryId = $collection->getFirstItem()->getId();
                                $categoryBrandObj = $this->categoryFactory->create()->load($brandcategoryId);
                                $childrenCategories = $categoryBrandObj->getChildrenCategories();

                                foreach ($childrenCategories as $cat) {
                                    $categoryObj = $this->categoryFactory->create()->load($cat->getId());
                                    $this->brandIdOptions[$cat->getName()] = $categoryObj->getCategoryId();
                                }
                            }
                        }
                        if ($brandName) {
                            $brandId = (array_key_exists($brandName, $this->brandIdOptions)) ? $this->brandIdOptions[$brandName] : '';
                            $titleData[$attributeCode] = $brandId;
                        }
                    }
                } else {
                    $text = $product->getAttributeText($attributeCode);
                    $titleData[$attributeCode] = (is_object($text)) ? (string) $text : $text;
                }
            } elseif ($attribute->getFrontendInput() == 'boolean') {
                $titleData[$attributeCode] = ($product->getData($attributeCode)) ? "Yes" : "No";
            } else {
                $titleData[$attributeCode] = $product->getData($attributeCode);
            }
        }

        /** Attribute set name - Start */
        $attributeSet = $this->attributeSetRepository->get($product->getAttributeSetId());
        $attributeSetCode = $attributeSet->getAttributeSetName();
        $titleData['attribute_set_code'] = $attributeSetCode;
        /** Attribute set name - End */

        try {
            $title = $this->productImportHandler->evalTitle($titleData);

            $title = str_replace("   ", " ", $title);
            $title = str_replace("  ", " ", $title);
            $title = str_replace("  ", " ", $title);
            $title = trim($title);

            //If current product is not same as regenerated title then only update.
            if ($title && trim($product->getName()) != $title) {
                if (empty($product->getData('oodo_sync_update'))) {
                    $oodoSyncUpdateAttruburteValue = "name";
                } else {
                    $oodoSyncUpdateAttruburteTemp = explode(",", $product->getData('oodo_sync_update'));
                    if (!in_array("name", $oodoSyncUpdateAttruburteTemp)) {
                        $oodoSyncUpdateAttruburteTemp[] = "name";
                    }
                    $oodoSyncUpdateAttruburteValue = implode(",", $oodoSyncUpdateAttruburteTemp);
                }
                $this->log("ID: " . $id);
                $this->log("Old title=>" . $product->getName());
                $this->log("New title=>" . $title);
                $this->action->updateAttributes([$id], ['name' => $title, 'meta_title' => $title, 'oodo_sync_update' => $oodoSyncUpdateAttruburteValue], 0);
            }

            if ($product->getTypeId() != 'simple') {
                return;
            }

            //Set title to configurable product
            $this->configProductTitleUpdate($id, $title);
            
        } catch (\Exception $ex) {
            $this->log("Error while updating product id: {$id}, Error:".$ex->getMessage());
        }
    }

    /**
     * Update simple product title to configurable product if it's first child.
     *
     * @param type $id
     * @param type $title
     */
    public function configProductTitleUpdate($id, $title)
    {
        try {
            $parentIds = $this->configurable->getParentIdsByChild($id);
            if (empty($parentIds)) {
                return;
            }
            $parentId = array_shift($parentIds);

            $configProduct = $this->productFactory->create()->load($parentId);
            $_children = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
            $childs = [];
            foreach ($_children as $child) {
                $childs[] = $child->getId();
            }

            if (min($childs) == $id) {
                $id = $parentId;
                if ($title && trim($configProduct->getName()) != $title) {
                    $this->action->updateAttributes([$parentId], ['name' => $title, 'meta_title' => $title], 0);
                    $this->log("Configurable ID:" . $parentId);
                    $this->log("Old title=>" . $configProduct->getName());
                    $this->log("New title=>" . $title);
                }
            }
        } catch (\Exception $ex) {
            $this->log("Error while updating config product id: {$id}, Error:".$ex->getMessage());
        }
    }

    /**
     * Do not use below attributes for creating title.
     *
     * @return type
     */
    private function getAttributesToNotUpdate()
    {
        return ['name', 'entity_id', 'store_id', 'attribute_set_id', 'created_in', 'updated_in', 'type_id', 'media_gallery'];
    }

    /**
     * Log the data
     *
     * @param type $message
     * @return type
     */
    public function log($message)
    {
        if (!$this->getConfig(self::CRON_LOG_STATUS_PATH)) {
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
