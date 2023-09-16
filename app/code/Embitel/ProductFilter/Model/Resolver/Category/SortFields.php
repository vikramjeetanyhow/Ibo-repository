<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductFilter\Model\Resolver\Category;

use Magento\Catalog\Model\Category\Attribute\Source\Sortby;
use Magento\Catalog\Model\Config;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\Registry;

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
     * @var Registry
     */

    protected $_registry;
    /**
     * @param Config $catalogConfig
     * @param Sortby $sortbyAttributeSource
     */
    public function __construct(
        Config $catalogConfig,
        Sortby $sortbyAttributeSource,
        Registry $registry
    ) {
        $this->catalogConfig = $catalogConfig;
        $this->sortbyAttributeSource = $sortbyAttributeSource;
        $this->_registry = $registry;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if ($this->_registry->registry('hide_product')) {
            return [];
        }
        $sortFieldsOptions = $this->sortbyAttributeSource->getAllOptions();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        
        array_walk(
            $sortFieldsOptions,
            function (&$option) {
                $currentSort = $this->_registry->registry('current_sort');
                $params = [];
                $sortDirection = [];
                if (!is_null($currentSort)) {
                    $params = array_keys($currentSort);
                    $sortDirection = array_values($currentSort);
                }

                $option['is_selected'] = false;
                if (isset($sortDirection[0])) {
                    if ($option['value'] == 'sort_order') {
                        if ((string)$option['label'] == "Popularity") {
                            if (in_array('ASC', [$sortDirection[0]]) && in_array($option['value'], $params)) {
                                $option['is_selected'] = true;
                            }
                            $option['sort_direction'] = 'ASC';
                        }
                    } elseif ($option['value'] == 'price') {
                        if ((string)$option['label'] == "Price Low-High") {
                            if (in_array('ASC', [$sortDirection[0]]) && in_array($option['value'], $params)) {
                                $option['is_selected'] = true;
                            }
                            $option['sort_direction'] = 'ASC';
                        } elseif ((string)$option['label'] == "Price High-Low") {
                            if (in_array('DESC', [$sortDirection[0]]) && in_array($option['value'], $params)) {
                                $option['is_selected'] = true;
                            }
                            $option['sort_direction'] = 'DESC';
                        }
                    } else {
                        $option['is_selected'] = (in_array($option['value'], $params)) ? true : false;
                        $option['sort_direction'] = 'ASC';
                    }
                } else {
                    //for search page
                    if ($option['value'] == 'sort_order') {
                        if ((string)$option['label'] == "Popularity") {
                            $option['sort_direction'] = 'ASC';
                        }
                    } elseif ($option['value'] == 'price') {
                        if ((string)$option['label'] == "Price Low-High") {
                            $option['sort_direction'] = 'ASC';
                        } elseif ((string)$option['label'] == "Price High-Low") {
                            $option['sort_direction'] = 'DESC';
                        }
                    } else {
                        $option['sort_direction'] = 'ASC';
                    }
                }
            }
        );
        $data = [
            'default' => ucwords($this->catalogConfig->getProductListDefaultSortBy($storeId)),
            'options' => $sortFieldsOptions,
        ];

        return $data;
    }
}
