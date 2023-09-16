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
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;

/**
 * @inheritdoc
 */
class VideoPositionResolver implements ResolverInterface
{
    protected $optionFactory;

    protected $_attributeOptionCollection;

    public function __construct(
      AttributeOptionInterfaceFactory $optionFactory,
      CollectionFactory $attributeOptionCollection
    ){
        $this->optionFactory = $optionFactory;
        $this->_attributeOptionCollection = $attributeOptionCollection;
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
        $videoOptionValue = null;
        if(!empty($product->getVideoPosition()))
        {
            $optionValue = $product->getVideoPosition();
            $optionFactory = $this->optionFactory->create();
            $optionFactory->load($optionValue); 
            $attributeId = $optionFactory->getAttributeId();
            $attributeOptionCollectionFac = $this->_attributeOptionCollection->create();
            $optionData = $attributeOptionCollectionFac
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setIdFilter($optionValue)
                            ->setStoreFilter()
                            ->load();

            if(!empty($optionData->getData()))
            {
                $optionData = $optionData->getData();
                if(!empty($optionData[0]['value'])) {
                    $videoOptionValue = $optionData[0]['value'];
                }
            }
        }
        return $videoOptionValue;
    }
}
