<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

class Delete extends \Embitel\Banner\Controller\Adminhtml\Items
{

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $bannerModel = $this->bannerFactory;
                $model =  $bannerModel->create();
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccess(__('You deleted the banner.'));
                $this->_redirect('embitel_banner/*/');
                return;
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addError(
                    __('We can\'t delete banner right now. Please review the log and try again.')
                );
                $this->logger->critical($e);
                $this->_redirect('embitel_banner/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }
        $this->messageManager->addError(__('We can\'t find a banner to delete.'));
        $this->_redirect('embitel_banner/*/');
    }
}
