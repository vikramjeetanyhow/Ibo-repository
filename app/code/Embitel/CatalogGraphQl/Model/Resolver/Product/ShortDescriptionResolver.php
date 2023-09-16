<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\Product;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\CategoryFactory;


/**
 * @inheritdoc
 *
 * Format a product's media gallery information to conform to GraphQL schema representation
 */
class ShortDescriptionResolver implements ResolverInterface
{

    /**
     * @var OutputHelper
     */
    private $outputHelper;

     /**
    * @var \Magento\Catalog\Model\CategoryFactory
    */
    protected $_categoryFactory;

    /**
     * @param OutputHelper $outputHelper
     */
    public function __construct(
        OutputHelper $outputHelper,
        CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
    ) {
        $this->outputHelper = $outputHelper;
        $this->_categoryFactory = $categoryFactory;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @inheritdoc
     *
     * Format product's media gallery entry data to conform to GraphQL schema
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var ProductInterface $product */
        $product = $value['model'];

        $renderedValue = '';

        //$brandId = $product->getAttributeText('brand_Id');

        $attribute = $product->getResource()->getAttribute('brand_Id');
        $brandId = $attribute
              ->setStoreId(0) // Admin store ID
              ->getSource()->getOptionText($product->getData('brand_Id'));
        if($brandId) {

            $collection1 = $this->_categoryCollectionFactory->create()->addAttributeToSelect('*')
            ->addAttributeToFilter('name',['eq'=> 'Brand'])
            ->addAttributeToFilter('level',['eq'=> 1])
            ->getFirstItem()
            ->toArray();

            $brandCatId = $collection1['entity_id'];

            $collection = $this->_categoryCollectionFactory->create();
            $collection->addAttributeToSelect('*')
                ->addAttributeToFilter('category_id',['eq'=> $brandId])
                ->addAttributeToFilter('parent_id',['eq'=> $brandCatId])
                ->getFirstItem()
                ->toArray(); 

            if(count($collection) > 0 ) {
                foreach ($collection as $category) {
                    $shortDescription = $category->getDescription();
                    if($shortDescription != '') {
                        $renderedValue = $this->outputHelper->productAttribute($product, $shortDescription, 'short_description');
                        $renderedValue = strip_tags($renderedValue);
                    }
                }
            }
        }
    
        return ['html' => $renderedValue ?? ''];
    }
}
