<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare (strict_types = 1);

namespace Embitel\ProductImport\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
 
class ProductPublishAttributes implements DataPatchInterface
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
            'unpublish_at',
            [
                'type' => 'datetime',
                'backend' => '',
                'frontend' => '',
                'label' => 'Unpublish At',
                'input' => 'date',
                'class' => '',
                'source' => '',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'sort_order' => 9,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        ); 
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'disabled_at',
            [
                'type' => 'datetime',
                'backend' => '',
                'frontend' => '',
                'label' => 'Disabled At',
                'input' => 'date',
                'class' => '',
                'source' => '',
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'sort_order' => 9,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
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
