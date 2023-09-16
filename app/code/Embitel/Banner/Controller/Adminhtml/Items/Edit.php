<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

class Edit extends \Embitel\Banner\Controller\Adminhtml\Items
{

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
       
        $bannerModel = $this->bannerFactory;
        $model =  $bannerModel->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This banner no longer exists.'));
                $this->_redirect('embitel_banner/*');
                return;
            }
        }
        // set entered data if was error when we do save
        $data = $this->backendSession->getPageData(true);
        if (!empty($data)) {
            $model->addData($data);
        }
        $this->_coreRegistry->register('current_embitel_banner_items', $model);
        $this->_initAction();
        $this->_view->getLayout()->getBlock('items_items_edit');
        $this->_view->renderLayout();
    }
}
