<?php

namespace Embitel\OrderStatus\Controller\Adminhtml\Oms;

class Repush extends \Magento\Backend\App\Action
{
    protected $resultPageFactory = false;
    protected $resultRedirectFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Backend\Model\Auth\Session $authSession
    ) {
        parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
        $this->resource = $resourceConnection;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->authSession = $authSession;
    }

    public function execute() {
        $bulkOrderNumbers = $this->getRequest()->getParam('order_numbers');
        try {
            $connection = $this->resource->getConnection();
            $salesOrderTable = $this->resource->getTableName('sales_order');
            $user = $this->authSession->getUser(); 
            $adminId = $user->getUserId();
            $omsOrdersHistoryTable = $this->resource->getTableName('ibo_oms_orders_repush_history');
            $connection->query("UPDATE $salesOrderTable SET `oms_status_flag`= 0 WHERE `increment_id` IN (" . $bulkOrderNumbers . ")");
            $connection->query("INSERT INTO $omsOrdersHistoryTable SET admin_id=$adminId, order_numbers='$bulkOrderNumbers', date_added=NOW()");
            $this->messageManager->addSuccessMessage( __('OMS flag for Orders (' . $bulkOrderNumbers . ') have been reset successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while resetting the OMS flags for orders: ' . $bulkOrderNumbers));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('iboorders/oms/index');
        return $resultRedirect;
    }
}
