<?php

namespace Ibo\DynamicBlockScript\Controller\Adminhtml\BlockScript;

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
        $resultPage->setActiveMenu('Ibo_DynamicBlockScript::blockscript');
        $resultPage->getConfig()->getTitle()->prepend(__('Static Block Scripts'));

        /**
         * Add breadcrumb item
         */
        $resultPage->addBreadcrumb(__('Ibo'), __('Ibo'));
        $resultPage->addBreadcrumb(__('Static Block Scripts'), __('Static Block Scripts'));

        return $resultPage;
    }
}
