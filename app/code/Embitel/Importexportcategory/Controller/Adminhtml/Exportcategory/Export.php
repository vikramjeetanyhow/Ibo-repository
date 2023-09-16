<?php

namespace Embitel\Importexportcategory\Controller\Adminhtml\Exportcategory;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Filesystem;
use Magento\Backend\App\Action\Context;

class Export extends \Magento\Backend\App\Action
{
    /**
     * @var ForwardFactory
     */
    protected $_resultForwardFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * @var Collection
     */
    protected $_productcollection;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var Filesystem
     */
    protected $directory;

    /**
     * @param ForwardFactory $resultForwardFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param CategoryFactory $categoryFactory
     * @param Collection $prodcollection
     * @param RawFactory $resultRawFactory
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     * @param Context $context
     */
    public function __construct(
        ForwardFactory $resultForwardFactory,
        StoreManagerInterface $storeManagerInterface,
        CategoryFactory $categoryFactory,
        Collection $prodcollection,
        RawFactory $resultRawFactory,
        FileFactory $fileFactory,
        Filesystem $filesystem,
        Context $context
    ) {
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_storeManager = $storeManagerInterface;
        $this->_categoryFactory = $categoryFactory;
        $this->_productcollection = $prodcollection;
        $this->resultRawFactory = $resultRawFactory;
        $this->fileFactory  = $fileFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
    }

    public function execute()
    {
        $storeId = $this->getRequest()->getPost('store_id');

        $name = date('m_d_Y_H_i_s');
        $filepath = 'export/custom' . $name . '.csv';
        $this->directory->create('export');

        /* Open file */
        $stream = $this->directory->openFile($filepath, 'w+');
        $stream->lock();
        $columns = $this->getColumnHeader();
        foreach ($columns as $column) {
            $header[] = $column;
        }
        /* Write Header */
        $stream->writeCsv($header);

        $collection = $this->_categoryFactory->create()->getCollection()->addAttributeToSort('entity_id', 'asc');
        foreach ($collection as $key => $cat) {
            $categoryitem = $this->_categoryFactory->create();
            if ($cat->getId() >= 2) {
                $categoryitem->setStoreId($storeId);
                $categoryitem->load($cat->getId());
                if ($categoryitem->getId()) {
                    $itemData = [];
                    $itemData[] = $categoryitem->getId();
                    $itemData[] = $categoryitem->getParentId();
                    $itemData[] = $categoryitem->getName();
                    $itemData[] = $categoryitem->getPath();
                    $itemData[] = $categoryitem->getImage();
                    $itemData[] = $categoryitem->getIsActive();
                    $itemData[] = $categoryitem->getIsAnchor();
                    $itemData[] = $categoryitem->getIncludeInMenu();
                    $itemData[] = $categoryitem->getMetaTitle();
                    $itemData[] = $categoryitem->getMetaKeywords();
                    $itemData[] = $categoryitem->getMetaDescription();
                    $itemData[] = $categoryitem->getDisplayMode();
                    $itemData[] = $categoryitem->getCustomUseParentSettings();
                    $itemData[] = $categoryitem->getCustomApplyToProducts();
                    $itemData[] = $categoryitem->getCustomDesign();
                    $itemData[] = $categoryitem->getCustomDesignFrom();
                    $itemData[] = $categoryitem->getCustomDesignTo();
                    $itemData[] = $categoryitem->getDefaultSortBy();
                    $itemData[] = $categoryitem->getPageLayout();
                    $itemData[] = $categoryitem->getDescription();
                    $itemData[] = $this->getProductIds($categoryitem);

                    /** Additional attributes */
                    $itemData[] = $categoryitem->getCategoryId();
                    $itemData[] = $categoryitem->getParentCategoryId();
                    $itemData[] = $categoryitem->getTitleNameRule();
                    $itemData[] = $categoryitem->getVariantAttribute();
                    $itemData[] = $categoryitem->getHierarchyType();
                    $itemData[] = $categoryitem->getCategoryType();

                    $stream->writeCsv($itemData);
                }
            }
        }
        return $this->downloadCsv($filepath);
    }

    /**
     * Download CSV file.
     *
     * @param type $filepath
     * @return type
     */
    private function downloadCsv($filepath)
    {
        $content = [];
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = '1';

        $csvfilename = 'Categories.csv';
        return $this->fileFactory->create($csvfilename, $content, DirectoryList::VAR_DIR);
    }

    /**
     * Get list of columns of CSV file.
     *
     * @return type
     */
    protected function getColumnHeader()
    {
        return [
            "id", "parent_id", "name", "path", "image", "is_active", "is_anchor",
            "include_in_menu", "meta_title", "meta_keywords", "meta_description", "display_mode",
            "custom_use_parent_settings", "custom_apply_to_products", "custom_design",
            "custom_design_from", "custom_design_to", "default_sort_by",
            "page_layout", "description", "products", "category_id", "parent_category_id",
            "title_name_rule", "variant_attribute", "hierarchy_type", "category_type"
        ];
    }

    /**
     * Get category product ids.
     *
     * @param type $categoryitem
     * @return type
     */
    protected function getProductIds($categoryitem)
    {
        $products = '';
        $productids = $this->_productcollection->addCategoryFilter($categoryitem)->getAllIds();
        if (isset($productids) && !empty($productids)) {
            $products = implode('|', $productids);
        }
        return $products;
    }
}
