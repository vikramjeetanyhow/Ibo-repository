<?php
 
 
namespace Embitel\Customer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
 
class AddCustomerAttribute implements DataPatchInterface, PatchRevertableInterface
{
 
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var CustomerSetup
     */
    private $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;
 
    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        IndexerRegistry $indexerRegistry
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->indexerRegistry = $indexerRegistry;
    }
 
    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');        
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();      
 
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);
       
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'sales_rep_name',
            [
                'group' => 'General',
                'type' => 'static',
                'label' => 'Sales Representative Name',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 100,
                'position' => 100,
                'used_in_grid' => true,
                'visible_in_grid' => true,
                'searchable_in_grid' => true,
                'filterable_in_grid' => true,
                'system' => 0
            ]
        );
         
        $salesrepname = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'sales_rep_name')->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer'
            ],
            'is_used_for_customer_segment' => true,
            'is_system' => 0,
            'is_user_defined' => 1,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true
        ]);
        $salesrepname->save();

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'sales_rep_number',
            [
                'group' => 'General',
                'type' => 'static',
                'label' => 'Sales Representative Number',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 100,
                'position' => 100,
                'used_in_grid' => true,
                'visible_in_grid' => true,
                'searchable_in_grid' => true,
                'filterable_in_grid' => true,
                'system' => 0
            ]
        );
         
        $salesrepnum = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'sales_rep_number')->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer'
            ],
            'is_used_for_customer_segment' => true,
            'is_system' => 0,
            'is_user_defined' => 1,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true
        ]);
        $salesrepnum->save();

        $indexer = $this->indexerRegistry->get(Customer::CUSTOMER_GRID_INDEXER_ID);
        $indexer->invalidate();
        $this->moduleDataSetup->getConnection()->endSetup();
    }
 
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->removeAttribute(Customer::ENTITY, 'sales_rep_name');
        $customerSetup->removeAttribute(Customer::ENTITY, 'sales_rep_number');
 
        $this->moduleDataSetup->getConnection()->endSetup();
    }
 
    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
 
    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
