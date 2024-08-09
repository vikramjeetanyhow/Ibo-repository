<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;

use Magento\Backend\App\Action;


class Edit  extends \Magento\Backend\App\Action
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
	// protected function _isAllowed()
	// {
	// 	return $this->_authorization->isAllowed('Anyhow_SupermaxPos::Register_edit');
	// }

    /**
     * Init actions
     *
     * @return \Magento\Backend\Model\View\Result\Register
     */
    protected function _initAction()
    {     

        // load layout, set active menu and breadcrumbs
        /** @var \Magento\Backend\Model\View\Result\Ragister $resultPage */
        $resultPage = $this->resultPageFactory->create();
        // $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_ragister')
        //     ->addBreadcrumb(__('Register Management'), __('Register Management'))
        //     ->addBreadcrumb(__('Manage All Registers'), __('Manage All Registers'));
        return $resultPage;
        
    }

    /**
     * Edit Ragisters
    * @return \Magento\Backend\Model\View\Result\Ragisters|\Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
        if($assignedOutletIds != -1) {
            $id = $this->getRequest()->getParam('pos_register_id');
            $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxRegister::class);
            if(!empty($id)) {
                $model->load($id);
                if (!$model->getId()) {
                    $this->messageManager->addError(__('This Ragister no longer exists.'));
                    /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    return $resultRedirect->setPath('*/*/');
                }
            }
        } else{
                // $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
                    /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    return $resultRedirect->setPath('supermax/register/nostore');
            }
            $this->_coreRegistry->register('Register', $model);

            /** @var \Magento\Backend\Model\View\Result\Register $resultPage */
            $resultPage = $this->_initAction();
            // $resultPage->addBreadcrumb(
        //     $id ? __('Register & Cash Management') : __('Add Register'),
        //     $id ? __('Register & Cash Management') : __('Add Register')
        // );
        $resultPage->addBreadcrumb(__('Register & Cash Management'), __('Register & Cash Management'));       
            
        $resultPage->getConfig()->getTitle()->prepend('Head Cashier Reconcile');

        return $resultPage;
    }
}

