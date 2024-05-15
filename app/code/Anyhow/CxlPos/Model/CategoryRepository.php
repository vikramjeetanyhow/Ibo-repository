<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento CXL Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

namespace Anyhow\CxlPos\Model;

class CategoryRepository implements \Anyhow\CxlPos\Api\CategoryRepositoryInterface
{
    protected $categoryRepository;
    protected $helper;

    public function __construct(
    \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
    \Anyhow\CxlPos\Helper\Data $helper
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->helper = $helper;
    }

    /**
     * @inheritDoc
     */
    public function getCategorieslist() {
        $json = array(
            "error" => false
        );
        try {
            if ($this->helper->getConfig('anyhowcxlpos/general/enable')) {
                $rootCategoryId = $this->helper->getConfig('catalog/category/root_id');
                if (!empty($rootCategoryId)) {
                    $json['error'] = false;
                    $json['categories']= $this->getChildrenCategories($rootCategoryId);
                }
            }else {
                $json['error'] = true;
            }
        } catch (\Exception $e) {
            $json['error'] = true;
        }
        if($json['error']) {
            $json['message'] = "Something went wrong";
        }
 
        echo (json_encode($json));
        exit;
    }

    protected function getChildrenCategories($parentId) {
        $categories = [];
        $parentCategory = $this->categoryRepository->get($parentId);

        foreach ($parentCategory->getChildrenCategories() as $childCategory) {
            $categoryData = [
                'id' => $childCategory->getId(),
                'parent_id' => $parentId,
                'name' => $childCategory->getName(),
                'is_active' => $childCategory->getIsActive(),
                'position' => $childCategory->getPosition(),
                'level' => $childCategory->getLevel(),
                'product_count' => $childCategory->getProductCount(),
                'children' => $this->getChildrenCategories($childCategory->getId()),
            ];

            $categories[] = $categoryData;
        }

        return $categories;
    }
}

