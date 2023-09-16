<?php

namespace Ibo\Quotation\Model;

use Magento\Catalog\Model\CategoryFactory;
use Ibo\Quotation\Model\ProductFields;

class CategoryData
{
    public const NAVIGATION_CATEGORY_NAME = 'Navigation Category';
    public const MERCHANDISING_CATEGORY_NAME = 'Merchandising Category';

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductFields
     */
    protected $productFields;

    /**
     * @param CategoryFactory $category
     * @param ProductFields $productFields
     */
    public function __construct(
        CategoryFactory $category,
        ProductFields $productFields
    ) {
        $this->categoryFactory = $category;
        $this->productFields = $productFields;
    }

    /**
     * Get category data using ibo category id
     *
     * @param type $iboCategoryId
     * @return type
     */
    public function getCategoryFields($iboCategoryId)
    {
        $this->productFields->log("Get category all data - START");
        try {
            $categoryData = [];
            $this->productFields->log("Get merchandising category id");
            $merchandisingId = $this->getCategoryId(self::MERCHANDISING_CATEGORY_NAME, $iboCategoryId);
            if ($merchandisingId > 0) {
                $this->productFields->log("Get merchandising category details.");
                $category = $this->categoryFactory->create()->load($merchandisingId);
                if ($category->getAttributeSet() == 0 || $category->getAttributeSet() == '') {
                    return ['error' => 'Attribute set not mapped with merchandising subclass.'];
                }
                $classCategory = $category->getParentCategory();
                $departmentCategory = $classCategory->getParentCategory();
                $categoryData['category_id'][] = $merchandisingId;
                $categoryData['attribute_set_id'] = $category->getAttributeSet();
                $categoryData['subclass'] = $category->getName();
                $categoryData['class'] = $classCategory->getName();
                $categoryData['department'] = $departmentCategory->getName();
            } else {
                return ['error' => 'Merchandising subclass not found.'];
            }

            $this->productFields->log("Get navigation category id");
            $navigationId = $this->getCategoryId(self::NAVIGATION_CATEGORY_NAME, $iboCategoryId);
            if ($navigationId > 0) {
                $categoryData['category_id'][] = $navigationId;
            } else {
                return ['error' => 'Navigation subclass not found.'];
            }

            $this->productFields->log("Get category all data - END");
            return $categoryData;
        } catch (\Exception $ex) {
            return ['error' => 'Some error for ibo_category_id:' . $ex->getMessage()];
        }
    }

    /**
     * Get category id
     *
     * @param type $type
     * @param type $iboCategoryId
     * @return int
     */
    public function getCategoryId($type, $iboCategoryId)
    {
        try {
            $collection = $this->categoryFactory->create()->getCollection()
                    ->addAttributeToFilter('name', $type)->setPageSize(1);
            $path = '';
            if ($collection->getData()) {
                $path = $collection->getFirstItem()->getPath();
            } else {
                return 0;
            }

            $catColl = $this->categoryFactory->create()->getCollection()
                ->addAttributeToFilter('category_id', $iboCategoryId)
                ->addAttributeToFilter('path', ['like' => $path."/%"]);
            if ($type == self::MERCHANDISING_CATEGORY_NAME) {
                $catColl->addAttributeToFilter('level', 4);
            }

            if ($catColl->getSize() > 0) {
                return $catColl->getFirstItem()->getId();
            } else {
                return 0;
            }
        } catch (Exception $ex) {
            return 0;
        }
    }
}
