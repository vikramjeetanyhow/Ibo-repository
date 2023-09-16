<?php
namespace Embitel\CatalogGraphQl\Observer\Grid;

use Magento\Framework\Module\Manager;
use Magento\Framework\Event\ObserverInterface;

/**
 * Product attribute grid build observer
 */
class ProductAttributeGridBuildObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * Construct.
     *
     * @param Manager $moduleManager
     */
    public function __construct(Manager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Execute.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->moduleManager->isOutputEnabled('Magento_LayeredNavigation')) {
            return;
        }

        /** @var \Magento\Catalog\Block\Adminhtml\Product\Attribute\Grid $grid */
        $grid = $observer->getGrid();
        
        $grid->addColumnAfter(
            'is_required_for_publish',
            [
                'header' => __('Required for Publish'),
                'sortable' => true,
                'index' => 'is_required_for_publish',
                'type' => 'options',
                'options' => ['1' => __('Yes'), '0' => __('No')],
                'align' => 'center'
            ],
            'is_required'
        );
    }
}
