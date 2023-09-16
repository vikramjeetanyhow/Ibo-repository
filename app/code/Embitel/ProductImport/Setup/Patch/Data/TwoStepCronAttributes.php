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

class TwoStepCronAttributes implements DataPatchInterface
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
            'two_step_status_cron',
            [
                'type' => 'int',
                'sort_order' => 500,
                'backend' => '',
                'frontend' => '',
                'label' => 'Picked by Two Step Status Cron',
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
            'two_step_publish_cron',
            [
                'type' => 'int',
                'sort_order' => 501,
                'backend' => '',
                'frontend' => '',
                'label' => 'Picked by Two Step Publish Cron',
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
