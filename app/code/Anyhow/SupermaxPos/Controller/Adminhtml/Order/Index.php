<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Order;

class Index extends \Magento\Backend\App\Action
{
	protected $resultPageFactory = false;

	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Anyhow\SupermaxPos\Helper\Data $helper

	)
	{
		parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;
	}

	public function execute()
	{
		$assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
		if($assignedOutletIds != -1) {
			$resultPage = $this->resultPageFactory->create();
			$resultPage->getConfig()->getTitle()->prepend((__('Supermax Pos Orders')));
		return $resultPage;
		} else{
			// $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
				/** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
				$resultRedirect = $this->resultRedirectFactory->create();
				return $resultRedirect->setPath('supermax/order/nostore');
		}
		
	}
}