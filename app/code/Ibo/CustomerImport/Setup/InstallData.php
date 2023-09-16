<?php
 
namespace Ibo\CustomerImport\Setup;

use Magento\Eav\Model\Config;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
       
        //Create Yes/No type customer attribute programmatically

        $eavSetup->addAttribute(\Magento\Customer\Model\Customer::ENTITY, 'is_csv_import', [
                    'type'         => 'varchar',
                    'label'        => 'Is CSV Import',
                    'input'        => 'text',
                    'required'     => false,
                    'visible'      => true,
                    'user_defined' => false,
                    'position'     => 999,
                    'system'       => 0
                ]);

        $this->getEavConfig()->getAttribute('customer', 'is_csv_import')
        ->setData('is_user_defined', 1)
        ->setData('is_required', 0)
        ->setData('default_value', 'No')
        ->setData('used_in_forms', [
            'adminhtml_customer',
            'checkout_register',
            'customer_account_create',
            'customer_account_edit',
            'adminhtml_checkout'
        ])->save();
    }
    
    public function getEavConfig()
    {
        return $this->eavConfig;
    }
}
