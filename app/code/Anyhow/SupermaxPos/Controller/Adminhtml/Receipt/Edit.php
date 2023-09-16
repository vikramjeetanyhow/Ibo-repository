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

class Edit extends \Magento\Backend\App\Action
{
	/**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * 
     */
    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        \Anyhow\SupermaxPos\Helper\Data $helper

    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
        $this->helper = $helper;

        
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
     * Edit Receipts
     * @return \Magento\Backend\Model\View\Result\Receipts|\Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
        if($assignedOutletIds != -1) {
        $id = $this->getRequest()->getParam('id');
        $storeId = $this->getRequest()->getParam('store');
        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxReceipt::class);
        if(!empty($id)) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This Receipt no longer exists.'));
                /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
    } else{
        // $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
            /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('supermax/receipt/nostore');
        }


        $this->_coreRegistry->register('Receipt', $model);

        /** @var \Magento\Backend\Model\View\Result\Receipt $resultPage */
        $resultPage = $this->_initAction();
        // $resultPage->addBreadcrumb(
        //     $id ? __('Edit Receipt') : __('Add Receipt'),
        //     $id ? __('Edit Receipt') : __('Add Receipt')
        // );
        $resultPage->addBreadcrumb(__('Edit Receipt'), __('Edit Receipt'));
        
        $resultPage->getConfig()->getTitle()->prepend(__('All Receipts'));
        // $resultPage->getConfig()->getTitle()
        //     ->prepend($model->getId() ? ('Edit Receipt') : __('Add Receipt'));
        $resultPage->getConfig()->getTitle()->prepend('Edit Receipt');

        return $resultPage;
    }
}
