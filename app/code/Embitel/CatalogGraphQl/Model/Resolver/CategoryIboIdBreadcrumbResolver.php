<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Model\CategoryFactory;

/**
 * @inheritdoc
 */
class CategoryIboIdBreadcrumbResolver implements ResolverInterface
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
        if (!isset($value['category_id'])) {
            throw new LocalizedException(__('Magento category id not specified.'));
        }
        $categoryId = '';
        if(isset($value['category_id'])) {
            $categoryData = $this->_categoryFactory->create()->load($value['category_id']);
            $categoryId = $categoryData->getData('category_id'); 
        }

        return $categoryId;
     
    }
}
