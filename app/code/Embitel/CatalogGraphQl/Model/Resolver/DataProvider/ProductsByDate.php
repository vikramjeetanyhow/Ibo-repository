<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;

/**
 * ProductsByDate Data provider
 */
class ProductsByDate
{

    public function __construct(
        DateTime $dateTime,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        CollectionFactory $categoryCollectionFactory,
        SearchResultFactory $searchResultFactory
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->_storeManager = $storeManager;
        $this->productStatus = $productStatus;
        $this->dateTime = $dateTime;
        $this->searchResultFactory = $searchResultFactory;
    }
    public function getProductsData(array $args)
    {
        $gmtDate = $this->dateTime->gmtDate();
        $updated_from = !empty($args['updated_from']) ? $args['updated_from'] : null;
        $updated_to = !empty($args['updated_to']) ? $args['updated_to'] : null;
        $sel_updated_from = !empty($args['sel_updated_from']) ? $args['sel_updated_from'] : null;
        $sel_updated_to = !empty($args['sel_updated_to']) ? $args['sel_updated_to'] : null;
        $iboCategoryId = !empty($args['ibo_category_id']) ? $args['ibo_category_id'] : null;
        $allowedChannels = !empty($args['allowed_channels']) ? $args['allowed_channels'] : null;
        $serviceCategory = !empty($args['service_category']) ? $args['service_category'] : null;
        $isPublished = !empty($args['is_published']) ? $args['is_published'] : null;
        $argSku = !empty($args['sku']) ? $args['sku'] : null;
        $realPageSize = !empty($args['pageSize']) ? $args['pageSize'] : 5;
        $realCurrentPage = !empty($args['currentPage']) ? $args['currentPage'] : 1;
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect("*")
                   ->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
                   ->addAttributeToFilter('type_id','simple');
                   

        if(!is_null($updated_from) && !is_null( $updated_to) && is_null($argSku)){
            $collection->addAttributeToFilter('updated_at',['from'=>$updated_from,'to'=>$updated_to]);
        }
        if(!is_null($iboCategoryId) && is_null($argSku)){
            $categoryId = $this->getCategoryId($iboCategoryId);
            if(!empty($categoryId)){
                $collection->addCategoriesFilter(['in' => $categoryId]);
            }else{
                throw new GraphQlInputException(__('ibo_category_id does not exist.'));
            }
        }
        if(!is_null($allowedChannels)){
            $collection->addAttributeToFilter('allowed_channels', ['in' => [$allowedChannels]]);
        }
        if(!is_null($serviceCategory)){
            $collection->addAttributeToFilter('service_category', ['in' => [$serviceCategory]]);
        }
        if(!is_null($argSku)){ $collection->addAttributeToFilter('sku', ['in' => [$argSku]]); }
        if(!is_null($isPublished)){ $collection->addAttributeToFilter('is_published', $isPublished); }

        if(!is_null($sel_updated_from) && !is_null($sel_updated_to) && is_null($argSku)){
            $collection->getSelect()->Where(
                $collection->getSelect()
                    ->getConnection()
                    ->quoteIdentifier(
                        'e.sel_updated_at'
                    ) . ' >= ?',$sel_updated_from. ' <= ?',$sel_updated_to
                    
            );
        } 

        $totalCount = (count($collection->getData()) > 0) ? count($collection->getData()) : 0;
        $totalPages = $realPageSize ? ((int)ceil($totalCount / $realPageSize)) : 0;
        // $offset = ($realCurrentPage != 1) ? ((($realCurrentPage-1) * $realPageSize)) : ($realCurrentPage-1);
        // $collection->getSelect()->limit($realPageSize, $offset);
        $collection->setPageSize($realPageSize);
        $collection->setCurPage($realCurrentPage);
        $productArray = [];
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($collection as $product) {
            $productArray[$product->getId()] = $product->getData();
            $productArray[$product->getId()]['model'] = $product;
        }
        return $this->searchResultFactory->create(
            [
                'totalCount' => $totalCount,
                'productsSearchResult' => $productArray,
                'searchAggregation' => null,
                'pageSize' => $realPageSize,
                'currentPage' => $realCurrentPage,
                'totalPages' => $totalPages,
            ]
        );
    }

    public function getCategoryId($iboCategoryId)
    {   
        $categoryId = '';
        $rootId = $this->_storeManager->getStore()->getRootCategoryId();
        $collection = $this->categoryCollectionFactory
                    ->create()
                    ->addAttributeToFilter('category_id',$iboCategoryId)
                    ->addFieldToFilter('path', array('like'=> "1/$rootId/%"));
       
        if ($collection->getSize()) {
            $categoryId = $collection->getFirstItem()->getId();
        }
        return $categoryId;
    }

}
