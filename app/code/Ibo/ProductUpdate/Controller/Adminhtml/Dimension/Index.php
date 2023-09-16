<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Dimension;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Ibo\ProductUpdate\Controller\Adminhtml\Dimension as DimensionController;
use Magento\Framework\Controller\ResultFactory;

class Index extends DimensionController implements HttpGetActionInterface
{
    /**
     * Dimension Import Page
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $resultPage->setActiveMenu('Ibo_ProductUpdate::dimension');

        $resultPage->getConfig()->getTitle()->prepend(__('Import Product Package Dimension'));
        return $resultPage;
    }
}
