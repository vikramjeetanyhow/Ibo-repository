<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Terminal;

class Release extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\Registry $registry,
		\Anyhow\SupermaxPos\Helper\Data $helper

	) {
		parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;

	}

	public function execute()
	{
		$id = $this->getRequest()->getParam('id');
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
		$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
		$connection = $resource->getConnection();
		$sql = "Select * FROM ah_supermax_pos_user_login_history WHERE pos_user_login_id = $id";
		$result = $connection->fetchAll($sql);
             if(!empty($result)){
                foreach ($result as $value) {
                       $UserId= $value['pos_user_id'];
                       $connection->query("UPDATE ah_supermax_pos_user_login_history SET status = 0, logout_time = NOW(), is_forced = 1 WHERE pos_user_id = $UserId And status = 1");
                        $connection->query("UPDATE ah_supermax_pos_api SET expire = NOW() WHERE pos_user_id = $UserId");
                }
            }
		$this->messageManager->addSuccess(__('Terminal Release Successfully.'));
        /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
	}
}