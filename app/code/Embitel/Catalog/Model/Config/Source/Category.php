<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Catalog\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Handles the category tree.
 */
class Category implements ArrayInterface
{
    protected $_categoryCollectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $StoreManagerInterface
    )
    {
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManagerInterface = $StoreManagerInterface;
        
    }

    /*
     * Return categories helper
     */

    public function getStoreCategories($sorted = false, $asCollection = false, $toLoad = false)
    {
        $rootCategoryId = $this->storeManagerInterface->getStore()->getRootCategoryId();
        $collection = $this->_categoryCollectionFactory->create(); 
        
        $collection->addAttributeToSelect('*')
                   ->addAttributeToFilter('parent_id', $rootCategoryId)
                   ->addAttributeToFilter('level', 2);

        return $collection;        
    }

    /*  
     * Option getter
     * @return array
     */
    public function toOptionArray()
    {


        $arr = $this->toArray();
        $ret = [];

        foreach ($arr as $key => $value)
        {

            $ret[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        return $ret;
    }

    /*
     * Get options in "key-value" format
     * @return array
     */
    public function toArray()
    {

        $categories = $this->getStoreCategories(true,false,true);

        $catagoryList = array();
        foreach ($categories as $category){

            $catagoryList[$category->getEntityId()] = __($category->getName());
        }

        return $catagoryList;
    }

}