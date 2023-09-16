<?php

namespace Embitel\Oodo\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Eav\Setup\EavSetup;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute
     */
    protected $_eavAttribute;


    public function __construct(
        EavSetup $eavSetupFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute $eavAttribute
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->_eavAttribute = $eavAttribute;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $setup->startSetup();
            $entityType = $this->eavSetupFactory->getEntityType(\Magento\Customer\Model\Customer::ENTITY);
            $entityTypeId = $entityType['entity_type_id'];
            $attributeId = $this->_eavAttribute->getIdByCode(\Magento\Customer\Model\Customer::ENTITY, 'oodo_customer_id');
            $this->eavSetupFactory->updateAttribute($entityTypeId, $attributeId, 'is_visible', 1, null);
            $setup->endSetup();
        }

        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $setup->startSetup();
            $entityType = $this->eavSetupFactory->getEntityType(\Magento\Customer\Model\Customer::ENTITY);
            $entityAddressType = $this->eavSetupFactory->getEntityType('customer_address');
            $entityTypeId = $entityType['entity_type_id'];
            $entityAddressTypeId = $entityAddressType['entity_type_id'];
            $attributeLastnameId = $this->_eavAttribute->getIdByCode(\Magento\Customer\Model\Customer::ENTITY, 'lastname');
            $attributeAddrLastnameId = $this->_eavAttribute->getIdByCode('customer_address', 'lastname');
            $attributeAddrCityId = $this->_eavAttribute->getIdByCode('customer_address', 'city');
            $this->eavSetupFactory->updateAttribute($entityTypeId, $attributeLastnameId, 'is_required', 0, null);
            $this->eavSetupFactory->updateAttribute($entityAddressTypeId, $attributeAddrLastnameId, 'is_required', 0, null);
            $this->eavSetupFactory->updateAttribute($entityAddressTypeId, $attributeAddrCityId, 'is_required', 0, null);
            $setup->endSetup();
        }
    }
}