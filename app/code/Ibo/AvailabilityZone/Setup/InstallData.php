<?php
namespace Ibo\AvailabilityZone\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

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
        /** Adding availability_zone product multiselect attribute **/
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);        
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'availability_zone',
            [
                'group' => 'Core',
                'label' => 'Availability Zone',
                'type'  => 'text',
                'input' => 'multiselect',
                'required' => false,
                'sort_order' => 300,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'used_in_product_listing' => true,
                'backend' => 'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                'visible_on_front' => true,
                'visible' => true,
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
        $setup->endSetup();
    }
}
