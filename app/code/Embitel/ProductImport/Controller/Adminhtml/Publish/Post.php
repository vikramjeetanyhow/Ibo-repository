<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Controller\Adminhtml\Publish;

use Magento\Framework\Controller\ResultFactory;

class Post extends \Embitel\ProductImport\Controller\Adminhtml\Publish
{
    /**
     * Product publish action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $productPublishFile = $this->getRequest()->getFiles('productpublish_file');
        if ($this->getRequest()->isPost() && isset($productPublishFile['tmp_name'])) {
            try {
                /** @var $publishHandler \Embitel\ProductImport\Model\Publish\ProductPublishHandler */
                $publishHandler = $this->_objectManager->create(
                    \Embitel\ProductImport\Model\Publish\ProductPublishHandler::class
                );
                $response = $publishHandler->publishFromCsvFile($productPublishFile);

                //$this->messageManager->addSuccess(__('The products has been published.'));
                if (isset($response['success'])) {
                    $message = __(
                        "Success records. Click <a href=\"%1\">here</a> to download file.",
                        $publishHandler->getBackendUrl('success_'.$response['uniqid'])
                    );
					 $this->messageManager->addSuccess($message);
                }
				
                if (isset($response['failure'])) {
                    $message = __(
                        "Failed records. Click <a href=\"%1\">here</a> to download file.",
                        $publishHandler->getBackendUrl('failure_'.$response['uniqid'])
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
            'Embitel_ProductImport::publish'
        );
    }
}
