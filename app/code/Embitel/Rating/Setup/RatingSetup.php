<?php

/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Embitel\Rating\Setup;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;

class RatingSetup
{
    /**
     * @var \Magento\Eav\Model\Config $eavConfig
     */
    private $eavConfig;

    /**
     * VirtualCategorySetup constructor.
     *
     * @param \Magento\Eav\Model\Config $eavConfig EAV Config.
     */
    public function __construct(\Magento\Eav\Model\Config $eavConfig)
    {
        $this->eavConfig = $eavConfig;
    }

    /**
     * Create product rating attribute.
     *
     * @param \Magento\Eav\Setup\EavSetup $eavSetup EAV module Setup
     */
    public function createRatingAttributes($eavSetup)
    {
        
        $entityType = $this->eavConfig->getEntityType(Product::ENTITY);
        $entityTypeId = $entityType->getId();
        $eavSetup->removeAttribute($entityTypeId, 'ratings');
        $eavSetup->addAttribute(
            $entityTypeId,
            'ratings',
            [
                'group' => 'Product Details',
                'type' => 'varchar',
                'sort_order' => 200,
                'backend' => '',
                'frontend' => '',
                'label' => 'Top Rated Products',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,            
                'default' => '0',
                'searchable' => true,
                'visible_in_advanced_search'=>false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'used_for_sort_by'=>true,
                'user_defined' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
    }
}
