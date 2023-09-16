<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Embitel\Rating\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetupFactory;

class InstallData implements InstallDataInterface
{
    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    
    private $ratingSetup;

    /**
     * InstallData constructor.
     *
     * @param \Magento\Eav\Setup\EavSetupFactory          $eavSetupFactory EAV Setup Factory     
     */
    public function __construct(EavSetupFactory $eavSetupFactory, RatingSetup $ratingSetup)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->ratingSetup     = $ratingSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $this->ratingSetup->createRatingAttributes($eavSetup);

        $setup->endSetup();
    }
}
