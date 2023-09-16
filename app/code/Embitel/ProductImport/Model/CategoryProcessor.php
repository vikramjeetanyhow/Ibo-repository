<?php

namespace Embitel\ProductImport\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class CategoryProcessor
{
    const DELIMITER_CATEGORY = '/';

    /**
     * Categories id to object cache.
     *
     * @var array
     */
    protected $categoriesCache = [];

    /**
     * @var CollectionFactory
     */
    private $categoryColFactory;

    /**
     * Category constructor.
     *
     * @param CollectionFactory $categoryColFactory
     */
    public function __construct(
        CollectionFactory $categoryColFactory
    ) {
        $this->categoryColFactory = $categoryColFactory;
        $this->initCategories();
    }

    public function getCategoryIdByPath($categoryPath)
    {
        /** @var string $index */
        $index = $this->standardizeString($categoryPath);
        if (isset($this->categories[$index])) {
            return $this->categories[$index]; // here is your category id
        }
    }

    /**
     * Initialize categories
     *
     * @return $this
     */
    public function initCategories()
    {
        if (empty($this->categories)) {
            $collection = $this->categoryColFactory->create();
            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect('url_key')
                ->addAttributeToSelect('url_path');
            $collection->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);

            /* @var $collection \Magento\Catalog\Model\ResourceModel\Category\Collection */
            foreach ($collection as $category) {
                $structure = explode(self::DELIMITER_CATEGORY, $category->getPath());
                $pathSize = count($structure);
                if ($pathSize > 1) {
                    $path = [];
                    for ($i = 1; $i < $pathSize; $i++) {
                        $name = $collection->getItemById((int)$structure[$i])->getName();
                        $path[] = $name;
                    }
                    /** @var string $index */
                    $index = $this->standardizeString(
                        implode('||', $path)
                    );
                    $this->categories[trim($index)] = $category->getId();
                }
            }
        }
        return $this->categories;
    }

    public function standardizeString($string)
    {
        return mb_strtolower($string);
    }

    public function quoteDelimiter($string)
    {
        return str_replace(self::DELIMITER_CATEGORY, '\\' . self::DELIMITER_CATEGORY, $string);
    }
}
