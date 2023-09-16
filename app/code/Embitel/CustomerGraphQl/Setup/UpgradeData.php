<?php

namespace Embitel\CustomerGraphQl\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Setup\EavSetup;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var \Magento\Customer\Setup\CustomerSetupFactory
     */
    private $customerSetupFactory;


    public function __construct(\Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory)
   {
       $this->customerSetupFactory = $customerSetupFactory;
   }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $attributeInfo = [
                ['label' => 'Insurance Opt In', 'code' => 'insurance_opt_in', 'input' => 'boolean','type' => 'int','visible' => true],
                ['label' => 'Relationship with Nominee', 'code' => 'relationship_with_nominee', 'input' => 'select','type' => 'int','visible' => true,
                'option' => ['values' => ['Mother','Father','Spouse']]],
                ['label' => 'Name of Insured', 'code' => 'name_of_insured', 'input' => 'text','type' => 'varchar', 'visible' => true],
                ['label' => 'Nominee Name', 'code' => 'nominee_name', 'input' => 'text','type' => 'varchar', 'visible' => true],
                ['label' => 'Customer Insurance number', 'code' => 'customer_insurance_number', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Insurance Agency', 'code' => 'insurance_agency', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Insurance Contact Number', 'code' => 'insurance_contact_number', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Referrer name', 'code' => 'referrer_name', 'input' => 'text','type' => 'varchar','visible' => true],
                ['label' => 'Referrer Phone Number', 'code' => 'referrer_phone_number', 'input' => 'text','type' => 'varchar','visible' => true]
            ];
            foreach($attributeInfo as $currentAttribute){
                $attributeLabel = $currentAttribute['label'];
                $attributeVisibility = !empty($currentAttribute['visible']) ? true : false;

                $source = ($currentAttribute['input'] == 'boolean') ? '\Magento\Eav\Model\Entity\Attribute\Source\Boolean' : '';
                $attributeArray = [
                    'label' => $currentAttribute['label'],
                    'input' => $currentAttribute['input'],
                    'type' => $currentAttribute['type'],
                    'source' => $source,
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
                if(!empty($currentAttribute['input']) && $currentAttribute['input'] == 'select'){
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

        if (version_compare($context->getVersion(), '1.0.3', '<')) {

            $customerSetup->addAttribute(
                'customer',
                'customer_secondary_email',
                [
                    'label' => 'Customer Secondary Email',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                           ->getAttribute('customer', 'customer_secondary_email')
                                           ->addData([
                                                'is_user_defined' => 1,
                                            ]);
                $attribute->save();
        }

        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            $attributeArray = [
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => true,
            ];
            $customerSetup->updateAttribute('customer', 'customer_type', $attributeArray);
        }

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            $customerSetup->removeAttribute('customer', 'business_activities');

                $attributeArray = [
                    'label' => 'Business Activities',
                    'input' => 'multiselect',
                    'type' => 'text',
                    'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Table',
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
                $attributeArray['option'] = ['Painter','Contractor'];
                $customerSetup->addAttribute('customer', 'business_activities', $attributeArray);
                $attribute = $customerSetup->getEavConfig()
                                           ->getAttribute('customer', 'business_activities');
                $used_in_forms = ['customer_account_create','customer_account_edit','adminhtml_checkout'];
                $attribute->setData("used_in_forms", $used_in_forms)
                        ->setData("is_used_for_customer_segment", true)
                        ->setData("is_system", 0)
                        ->setData("is_user_defined", 1)
                        ->setData("is_visible", 1)
                        ->setData("sort_order", 100);
                $attribute->save();
            }

        if (version_compare($context->getVersion(), '1.0.6', '<')) {

            $customerSetup->addAttribute(
                'customer',
                'referrer_customer_id',
                [
                    'label' => 'Referrer Customer Id',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                            ->getAttribute('customer', 'referrer_customer_id')
                                            ->addData([
                                                'is_user_defined' => 1,
                                            ]);
            $attribute->save();

            $customerSetup->addAttribute(
                'customer',
                'referrer_date',
                [
                    'label' => 'Referrer Date',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                            ->getAttribute('customer', 'referrer_date')
                                            ->addData([
                                                'is_user_defined' => 1,
                                            ]);
            $attribute->save();
        }

        if (version_compare($context->getVersion(), '1.0.7', '<')) {

            $customerSetup->addAttribute(
                'customer',
                'campaign_id',
                [
                    'label' => 'Campaign Id',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                            ->getAttribute('customer', 'campaign_id')
                                            ->addData([
                                                'is_user_defined' => 1,
                                            ]);
            $attribute->save();

            $customerSetup->addAttribute(
                'customer',
                'referral_id',
                [
                    'label' => 'Referral Id',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                            ->getAttribute('customer', 'referral_id')
                                            ->addData([
                                                'is_user_defined' => 1,
                                            ]);
            $attribute->save();

            $customerSetup->addAttribute(
                'customer',
                'personalised_coupon_code',
                [
                    'label' => 'Personalised Coupon Code',
                    'input' => 'text',
                    'type' => 'varchar',
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                                            ->getAttribute('customer', 'personalised_coupon_code')
                                            ->addData([
                                                'is_user_defined' => 1,
                                            ]);
            $attribute->save();

        }

        if (version_compare($context->getVersion(), '1.0.9', '<')) {
            $customerSetup->removeAttribute('customer', 'first_time_promo_applied');
            $customerSetup->addAttribute(
                'customer',
                'first_time_promo_applied',
                [
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
                ]
            );

            $attribute = $customerSetup->getEavConfig()
                ->getAttribute('customer', 'first_time_promo_applied')
                ->addData([
                    'is_user_defined' => 1,
                ]);
            $attribute->save();
        }

    }
}
