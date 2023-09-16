<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Controller\Adminhtml\Disable;

use Magento\Framework\Controller\ResultFactory;

class Post extends \Embitel\ProductImport\Controller\Adminhtml\Disable
{
    /**
     * Product disable action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $productDisableFile = $this->getRequest()->getFiles('productdisable_file');
        if ($this->getRequest()->isPost() && isset($productDisableFile['tmp_name'])) {
            try {
                /** @var $disableHandler \Embitel\ProductImport\Model\Disable\ProductDisableHandler */
                $disableHandler = $this->_objectManager->create(
                    \Embitel\ProductImport\Model\Disable\ProductDisableHandler::class
                );
                $response = $disableHandler->disableFromCsvFile($productDisableFile);

                //$this->messageManager->addSuccess(__('The products has been disableed.'));
                if (isset($response['success'])) {
                    $message = __(
                        "Success records. Click <a href=\"%1\">here</a> to download file.",
                        $disableHandler->getBackendUrl('success_'.$response['uniqid'])
                    );
					 $this->messageManager->addSuccess($message);
                }
				
                if (isset($response['failure'])) {
                    $message = __(
                        "Failed records. Click <a href=\"%1\">here</a> to download file.",
                        $disableHandler->getBackendUrl('failure_'.$response['uniqid'])
                    );
                    $this->messageManager->addError($message);
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addError(__('Invalid file upload attempt' . $e->getMessage()));
            }
        } else {
            $this->messageManager->addError(__('Invalid file upload attempt'));
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRedirectUrl());
        return $resultRedirect;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(
            'Embitel_ProductImport::disable'
        );
    }
}
