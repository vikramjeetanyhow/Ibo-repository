<?php

namespace Ibo\OfflinePaymentsGraphQl\Setup;

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
            $attributeInfo = [
                ['label' => 'Virtual Customer Account', 'code' => 'virtual_customer_account', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Enable Offline Payment', 'code' => 'enable_offline_payment', 'input' => 'boolean','type' => 'int','visible' => true],
                ['label' => 'Virtual Account Ifsc', 'code' => 'virtual_account_ifsc', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Virtual Account Bank', 'code' => 'virtual_account_bank', 'input' => 'text','type' => 'varchar','visible' => true]
            ];
            foreach($attributeInfo as $currentAttribute){
                $attributeLabel = $currentAttribute['label'];
                $attributeVisibility = !empty($currentAttribute['visible']) ? true : false;

                $attributeArray = [
                    'label' => $currentAttribute['label'],
                    'input' => $currentAttribute['input'],
                    'type' => $currentAttribute['type'],
                    'source' => '',
                    'required' => false,
                    'position' => 1,
                    'visible' => $attributeVisibility,
                    'system' => false,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                    'is_searchable_in_grid' => false,
                    'frontend_input' => 'hidden',
                    'backend' => ''
                ];
                $customerSetup->addAttribute('customer', $currentAttribute['code'], $attributeArray);
                $attribute = $customerSetup->getEavConfig()
                                           ->getAttribute('customer', $currentAttribute['code']);
                $attribute->addData([
                    'is_user_defined' => 1,
                ]);
                $attribute->setData('used_in_forms', ['adminhtml_customer']);
                $attribute->save();   
            }
        }
   }
}