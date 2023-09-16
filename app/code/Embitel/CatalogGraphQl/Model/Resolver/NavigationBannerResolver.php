<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
/**
 * @inheritdoc
 */
class NavigationBannerResolver implements ResolverInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionFactory
     */
     protected $categoryCollectionFactory;

     /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $catgory = $value['model'];
        $categroyNavigationBannerData = [];
        $primaryBannerImage = $catgory->getPrimaryBannerImage();
        $primaryBannerTitle = $catgory->getPrimaryBannerTitle();
        $primaryBannerLinkCategoryId = $catgory->getPrimaryBannerLinkCategoryId();
        $secondaryBannerImage = $catgory->getSecondaryBannerImage();
        $secondaryBannerTitle = $catgory->getSecondaryBannerTitle();
        $secondaryBannerLinkCategoryId = $catgory->getSecondaryBannerLinkCategoryId();

        $getPrimaryBannerIBOCategoryId = null;
        if($primaryBannerLinkCategoryId !=null) {
            $getPrimaryBannerIBOCategoryId = $this->getSelectedServiceCategory($primaryBannerLinkCategoryId);
        }

        $getSecondaryBannerIBOCategoryId = null;
        if($secondaryBannerLinkCategoryId !=null) {
            $getSecondaryBannerIBOCategoryId = $this->getSelectedServiceCategory($secondaryBannerLinkCategoryId);
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $baseUrl = substr($baseUrl, 0, -1);

        if($primaryBannerImage !='') {
            $primaryBannerImage = $baseUrl.$primaryBannerImage;
        }else { $primaryBannerImage = null;}

        if($secondaryBannerImage !='') {
            $secondaryBannerImage = $baseUrl.$secondaryBannerImage;
        }else { $secondaryBannerImage = null;}


         $categroyNavigationBannerData[] = [
                    'primary_banner_image' => $primaryBannerImage,
                    'primary_banner_title' => $primaryBannerTitle,
                    'primary_banner_link_category_id' => $getPrimaryBannerIBOCategoryId,
                    'secondary_banner_image' => $secondaryBannerImage,
                    'secondary_banner_title' => $secondaryBannerTitle,
                    'secondary_banner_link_category_id' => $getSecondaryBannerIBOCategoryId
                ]; 
        return $categroyNavigationBannerData;
    }

     public function getSelectedServiceCategory($categoryId) {
        $iboCategoryIdValue = '';
        $collection = $this->categoryCollectionFactory
                        ->create()
                        ->addAttributeToSelect('category_id')
                        ->addAttributeToFilter('entity_id',['eq'=>$categoryId])
                        ->setPageSize(1);

        $catObj = $collection->getFirstItem();
        $catData = $catObj->getData();
        if(isset($catData['category_id'])) {
            $iboCategoryIdValue = $catData['category_id'];
        }
        return $iboCategoryIdValue;
    }
}