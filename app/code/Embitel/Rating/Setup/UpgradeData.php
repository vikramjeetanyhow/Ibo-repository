<?php

namespace Embitel\Rating\Setup;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Psr\Log\LoggerInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $templates;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $setup;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var EavSetup
     */
    protected $eavSetup;

    /**
     * @param LoggerInterface $logger
     * @param EavConfig $eavConfig
     * @param EavSetupFactory $eavSetupFactory
     * @param array $templates
     */
    public function __construct(
        LoggerInterface $logger,
        EavConfig $eavConfig,
        EavSetupFactory $eavSetupFactory,
        array $templates = []
    ) {
        $this->logger = $logger;
        $this->eavConfig = $eavConfig;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->templates = $templates;
    }

    /**
     * @inheritDoc
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $this->setup = $setup;
        $this->eavSetup = $this->eavSetupFactory->create([
            'setup' => $setup
        ]);

        $this->setup->startSetup();
        $this->installAttributes();
        $this->setup->endSetup();
    }

    protected function installAttributes()
    {
        $this->installProductAttributes();
    }

    protected function installProductAttributes()
    {
        $entityType = $this->eavConfig->getEntityType(Product::ENTITY);
        $entityTypeId = $entityType->getId();
        
        $this->eavSetup->removeAttribute($entityTypeId, 'ratings');
        $this->eavSetup->addAttribute(
            $entityTypeId,
            'ratings',
            [
                'group' => 'Product Details',
                'type' => 'int',
                'sort_order' => 200,
                'backend' => '',
                'frontend' => '',
                'label' => 'Top Rated Products',
                'input' => 'select',
                'class' => '',
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'option' => array('values' => array("1 Star", "2 Star", "3 Star", "4 Star", "5 Star")),
                'searchable' => true,
                'visible_in_advanced_search'=>false,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'used_for_sort_by'=>true,
                'user_defined' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );
    }
}
