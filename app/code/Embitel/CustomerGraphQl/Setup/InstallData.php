<?php

namespace Embitel\CustomerGraphQl\Setup;

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
                ['label' => 'Owner name', 'code' => 'owner_name', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Customer Type', 'code' => 'customer_type', 'input' => 'multiselect','type' => 'int','visible' => true,
                'option' => ['values' => ['Dealer','Architect/Interior Designer','Contractor','Expert/Technician','Individual']]],
                ['label' => 'Longitude', 'code' => 'longitude', 'input' => 'text','type' => 'varchar'],
                ['label' => 'Latitude', 'code' => 'latitude', 'input' => 'text','type' => 'varchar'],
                ['label' => 'ERP ID', 'code' => 'erp_id', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Outlet Category', 'code' => 'outlet_category', 'input' => 'textarea','type' => 'varchar'],
                ['label' => 'PAN Card Number', 'code' => 'pan_card_number', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Customer Contribution Info', 'code' => 'customer_contribution_info', 'input' => 'text','type' => 'varchar','visible' => true]
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
                if(!empty($currentAttribute['input']) && $currentAttribute['input'] == 'multiselect'){
                    $attributeArray['source'] = 'Magento\Eav\Model\Entity\Attribute\Source\Table';
                }
                if(!empty($currentAttribute['option'])){
                    $attributeArray['option'] = $currentAttribute['option'];
                }
                $customerSetup->addAttribute('customer', $currentAttribute['code'], $attributeArray);
                $attribute = $customerSetup->getEavConfig()
                                           ->getAttribute('customer', $currentAttribute['code'])
                                           ->addData([
                                                'is_user_defined' => 1,
                                            ]);
                $attribute->save();
            }
        }

       /*
       if (version_compare($context->getVersion(), '1.0.8', '<')) {
           $attributeArray = [
               'label' => 'First Time Promo Applied',
               'input' => 'boolean',
               'type' => 'int',
               'source' => '',
               'required' => false,
               'position' => 1,
               'visible' => true,
               'system' => false,
               'is_used_in_grid' => false,
               'is_visible_in_grid' => false,
               'is_filterable_in_grid' => false,
               'is_searchable_in_grid' => false,
               'frontend_input' => 'hidden',
               'backend' => ''
           ];

           $customerSetup->addAttribute('customer', 'first_time_promo_applied', $attributeArray);
           $attribute = $customerSetup->getEavConfig()
               ->getAttribute('customer', 'first_time_promo_applied')
               ->addData([
                   'is_user_defined' => 1,
               ]);
           $attribute->save();
       }
       */
   }
}
