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
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Anyhow\SupermaxPos\Helper\Data $helper

    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
        $this->supermaxSession = $supermaxSession;
        $this->helper = $helper;

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
     * Edit Outlet
     * @return \Magento\Backend\Model\View\Result\Outlet|\Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
        if($assignedOutletIds != -1) {
        $id = $this->getRequest()->getParam('id');
        $this->supermaxSession->setPosOutletId($id);

        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxPosOutlet::class);
        if(!empty($id)) {
            $model->load($id);
            //$outletId = $model->getId();
            if (!$model->getId()) {
                $this->messageManager->addError(__('This store no longer exists.'));
                /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
    } else{
        // $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
            /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('supermax/outlet/nostore');
    }

        $this->_coreRegistry->register('outlet', $model);

        /** @var \Magento\Backend\Model\View\Result\Receipt $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(__('Edit Store'), __('Edit Store'));
        
        $resultPage->getConfig()->getTitle()->prepend(__('All Stores'));
        // $resultPage->getConfig()->getTitle()
        //     ->prepend($model->getId() ? ('Edit Receipt') : __('Add Receipt'));
        $resultPage->getConfig()->getTitle()->prepend('Edit Store');

        return $resultPage;

        // /** @var \Magento\Backend\Model\View\Result\Outlet $resultPage */
        // $resultPage = $this->_initAction();
        // $resultPage->addBreadcrumb(
        //     $id ? __('Edit Outlet') : __('Add Outlet'),
        //     $id ? __('Edit Outlet') : __('Add Outlet')
        // );
        // $resultPage->getConfig()->getTitle()->prepend(__('All Outlets'));
        // $resultPage->getConfig()->getTitle()
        //     ->prepend($model->getId() ? ('Edit Outlet') : __('Add Outlet'));

        // return $resultPage;
    }
}