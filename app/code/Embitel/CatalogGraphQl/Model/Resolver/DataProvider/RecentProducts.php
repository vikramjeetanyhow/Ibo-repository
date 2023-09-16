<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResultFactory;

/**
 * Best Seller Data provider
 */
class RecentProducts
{

    public function __construct(
        \Embitel\CatalogGraphQl\Block\Product\Viewed $reportProductViewed,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Customer\Model\Visitor $customerVisitor,
        SearchResultFactory $searchResultFactory
    ) {
        $this->reportProductViewed = $reportProductViewed;
        $this->_customerVisitor = $customerVisitor;
        $this->session = $session;
        $this->searchResultFactory = $searchResultFactory;
    }
    public function getRecentlyViewData($customerId,$args)
    {
        $totalCount = 0;
        $items = [];
        $productSku = [];
        $this->reportProductViewed->setCustomerId($customerId);
        if ($this->session->getVisitorData()) {
                $this->_customerVisitor->setData($this->session->getVisitorData());
            if ($this->_customerVisitor->getSessionId() != $this->session->getSessionId()) {
                $this->_customerVisitor->setSessionId($this->session->getSessionId());
            }
        }
        $collection = $this->reportProductViewed->getItemsCollection();
        $allowedChannels = !empty($args['allowed_channels']) ? $args['allowed_channels'] : null;
        $serviceCategory = !empty($args['service_category']) ? $args['service_category'] : null;
        $isPublished = !empty($args['is_published']) ? $args['is_published'] : null;
        $realPageSize = !empty($args['pageSize']) ? $args['pageSize'] : 5;
        $realCurrentPage = !empty($args['currentPage']) ? $args['currentPage'] : 1;
        if(!is_null($allowedChannels)){
            $collection->addAttributeToFilter('allowed_channels', ['in' => [$allowedChannels]]);
        }
        if(!is_null($serviceCategory)){
            $collection->addAttributeToFilter('service_category', ['in' => [$serviceCategory]]);
        }
        if(!is_null($isPublished)){ $collection->addAttributeToFilter('is_published', $isPublished); }
        $totalCount = (count($collection->getData()) > 0) ? count($collection->getData()) : 0;
        $totalPages = $realPageSize ? ((int)ceil($totalCount / $realPageSize)) : 0;
        $collection->getSelect()->limit($realPageSize);
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
}
