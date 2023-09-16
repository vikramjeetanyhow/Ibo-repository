<?php
namespace Ibo\HomePage\Controller\Adminhtml\Topcategory;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Ibo\HomePage\Controller\Adminhtml\Topcategory as TopcategoryController;
use Magento\Framework\Controller\ResultFactory;

class Index extends TopcategoryController implements HttpGetActionInterface
{
    /**
     * Items list.
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Ibo_Homepage::topcategory');
        $resultPage->getConfig()->getTitle()->prepend(__('Top Categories/Brands'));
        return $resultPage;
    }
}