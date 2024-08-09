<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;
use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;

use Magento\Backend\Model\Session;
use Anyhow\SupermaxPos\Model\SupermaxRegister;

class Save extends \Magento\Backend\App\Action
{
    protected $registerModel;

    protected $adminsession;

    public function __construct(
        Action\Context $context,
        SupermaxRegister $registerModel,
        Session $adminsession
    ) {
        parent::__construct($context);
        $this->registerModel = $registerModel;
        $this->adminsession = $adminsession;
    }

    
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
    
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($data) {
            $data['reconciliation_status'] = 1;
            $id = $this->getRequest()->getParam('pos_register_id');
            if ($id) {
                $this->registerModel->load($id);
            }

            $this->registerModel->setData($data);

            try {
                $this->registerModel->save();
                $this->messageManager->addSuccess(__('The Head Cashier reconcile has been saved.'));
                $this->adminsession->setFormData(false);
                if ($this->getRequest()->getParam('back')) {
                    if ($this->getRequest()->getParam('back') == 'add') {
                        return $resultRedirect->setPath('*/*/add');
                    } else {
                        return $resultRedirect->setPath('*/*/edit', ['pos_register_id' => $this->registerModel->getId(), '_current' => true]);
                    }
                }

                return $resultRedirect->setPath('*/*/');
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\RuntimeException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addException($e, __('Something went wrong while saving the data.'));
            }

            $this->_getSession()->setFormData($data);
            return $resultRedirect->setPath('*/*/edit', ['pos_register_id' => $this->getRequest()->getParam('pos_register_id')]);
        }

        return $resultRedirect->setPath('*/*/');
    }
}
