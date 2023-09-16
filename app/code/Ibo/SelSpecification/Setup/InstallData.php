<?php
namespace Ibo\SelSpecification\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;

class InstallData implements InstallDataInterface
{
    /**
     * @var EavSetupFactory 
     */
    private $eavSetupFactory;

    /**
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        
        //Category attribute
        $attributeCode = 'sel_specification_rule';
        $eavSetup->removeAttribute(Category::ENTITY, $attributeCode);

        $eavSetup->addAttribute(
            Category::ENTITY,
            $attributeCode,
            [
                'type'         => 'varchar',
                'label'        => 'SEL Specification Rule',
                'input'        => 'text',
                'global'       => ScopedAttributeInterface::SCOPE_STORE,
                'visible'      => true,
                'required'     => false,
                'user_defined' => true,
                'group'        => 'Category Attributes',
                'backend'      => ''
            ]
        );

        //Product attribute
        $attributeCode = 'sel_specification_output';
        $eavSetup->removeAttribute(Product::ENTITY, $attributeCode);
        $eavSetup->addAttribute(
            Product::ENTITY,
            $attributeCode,
            [
                'group' => 'Product Details',
                'type' => 'varchar',
                'sort_order' => 220,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'label' => 'SEL Specification Output',
                'input' => 'text',
                'visible' => true,
                'required' => false,
                'searchable' => false,
                'visible_in_advanced_search'=>false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'used_for_sort_by'=>false,
                'user_defined' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
    }
}
