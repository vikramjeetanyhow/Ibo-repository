<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Pincodemaster;

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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::pincode_edit');
	}

    protected function _initAction()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::pincode_edit')
            ->addBreadcrumb(__('Pincode Master'), __('Pincode Master'))
            ->addBreadcrumb(__('Manage Pincodes'), __('Manage Pincodes'));
        return $resultPage;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxPincodeMaster::class);
        if(!empty($id)) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This pincode no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }
    
        $this->_coreRegistry->register('pincodemaster', $model);
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Pincode') : __('Add Pincode'),
            $id ? __('Edit Pincode') : __('Add Pincode')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('All Pincodes'));
        $resultPage->getConfig()->getTitle()
            ->prepend($model->getId() ? ('Edit Pincode') : __('Add Pincode'));

        return $resultPage;
    }
}
