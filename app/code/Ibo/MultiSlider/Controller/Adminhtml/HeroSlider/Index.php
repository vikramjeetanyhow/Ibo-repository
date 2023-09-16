<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(
        PageFactory $resultPageFactory,
        Context $context
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        /**
         * Set active menu item
         */
        $resultPage->setActiveMenu('Ibo_MultiSlider::heroslider');
        $resultPage->getConfig()->getTitle()->prepend(__('Category Hero Slider'));

        /**
         * Add breadcrumb item
         */
        $resultPage->addBreadcrumb(__('Ibo'), __('Ibo'));
        $resultPage->addBreadcrumb(__('Category Hero Slider'), __('Category Hero Slider'));

        return $resultPage;
    }
}
