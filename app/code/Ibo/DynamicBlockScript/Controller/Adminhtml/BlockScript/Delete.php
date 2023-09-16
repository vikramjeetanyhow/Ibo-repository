<?php

namespace Ibo\DynamicBlockScript\Controller\Adminhtml\BlockScript;

use Ibo\DynamicBlockScript\Model\BlockScriptFactory;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;
use Exception;

class Delete extends Action
{
    protected $resultRedirect;
    protected $model;

    public function __construct(
        RedirectFactory $resultRedirect,
        BlockScriptFactory $model,
        Context $context
    ) {
        $this->resultRedirect = $resultRedirect;
        $this->model = $model;
        parent::__construct($context);
    }

    public function execute()
    {
        $loadId = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirect->create();
        if (!($this->model->create()->load($loadId))) {
            $this->messageManager->addErrorMessage(__('Unable to proceed. Please, try again.'));
            return $resultRedirect->setPath('*/*/index', ['_current' => true]);
        }
        if ($loadId) {
            try {
                // Init model and delete
                $this->model->create()->load($loadId)->delete();
                $this->messageManager->addSuccessMessage(__('Your Script has been deleted !'));
                return $resultRedirect->setPath('*/*/index');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                // Result redirected to the index controller
                return $resultRedirect->setPath('*/*/index', ['_current' => true]);
            }
            $this->messageManager->addErrorMessage(__('We can\'t find a Static block to delete.'));
            return $resultRedirect->setPath('*/*/index', ['_current' => true]);
        }
    }
}
