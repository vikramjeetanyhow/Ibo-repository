<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Receipt;

use Magento\Backend\App\Action;
class NewAction extends \Magento\Backend\App\Action
{
    protected $resultForwardFactory;

    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::receipt_edit');
	}

    /**
     * Init actions
     *
     * @return \Magento\Backend\Model\View\Result\Receipt
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Receipt $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_receipts')
            ->addBreadcrumb(__('Receipt Management'), __('Receipt Management'))
            ->addBreadcrumb(__('Manage All Receipts'), __('Manage All Receipts'));
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
        $resultPage->addBreadcrumb(__('New Receipt'), __('New Receipt'));
        $resultPage->getConfig()->getTitle()->prepend(__('All Receipts'));
        $resultPage->getConfig()->getTitle()->prepend('New Receipt');

        return $resultPage;
        /** @var \Magento\Backend\Model\View\Result\Forward $resultForward */
        // $resultForward = $this->resultForwardFactory->create();
        // return $resultForward->forward('edit');

    }
}