<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Pricereduction;

use Magento\Backend\App\Action;

class Edit extends \Magento\Backend\App\Action
{
    protected $_coreRegistry;
    protected $resultPageFactory;

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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::price_reduction_edit');
	}

    protected function _initAction()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Anyhow_SupermaxPos::ah_price_reductions')
            ->addBreadcrumb(__('Override Management'), __('Override Management'))
            ->addBreadcrumb(__('Manage All Price Overrides'), __('Manage All Price Overrides'));
        return $resultPage;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxPriceReduction::class);
        if(!empty($id)) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This price reduction no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        $this->_coreRegistry->register('Pricereduction', $model);
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Override Price') : __('Add Override Price'),
            $id ? __('Edit Override Price') : __('Add Override Price')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('All Override Prices'));
        $resultPage->getConfig()->getTitle()
            ->prepend($model->getId() ? ('Edit Override Price') : __('Add Override Price'));

        return $resultPage;
    }
}
