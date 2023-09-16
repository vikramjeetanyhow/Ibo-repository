<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Model\Publish;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Swatches\Helper\Media;
use Magento\Framework\Filesystem;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Embitel\ProductImport\Model\CategoryProcessor;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;

/**
 * This class is to prepare product fields.
 */
class ProductFieldProcessor extends AbstractModel
{

    /**
     * @var CategoryFactory
     */
    protected $category;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /** @var \Magento\Framework\Filesystem  */
    protected $filesystem;

    /** @var \Magento\Swatches\Helper\Media */
    protected $swatchHelper;

    /** @var \Magento\Catalog\Model\Product\Media\Config */
    protected $productMediaConfig;

    /** @var CategoryProcessor */
    protected $categoryProcessor;

    /** @var \Magento\Framework\Filesystem\Driver\File */
    protected $driverFile;

    protected $rootCategoryName = null;

    /**
     * @var SetFactory
     */
    protected $eavConfig;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var Config
     */
    protected $setFactory;

    /**
     * @var AttributeSetCollectionFactory
     */
    protected $attributeSetCollectionFactory;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollection;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var CsvProcessor
     */
    protected $csvProcessor;

    /**
     * @var Configurable
     */
    protected $configurableModel;

    protected $connection = null;

    protected $products = [];

    protected $requiredFields = [];

    protected $validAttributes = false;

    protected $brandNameOptions = [];

    protected $brandChildCategoryName = [];

    protected $attributeSetValidation = [];

    /**
     * @param Context $context
     * @param Registry $registry
     * @param SetFactory $setFactory
     * @param Config $eavConfig
     * @param Attribute $attribute
     * @param CategoryFactory $category
     * @param AttributeSetCollectionFactory $attributeSetCollectionFactory
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param Media $swatchHelper
     * @param Filesystem $filesystem
     * @param MediaConfig $productMediaConfig
     * @param CategoryProcessor $categoryProcessor
     * @param DriverFile $driverFile
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManagerInterface
     * @param ResourceConnection $resourceConnection
     * @param ProductCollectionFactory $productCollection
     * @param AttributeRepository $attributeRepository
     * @param CsvProcessor $csvProcessor
     * @param Configurable $configurableModel
     */
    public function __construct(
        Context $context,
        Registry $registry,
        SetFactory $setFactory,
        Config $eavConfig,
        Attribute $attribute,
        CategoryFactory $category,
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        WebsiteRepositoryInterface $websiteRepository,
        Media $swatchHelper,
        Filesystem $filesystem,
        MediaConfig $productMediaConfig,
        CategoryProcessor $categoryProcessor,
        DriverFile $driverFile,
        DirectoryList $directoryList,
        StoreManagerInterface $storeManagerInterface,
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollection,
        AttributeRepository $attributeRepository,
        CsvProcessor $csvProcessor,
        Configurable $configurableModel
    ) {
        parent::__construct($context, $registry);
        $this->setFactory = $setFactory;
        $this->eavConfig = $eavConfig;
        $this->attribute = $attribute;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->websiteRepository = $websiteRepository;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->category = $category;
        $this->filesystem = $filesystem;
        $this->swatchHelper = $swatchHelper;
        $this->productMediaConfig = $productMediaConfig;
        $this->categoryProcessor = $categoryProcessor;
        $this->driverFile = $driverFile;
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->productCollection = $productCollection;
        $this->attributeRepository = $attributeRepository;
        $this->csvProcessor = $csvProcessor;
        $this->configurableModel = $configurableModel;
    }

    /**
     * Filter correct and incorrect raws from CSV.
     *
     * @param type $csvData
     * @return type
     */
    public function getFilteredCsvData($csvData)
    {
        $header = [];
        foreach ($csvData as $rowIndex => $dataRow) {
            //skip headers
            if ($rowIndex == 0) {
                $header = $dataRow;
                continue;
            }
            $this->validateRowData($header, $dataRow);
        }

        return $this->products;
    }

    /**
     * Validate CSV data
     *
     * @param type $header
     * @param type $dataRow
     * @return boolean
     */
    private function validateRowData($header, $dataRow)
    {
        $isRawValid = true;
        $data = array_combine($header, $dataRow);

        //If SKU is not added, skip the raw.
        if (!isset($data['sku']) || !trim($data['sku'])) {
            $data['error'] = "Provide valid SKU.";
            $this->products['failure'][] = $data;
            return true;
        }
        
        if (!isset($data['publish'])) {
            $data['error'] = "Provide publish value either yes or no.";
            $this->products['failure'][] = $data;
            return true;
        }

        if ($isRawValid) {
            $this->products['success'][] = $data;
            return true;
        }
    }

    public function log($message)
    {
        $logFileName = BP . '/var/log/product_publish.log';
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
