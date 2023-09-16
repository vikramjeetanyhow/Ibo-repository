<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\HomepageGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\CategoryFactory;

/**
 * @inheritdoc
 */
class CategoryIboIdResolver implements ResolverInterface
{

    /**
    * @var \Magento\Catalog\Model\CategoryFactory
    */
    protected $_categoryFactory;

    /**
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(CategoryFactory $categoryFactory)
    {
        $this->_categoryFactory = $categoryFactory;
    }

    /**
     * @inheritdoc
     */
   public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['banner_cat_ids'])) {
            throw new LocalizedException(__('"Category Id" value should be specified'));
        }
        $categoryIds = [];
        if(isset($value['banner_cat_ids']) && ($value['banner_cat_ids'] > 0)) {
            $bannerCateIds = explode(',',$value['banner_cat_ids']);
            foreach ($bannerCateIds as $key => $bannerCateId) {          
                $mageCategoryId = $bannerCateId; //$value['banner_cat_ids'];
                $categoryData = $this->_categoryFactory->create()->load($mageCategoryId);
                if(!empty($categoryData->getData('category_id') )) {
                    $categoryIds[] = $categoryData->getData('category_id'); 
                }
                else{
                 $categoryIds[] = null;   
                }
            }
        }
        $catIds = implode(',', $categoryIds);
        return $catIds;     
    }
}


