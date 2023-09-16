<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\Category;

use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use Magento\Catalog\Model\Config;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;

/**
 * Retrieves the sort fields data
 */
class SortFields implements ResolverInterface
{
    /**
     * @var Config
     */
    private $catalogConfig;

    /**
     * @var Sortby
     */
    private $sortbyAttributeSource;

    /**
     * @param Config $catalogConfig
     * @param Sortby $sortbyAttributeSource
     */
    public function __construct(
        Config $catalogConfig,
        Sortby $sortbyAttributeSource
    ) {
        $this->catalogConfig = $catalogConfig;
        $this->sortbyAttributeSource = $sortbyAttributeSource;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $sortFieldsOptions = $this->sortbyAttributeSource->getAllOptions();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

        array_walk(
            $sortFieldsOptions,
            function (&$option) {
                $option['label'] = (string)$option['label'];
            }
        );
        
        
        /*$newSortFieldsOptions = $sortFieldsOptions;
        foreach ($newSortFieldsOptions as $key => $value) {
            $tempArr = $newSortFieldsOptions[0];
            if ($value['value']=="new") {
                $newSortFieldsOptions[0] = $value;
                $newSortFieldsOptions[$key] = $tempArr;
            }

            if ($value['value']=="position") {
                $value['label'] = __("Popularity");
                $newSortFieldsOptions[$key] = $value;
            }

            if ($value['value']=="price") {
                $value['label'] = __("Price Low to High");
                $newSortFieldsOptions[$key] = $value;
            }
        }*/
        $newSortFieldsOptions = $priceLowToHighArr = $priceHighToLowArr = $popularityArr = [];
        $popularityArr['value'] = 'sort_order';
        $popularityArr['label'] = __('Popularity');
        $popularityArr['sort_direction'] = __('ASC');
        $newSortFieldsOptions[] = $popularityArr;
        $priceLowToHighArr['value'] = 'price';
        $priceLowToHighArr['label'] = __('Price Low to High');
        $priceLowToHighArr['sort_direction'] = __('ASC');
        $newSortFieldsOptions[] = $priceLowToHighArr;
        $priceHighToLowArr['value'] = 'price';
        $priceHighToLowArr['label'] = __('Price High to Low');
        $priceHighToLowArr['sort_direction'] = __('DESC');
        $newSortFieldsOptions[] = $priceHighToLowArr;
        
        $data = [
            'default' => $this->catalogConfig->getProductListDefaultSortBy($storeId),
            'options' => $newSortFieldsOptions,
        ];

        return $data;
    }
}
