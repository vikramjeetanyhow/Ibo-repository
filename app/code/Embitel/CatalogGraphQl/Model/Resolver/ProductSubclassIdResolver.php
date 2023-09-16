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
class ProductSubclassIdResolver implements ResolverInterface
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
        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /* @var $product ProductInterface */
        $product = $value['model'];
        $subclassId = null;
        if(!empty($product->getSubclass())){
            $collection = $this->_categoryFactory->create()->getCollection()              
              ->addFieldToFilter('name',$product->getSubclass());
            if ($collection->getSize()) {               
                $categoryId = $collection->getFirstItem()->getId();
                $catcollection = $this->_categoryFactory->create()->load($categoryId);
                $subclassId = $catcollection->getCategoryId();
            }            
        }
        return $subclassId;
    }
}
