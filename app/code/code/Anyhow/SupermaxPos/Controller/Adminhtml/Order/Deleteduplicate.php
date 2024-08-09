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
use Magento\Framework\Controller\ResultFactory; 
use Magento\Backend\App\Action;
class Deleteduplicate extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;

	public function __construct(
		\Anyhow\SupermaxPos\Helper\Data $helper,
		Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\Registry $registry,
		\Embitel\Quote\Helper\Data $embitelHelper,
		\Magento\Backend\Model\Auth\Session $authSession
	) {
		$this->helper = $helper;
		$this->resultPageFactory = $resultPageFactory;
		$this->_coreRegistry = $registry;
		$this->embitelHelper = $embitelHelper;
		$this->authSession = $authSession;
		parent::__construct($context);	
	}

	public function execute()
	{
		try {
			$orderId = $this->getRequest()->getParam('order_id');
			if($orderId) {
				// if($this->helper->isOrderTotalCorrect($orderId)) {
					$this->helper->deleteDuplicateOrderItems($orderId);
					$this->embitelHelper->SuccessOrderExecute($orderId); 
					$user = $this->authSession->getUser();
					$userId = 0;
					$username = "";
					if(!empty($user)) {
						$userId = $user->getUserId();
						$username = $user->getUsername();
					}
					$logData = "----Duplicate order line items are deleted & order ID: " . $orderId . " is re-pushed By admin user Id: " . $userId . " & admin username: " . $username;
					$this->helper->addDebuggingLogData($logData);
					$this->messageManager->addSuccessMessage(
						__('Order has been successfully re-pushed to OMS')
					);
				// } else {
				// 	$this->messageManager->addErrorMessage(
				// 		__('Actual order total & calculated order total is not matching. Please contact to your admin.')
				// 	);
				// }
			} else {
				$this->messageManager->addErrorMessage(
					__('Order Id is blank')
				);
			}
		} catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while deleting data or repushing order')
            );
        }
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('supermax/order/index');
        return $resultRedirect;
	}
}