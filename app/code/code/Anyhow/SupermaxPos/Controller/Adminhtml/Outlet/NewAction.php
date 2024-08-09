<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Outlet;


class NewAction extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Backend\Model\View\Result\Forward
     */
    protected $resultForwardFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::outlet_edit');
	}
    
    /**
     * Init actions
     *
     * @return \Magento\Backend\Model\View\Result\Outlet
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Outlet $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_outlets')
            ->addBreadcrumb(__('Store Management'), __('Store Management'))
            ->addBreadcrumb(__('Manage All Stores'), __('Manage All Stores'));
        return $resultPage;
    }
    /**
     * Forward to edit
     *
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Receipt $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(__('New Store'), __('New Store'));
        $resultPage->getConfig()->getTitle()->prepend(__('New Store'));
        $resultPage->getConfig()->getTitle()->prepend('New Store');

        return $resultPage;
        /** @var \Magento\Backend\Model\View\Result\Forward $resultForward */
        // $resultForward = $this->resultForwardFactory->create();
        // return $resultForward->forward('edit');
    }
}