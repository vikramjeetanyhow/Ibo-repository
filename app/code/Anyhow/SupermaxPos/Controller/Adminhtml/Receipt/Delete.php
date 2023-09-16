<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Receipt;


class Delete extends \Magento\Backend\App\Action
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->resource = $resourceConnection;
        parent::__construct($context);
    }
    /**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::receipt_delete');
	}
	
	/**
     * Delete action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $connection = $this->resource->getConnection();
        $receiptStoreTable = $this->resource->getTableName('ah_supermax_pos_receipt_store');
        // check if we know what should be deleted
        $id = $this->getRequest()->getParam('id');
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            $title = "";
            try {
                // init model and delete
                $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxReceipt::class);
                $model->load($id);
                $title = $model->getTitle();
                $connection->query("DELETE FROM $receiptStoreTable WHERE receipt_id = $id");
                $model->delete();
                // display success message
                $this->messageManager->addSuccess(__('The Receipt data has been deleted.'));
                // go to grid
                $this->_eventManager->dispatch(
                    'adminhtml_receipt_on_delete',
                    ['title' => $title, 'status' => 'success']
                );
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->_eventManager->dispatch(
                    'adminhtml_receipt_on_delete',
                    ['title' => $title, 'status' => 'fail']
                );
                // display error message
                $this->messageManager->addError($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addError(__('We can\'t find a Receipt to delete.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }
}
