<?php

namespace Embitel\Catalog\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.1') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'is_shopby');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'is_shopby',
                [
                    'type'         => 'int',
                    'label'        => 'Home Shop By Category',
                    'input'        => 'select',
                    'sort_order'   => 100,
                    'source'       => Boolean::class,
                    'global'       => ScopedAttributeInterface::SCOPE_STORE,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'default'      => null,
                    'group'        => 'General Information',
                    'backend'      => ''
                ]
            );


            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'is_bestdeal');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'is_bestdeal',
                [
                    'group' => 'Product Details',
                    'type' => 'int',
                    'sort_order' => 200,
                    'backend' => '',
                    'frontend' => '',
                    'label' => 'Best Deal',
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
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.2') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'home_shopby_category_text');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'home_shopby_category_text',
                [
                    'type'         => 'varchar',
                    'label'        => 'Home Shop By Category Text',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Category Attributes',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.3') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'is_shopby_brand');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'is_shopby_brand',
                [
                    'type'         => 'int',
                    'label'        => 'Home Shop By Brand',
                    'input'        => 'select',
                    'sort_order'   => 100,
                    'source'       => Boolean::class,
                    'global'       => ScopedAttributeInterface::SCOPE_STORE,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'default'      => null,
                    'group'        => 'General Information',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.4') < 0) {
            $attributeCode = 'attribute_set';
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                $attributeCode,
                [
                    'type' => 'int',
                    'label' => 'Attribute Set',
                    'input' => 'select',
                    'source' => 'Embitel\Catalog\Model\Category\Attribute\Source\AttributeSet',
                    'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'required' => false,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Category Attributes',
                    'used_in_product_listing' => false
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.5') < 0) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'allowed_channels');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'allowed_channels',
                [
                    'group' => 'Core',
                    'type' => 'varchar',
                    'sort_order' => 200,
                    'label' => 'Allowed Channels',
                    'input' => 'select',
                    'class' => '',
                    'source' => 'Embitel\Catalog\Model\Config\Source\AllowedChannels',
                    'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'frontend' => '',
                    'default' => 'OMNI',
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'required' => true,
                    'searchable' => true,
                    'filterable' => false,
                    'filterable_in_search' => false,
                    'is_filterable_in_grid' => true,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => false,
                    'visible_in_advanced_search'=>false,
                    'comparable' => false,
                    'visible_on_front' => true,
                    'used_in_product_listing' => true,
                    'used_for_sort_by'=>false,
                    'user_defined' => true,
                    'unique' => false,
                    'apply_to' => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.6') < 0) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'service_category');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'service_category',
                [
                    'group' => 'Core',
                    'type' => 'varchar',
                    'sort_order' => 200,
                    'label' => 'Service Category',
                    'input' => 'select',
                    'class' => '',
                    'source' => 'Embitel\Catalog\Model\Config\Source\ServiceCategory',
                    'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'frontend' => '',
                    'default' => 'LOCAL',
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'required' => true,
                    'searchable' => true,
                    'filterable' => false,
                    'filterable_in_search' => false,
                    'is_filterable_in_grid' => true,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => false,
                    'visible_in_advanced_search'=>false,
                    'comparable' => false,
                    'visible_on_front' => true,
                    'used_in_product_listing' => true,
                    'used_for_sort_by'=>false,
                    'user_defined' => true,
                    'unique' => false,
                    'apply_to' => ''
                ]
            );
        }
        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.11') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'primary_banner_image');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'primary_banner_image',
                [
                    'type' => 'varchar',
                    'label' => 'Primary Banner Image',
                    'input' => 'image',
                    'backend' => 'Magento\Catalog\Model\Category\Attribute\Backend\Image',
                    'required' => false,
                    'sort_order' => 9,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Navigation Banner',
                    'used_in_product_listing' => false
                ]
            );

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'primary_banner_link_category_id');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'primary_banner_link_category_id',
                [
                    'type'         => 'varchar',
                    'label'        => 'Primary Banner Link Category Id',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Navigation Banner',
                    'backend'      => ''
                ]
            );


            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'secondary_banner_image');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'secondary_banner_image',
                [
                    'type' => 'varchar',
                    'label' => 'Banner 2',
                    'input' => 'image',
                    'backend' => 'Magento\Catalog\Model\Category\Attribute\Backend\Image',
                    'required' => false,
                    'sort_order' => 9,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Navigation Banner',
                    'used_in_product_listing' => false
                ]
            );

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'secondary_banner_link_category_id');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'secondary_banner_link_category_id',
                [
                    'type'         => 'varchar',
                    'label'        => 'Secondary Banner Link Categroy Id',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Navigation Banner',
                    'backend'      => ''
                ]
            );
        }

         if ($context->getVersion() && version_compare($context->getVersion(), '1.0.12') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'primary_banner_title');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'primary_banner_title',
                [
                    'type'         => 'varchar',
                    'label'        => 'Primary Banner Title',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Navigation Banner',
                    'backend'      => ''
                ]
            );

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'secondary_banner_title');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'secondary_banner_title',
                [
                    'type'         => 'varchar',
                    'label'        => 'Secondary Banner Title',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Navigation Banner',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.13') < 0) {
            $attributeCode = 'service_category';
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                $attributeCode,
                [
                    'type'         => 'varchar',
                    'label'        => 'Service Catagory',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Category Attributes',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.15') < 0) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'courier_flag');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'courier_type',
                [
                    'group' => 'SCM',
                    'type' => 'varchar',
                    'sort_order' => 200,
                    'label' => 'Courier Type',
                    'input' => 'select',
                    'class' => '',
                    'source' => 'Embitel\Catalog\Model\Config\Source\CourierFlag',
                    'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'frontend' => '',
                    'default' => 'F',
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'required' => false,
                    'searchable' => true,
                    'filterable' => false,
                    'filterable_in_search' => false,
                    'is_filterable_in_grid' => true,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => false,
                    'visible_in_advanced_search'=>false,
                    'comparable' => false,
                    'visible_on_front' => true,
                    'used_in_product_listing' => true,
                    'used_for_sort_by'=>false,
                    'user_defined' => true,
                    'unique' => false,
                    'apply_to' => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.16') < 0) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'base_image_custom');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'base_image_custom',
                [
                    'type' => 'varchar',
                    'label' => 'Ibo Base Image',
                    'input' => 'text',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'sort_order' => 11,
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group' => 'IBO Core Media',
                    'backend'      => ''
                ]
            );

            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'primary_banner_image_custom');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'primary_banner_image_custom',
                [
                    'type' => 'varchar',
                    'label' => 'Ibo Primary Banner Image',
                    'input' => 'text',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'sort_order' => 12,
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group' => 'IBO Core Media',
                    'backend'      => ''
                ]
            );


            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, 'secondary_banner_image_custom');
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                'secondary_banner_image_custom',
                [
                    'type' => 'varchar',
                    'label' => 'Ibo Secondary Banner Image',
                    'input' => 'text',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'sort_order' => 14,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group' => 'IBO Core Media',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.17') < 0) {
            $attributeCode = 'ibo_brand_id';
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                $attributeCode,
                [
                    'type'         => 'varchar',
                    'label'        => 'IBO Brand Id',
                    'input'        => 'text',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Category Attributes',
                    'backend'      => ''
                ]
            );
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.18') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'catalog_service_sync_count');
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.19') < 0) {
            $attributeCode = 'plp_content';
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Category::ENTITY, $attributeCode);

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Category::ENTITY,
                $attributeCode,
                [
                    'type'         => 'int',
                    'label'        => 'Add PLP Content',
                    'input'        => 'select',
                    'sort_order'   => 100,
                    'source' => 'Magento\Catalog\Model\Category\Attribute\Source\Page',
                    'global'       => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible'      => true,
                    'required'     => false,
                    'user_defined' => true,
                    'group'        => 'Category Attributes',
                    'backend'      => ''
                ]
            );
        }
        /*
        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.20') < 0) {
            $attributeCode = 'is_returnable';
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                $attributeCode,
                [
                    'group' => 'Core',
                    'frontend' => '',
                    'label' => 'Enable RMA',
                    'input' => 'select',
                    'class' => '',
                    'source' => \Embitel\Catalog\Model\Config\Source\IsReturnable::class,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_WEBSITE,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => \Embitel\Catalog\Model\Config\Source\IsReturnable::ATTRIBUTE_ENABLE_RMA_NO,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'unique' => false,
                    'apply_to' => '',
                ]
            );
        }
        */

        $setup->endSetup();
    }
}
