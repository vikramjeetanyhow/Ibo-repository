<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Posterminal;

use Magento\Backend\App\Action;

class Edit extends \Magento\Backend\App\Action
{
    protected $_coreRegistry;
    protected $resultPageFactory;

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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::pos_terminal_edit');
	}

    protected function _initAction()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_pos_terminals')
            ->addBreadcrumb(__('POS Terminal Management'), __('POS Terminal Management'))
            ->addBreadcrumb(__('Manage All POS Terminals'), __('Manage All POS Terminals'));
        return $resultPage;
    }

    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
        if($assignedOutletIds != -1) {
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxPosTerminal::class);
        if(!empty($id)) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This pos terminal no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
    } else{
        // $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
            /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('supermax/posterminal/nostore');
    }


        $this->_coreRegistry->register('Posterminal', $model);
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit POS Terminal') : __('Add POS Terminal'),
            $id ? __('Edit POS Terminal') : __('Add POS Terminal')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('All POS Terminals'));
        $resultPage->getConfig()->getTitle()
            ->prepend($model->getId() ? ('Edit POS Terminal') : __('Add POS Terminal'));

        return $resultPage;
    }
}
