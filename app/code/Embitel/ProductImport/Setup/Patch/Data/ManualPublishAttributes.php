<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare (strict_types = 1);

namespace Embitel\ProductImport\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
 
class ManualPublishAttributes implements DataPatchInterface
{
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /** @var EavSetupFactory */
    private $eavSetupFactory;
 
    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
    }
    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
 
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'manual_unpublish',
            [
                'type' => 'int',
                'sort_order' => 200,
                'backend' => '',
                'frontend' => '',
                'label' => 'Manually UnPublished',
                'input' => 'boolean',
                'class' => '',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_STORE,                   
                'visible' => true,
                'required' => false,                    
                'searchable' => false,
                'visible_in_advanced_search'=>false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'used_for_sort_by'=>false,
                'user_defined' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'manual_unpublish_reason',
            [
                'type' => 'text',
                'frontend' => '',
                'label' => 'Reason For Manually UnPublished',
                'input' => 'textarea',
                'class' => '',
                'source' => '',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'is_wysiwyg_enabled'      => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'manual_disable',
            [
                'type' => 'int',
                'sort_order' => 200,
                'backend' => '',
                'frontend' => '',
                'label' => 'Manually Disabled',
                'input' => 'boolean',
                'class' => '',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_STORE,                   
                'visible' => true,
                'required' => false,                    
                'searchable' => false,
                'visible_in_advanced_search'=>false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'used_for_sort_by'=>false,
                'user_defined' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'manual_disable_reason',
            [
                'type' => 'text',
                'frontend' => '',
                'label' => 'Reason For Manually Disabled',
                'input' => 'textarea',
                'class' => '',
                'source' => '',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'is_wysiwyg_enabled'      => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );
        $this->moduleDataSetup->getConnection()->endSetup();
    }
 
    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
 
    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
