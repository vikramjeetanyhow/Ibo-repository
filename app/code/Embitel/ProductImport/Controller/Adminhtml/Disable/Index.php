<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductImport\Controller\Adminhtml\Disable;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Embitel\ProductImport\Controller\Adminhtml\Disable as DisableController;
use Magento\Framework\Controller\ResultFactory;

class Index extends DisableController implements HttpGetActionInterface
{
    /**
     * Product Import Page
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $resultPage->setActiveMenu('Embitel_ProductImport::disable');
        
        /*$resultPage->addContent(
            $resultPage->getLayout()->createBlock(\Embitel\ProductImport\Block\Adminhtml\Import\Index::class)
        );*/
        $resultPage->getConfig()->getTitle()->prepend(__('EBO Product Disable'));
        return $resultPage;
    }
}
