<?php

namespace Embitel\CustomerAddress\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\InstallDataInterface;

class InstallData implements InstallDataInterface
{
   private $customerSetupFactory;

   public function __construct(\Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory)
   {
       $this->customerSetupFactory = $customerSetupFactory;
   }

   public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
   {
       $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
       if (version_compare($context->getVersion(), '1.0.1') < 0) {
           $customerSetup->addAttribute('customer_address', 'landmark', [
               'label' => 'Landmark',
               'input' => 'text',
               'type' => 'varchar',
               'source' => '',
               'required' => false,
               'position' => 90,
               'visible' => true,
               'system' => false,
               'is_used_in_grid' => false,
               'is_visible_in_grid' => false,
               'is_filterable_in_grid' => false,
               'is_searchable_in_grid' => false,
               'frontend_input' => 'hidden',
               'backend' => ''
           ]);

              $attribute=$customerSetup->getEavConfig()
                ->getAttribute('customer_address','landmark')                                  
                ->addData(['used_in_forms' => [
                   'adminhtml_customer_address',
                   'adminhtml_customer',
                   'customer_address_edit',
                   'customer_register_address',
                   'customer_address',
                  ]
                ]);
           $attribute->save();
       }
   }
}