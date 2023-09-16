<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Cashier;

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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::cashier_edit');
	}

    /**
     * Init actions
     *
     * @return \Magento\Backend\Model\View\Result\Cashier
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Cashier $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_cashiers')
            ->addBreadcrumb(__('Employee Management'), __('Employee Management'))
            ->addBreadcrumb(__('Manage All Employees'), __('Manage All Employees'));
        return $resultPage;
    }

    /**
     * Edit Cashiers
     * @return \Magento\Backend\Model\View\Result\Cashiers|\Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
        if($assignedOutletIds != -1) {
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxUser::class);
        if(!empty($id)) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This employee no longer exists.'));
                /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
    } else{
        // $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
            /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('supermax/cashier/nostore');
    }

        $this->_coreRegistry->register('Cashier', $model);

        /** @var \Magento\Backend\Model\View\Result\Cashier $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Employee') : __('Add Employee'),
            $id ? __('Edit Employee') : __('Add Employee')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('All Employees'));
        $resultPage->getConfig()->getTitle()
            ->prepend($model->getId() ? ('Edit Employee') : __('Add Employee'));

        return $resultPage;
    }
}
