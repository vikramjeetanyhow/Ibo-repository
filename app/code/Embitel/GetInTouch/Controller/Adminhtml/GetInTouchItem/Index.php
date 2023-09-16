<?php

namespace Embitel\GetInTouch\Controller\Adminhtml\GetInTouchItem;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Embitel_GetInTouch::info');
        $resultPage->addBreadcrumb(__('Embitel'), __('Embitel'));
        $resultPage->addBreadcrumb(__('Get In Touch'), __('Get In Touch'));
        $resultPage->getConfig()->getTitle()->prepend(__('Get In Touch'));

        return $resultPage;
    }
}
