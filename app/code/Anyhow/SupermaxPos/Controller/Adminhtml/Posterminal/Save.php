<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Posterminal;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxPosTerminal;

class Save extends \Magento\Backend\App\Action
{
    protected $dataPersistor;

    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Anyhow\SupermaxPos\Model\SupermaxPosTerminalFactory $SupermaxPosTerminalFactory = null,
        \Anyhow\SupermaxPos\Api\PosTerminalRepositoryInterface $PosTerminalRepository = null,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
        $this->SupermaxPosTerminalFactory = $SupermaxPosTerminalFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxPosTerminalFactory::class);
        $this->PosTerminalRepository = $PosTerminalRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\PosTerminalRepositoryInterface::class);
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::pos_terminal_save');
	}

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ah_supermax_pos_terminals');
        $terminalData = array();
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (isset($data['status']) && $data['status'] === 'true') {
                $data['status'] = SupermaxPosTerminal::STATUS_ENABLED;
            }
            if (empty($data['pos_terminal_id'])) {
                $data['pos_terminal_id'] = null;
            }

            /** @var \Anyhow\SupermaxPos\Model\SupermaxPosTerminal $model */
            $model = $this->SupermaxPosTerminalFactory->create();

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->PosTerminalRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This pos terminal no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            $this->_eventManager->dispatch(
                'PosTerminal_PosTerminal_prepare_save',
                ['PosTerminal' => $model, 'request' => $this->getRequest()]
            );

            try {
                $terminalCode = $data['code'];
                $ezetap_username = $ezetap_device_id = $pinelabs_merchant_pos_code = $pinelabs_device_id = "";
                $sql = "SELECT * FROM $table WHERE ( code = '$terminalCode' ";
                if($data['edc_type'] == 'ezetap') {
                    if(!empty($data['ezetap_username']) && !empty($data['ezetap_device_id'])) {
                        $ezetap_username = $data['ezetap_username'];
                        $ezetap_device_id = $data['ezetap_device_id'];
                        $sql .= " OR ezetap_username = '$ezetap_username' OR ezetap_device_id = '$ezetap_device_id'";
                    }
                } elseif($data['edc_type'] == 'pinelabs') {
                    if(!empty($data['pinelabs_merchant_pos_code']) && !empty($data['pinelabs_device_id'])) {
                        $pinelabs_merchant_pos_code = $data['pinelabs_merchant_pos_code'];
                        $pinelabs_device_id = $data['pinelabs_device_id'];
                        $sql .= " OR pinelabs_merchant_pos_code = '$pinelabs_merchant_pos_code' OR pinelabs_device_id = '$pinelabs_device_id'";
                    }
                }
               
                $sql .= ") " ;
                $terminalId = $model->getId();
                if(!empty($terminalId)){
                    $sql .= " AND pos_terminal_id != '$terminalId'";
                }

                $terminalData = $connection->query($sql)->fetch();
                
                if(empty($terminalData)){
                    $this->PosTerminalRepository->save($model);
                    $this->messageManager->addSuccessMessage(__('You saved the pos terminal data.'));
                    $this->dataPersistor->clear('PosTerminal');
                } else {
                    if($terminalData['code'] == $terminalCode) {
                        $this->messageManager->addErrorMessage('Terminal code is not available. Please try again with other unique values.');
                    } elseif(!empty($ezetap_username) && $terminalData['ezetap_username'] == $ezetap_username) {
                        $this->messageManager->addErrorMessage('Ezetap Username is not available. Please try again with other unique values.');
                    } elseif(!empty($ezetap_device_id) && $terminalData['ezetap_device_id'] == $ezetap_device_id) {
                        $this->messageManager->addErrorMessage('Ezetap Device Id is not available. Please try again with other unique values.');
                    } elseif(!empty($pinelabs_merchant_pos_code) && $terminalData['pinelabs_merchant_pos_code'] == $pinelabs_merchant_pos_code) {
                        $this->messageManager->addErrorMessage('Pinelabs Marchant Pos Code is not available. Please try again with other unique values.');
                    } elseif(!empty($pinelabs_device_id) && $terminalData['pinelabs_device_id'] == $pinelabs_device_id) {
                        $this->messageManager->addErrorMessage('Pinelabs Device Id is not available. Please try again with other unique values.');
                    } else {
                        $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the pos terminal data.'));
                    }
                }
                
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
                
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the pos terminal data.'));
            }

            $this->dataPersistor->set('PosTerminal', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}