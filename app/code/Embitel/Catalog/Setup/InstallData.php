<?php
namespace Embitel\Catalog\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Catalog\Model\Category;

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
        
        $eavSetup->addAttributeGroup(
            Category::ENTITY,
            $eavSetup->getDefaultAttributeSetId(Category::ENTITY),
            'Category Attributes',
            500
        );

        //Text field attributes.
        $textAttributes = [
            "category_id" => "Category ID",
            "parent_category_id" => "Parent Category ID",
            "title_name_rule" => "Title Name Rule",
            "variant_attribute" => "Variant Attribute"
        ];

        foreach ($textAttributes as $attributeCode => $attributeLabel) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                $attributeCode,
                [
                    'type'     => 'varchar',
                    'label'    => $attributeLabel,
                    'input'    => 'text',
                    'visible'  => true,
                    'required' => false,
                    'global'   => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'    => 'Category Attributes',
                ]
            );
        }

        //Drop down attributes.
        $DropdownAttributes = [
            "hierarchy_type" => [
                "label" => "Hierarchy Type",
                "source" => "Embitel\Catalog\Model\Category\Attribute\Source\HierarchyType"
            ],
            "category_type" => [
                "label" => "Category Type",
                "source" => "Embitel\Catalog\Model\Category\Attribute\Source\CategoryType"
            ]
        ];

        foreach ($DropdownAttributes as $attributeCode => $fields) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                $attributeCode,
                [
                    'type' => 'varchar',
                    'label' => $fields['label'],
                    'input' => 'select',
                    'source' => $fields['source'],
                    'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                    'required' => false,
                    'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group' => 'Category Attributes',
                    'used_in_product_listing' => true
                ]
            );
        }
    }
}
