<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Pincodemaster;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxPincodeMaster;

class Save extends \Magento\Backend\App\Action
{
    protected $dataPersistor;

    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Anyhow\SupermaxPos\Model\SupermaxPincodeMasterFactory $SupermaxPincodeMasterFactory = null,
        \Anyhow\SupermaxPos\Api\PincodeMasterRepositoryInterface $PincodeMasterRepository = null,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
        $this->SupermaxPincodeMasterFactory = $SupermaxPincodeMasterFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxPincodeMasterFactory::class);
        $this->PincodeMasterRepository = $PincodeMasterRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\PincodeMasterRepositoryInterface::class);
            $this->curl= $curl;
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::pincode_save');
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
        $table = $this->resourceConnection->getTableName('pincode');
        $terminalData = array();
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
       
        if (!empty($data['pincode'])) {
            if (empty($data['entity_id'])) {
                $data['entity_id'] = null;
            }

            /** @var \Anyhow\SupermaxPos\Model\SupermaxPincodeMaster $model */
            $model = $this->SupermaxPincodeMasterFactory->create();
            // $model->setData($data);

            $this->_eventManager->dispatch(
                'Pincode_Pincode_prepare_save',
                ['PincodeMaster' => $model, 'request' => $this->getRequest()]
            );

            try {
                $pinCode = $data['pincode'];
                $pincodeData = $connection->query("SELECT * FROM $table WHERE `pincode` = '$pinCode'")->fetch();
                if(empty($pincodeData)){
                    $url = 'https://services.ibo.com/logistics/v1/post-codes/' . $pinCode;
                    $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                    $this->curl->setOption(CURLOPT_HTTPGET, true);
                    $headers = ["trace_id" => "123", "client_id" => "123", 'Content-Type' => 'application/json'];
                    $this->curl->setHeaders($headers);
                    $this->curl->get($url);
                    $result = $this->curl->getBody();
                    $resultData = json_decode($result, true);
                    if(isset($resultData['city']) && isset($resultData['state'])) {
                        $model->setPincode($pinCode);
                        $model->setCity($resultData['city']);
                        $model->setDistrictname($resultData['city']);
                        $model->setStatename($resultData['state']);
                        $this->PincodeMasterRepository->save($model);
                        $this->messageManager->addSuccessMessage(__('You saved the pincode data.'));
                        $this->dataPersistor->clear('PincodeMaster');
                    } else {
                        $this->messageManager->addErrorMessage('The given pincode is not present in pincode master.');
                    }
                  
                } else {
                    $this->messageManager->addErrorMessage('The given pincode is already present.');
                }
                
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the pincode data.'));
            }
            $this->dataPersistor->set('PincodeMaster', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        } else {
            $this->messageManager->addErrorMessage( __('Something went wrong while saving the pincode data.'));
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}