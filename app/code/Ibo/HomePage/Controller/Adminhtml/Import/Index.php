<?php
/**
 * Bestdeal Products Import Form
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\HomePage\Controller\Adminhtml\Import;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Ibo\HomePage\Controller\Adminhtml\Import as ImportController;
use Magento\Framework\Controller\ResultFactory;

class Index extends ImportController implements HttpGetActionInterface
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

        $resultPage->setActiveMenu('Ibo_Homepage::bestdeal');

        $resultPage->getConfig()->getTitle()->prepend(__('Import Bestdeal Products'));
        return $resultPage;
    }
}
