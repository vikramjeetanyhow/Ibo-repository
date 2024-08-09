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

use Magento\Backend\App\Action\Context;

class Delete extends \Magento\Backend\App\Action
{

    public function __construct(
        Context $context, 
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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::price_reduction_delete');
	}
	
	/**
     * Delete action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {

        // check if we know what should be deleted
        $id = $this->getRequest()->getParam('id');
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {

            $title = "";
            try {

                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('ah_supermax_pos_price_reductions'); 

                $price_reasons_data = $connection->query("SELECT * FROM $tableName WHERE pos_price_reduction_id = $id")->fetch();
                if(empty($price_reasons_data)){

                    // init model and delete
                    $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxPriceReduction::class);
                    $model->load($id);
                    $title = $model->getTitle();
                    $model->delete();
                    // display success message
                    $this->messageManager->addSuccess(__('The price override data has been deleted.'));
                    // go to grid
                    $this->_eventManager->dispatch(
                        'adminhtml_Price_reasons_on_delete',
                        ['title' => $title, 'status' => 'success']
                    );
                } else {
                    $this->messageManager->addError(__('The price override can\'t be deleted due to it is assigned to one or more employee. Please disable it instead of deleting it.'));
                }
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->_eventManager->dispatch(
                    'adminhtml_Price_reasons_on_delete',
                    ['title' => $title, 'status' => 'fail']
                );
                // display error message
                $this->messageManager->addError($e->getMessage());
                // go back to edit form
                return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
            }
        }
        // display error message
        $this->messageManager->addError(__('We can\'t find a price override to delete.'));
        // go to grid
        return $resultRedirect->setPath('*/*/');
    }
}
