<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Updatecontrol;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Ibo\ProductUpdate\Controller\Adminhtml\Updatecontrol as UpdatecontrolController;
use Magento\Framework\Controller\ResultFactory;

class Index extends UpdatecontrolController implements HttpGetActionInterface
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

        $resultPage->setActiveMenu('Ibo_ProductUpdate::updatelotcontrol');

        $resultPage->getConfig()->getTitle()->prepend(__('Import for LotContol Update'));
        return $resultPage;
    }
}
