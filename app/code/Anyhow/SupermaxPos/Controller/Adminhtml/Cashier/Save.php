<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Cashier;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxUser;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var \Anyhow\SupermaxPos\Model\SupermaxUserFactory
     */
    private $cashierFactory;

    /**
     * @var \Anyhow\SupermaxPos\Api\CashierRepositoryInterface
     */
    private $cashierRepository;

    /**
     * @param Action\Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param Anyhow\SupermaxPos\Model\SupermaxUserFactory $SupermaxUserFactory
     * @param Anyhow\SupermaxPos\Api\CashierRepositoryInterface $CashierRepository
     */
    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Anyhow\SupermaxPos\Model\SupermaxUserFactory $SupermaxUserFactory = null,
        \Anyhow\SupermaxPos\Api\CashierRepositoryInterface $CashierRepository = null,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
        $this->SupermaxUserFactory = $SupermaxUserFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxUserFactory::class);
        $this->CashierRepository = $CashierRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\CashierRepositoryInterface::class);
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::cashier_save');
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
        $userTable = $this->resourceConnection->getTableName('ah_supermax_pos_user');
        $userData = array();
        
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (isset($data['status']) && $data['status'] === 'true') {
                $data['status'] = SupermaxUser::STATUS_ENABLED;
            }
            if (isset($data['password'])) {
                $data['password'] = $this->encryptor->getHash($data['password'], false);
            }
            if (empty($data['pos_user_id'])) {
                $data['pos_user_id'] = null;
            }

            /** @var \Anyhow\SupermaxPos\Model\SupermaxUser $model */
            $model = $this->SupermaxUserFactory->create();

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->CashierRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This employee no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            $this->_eventManager->dispatch(
                'Cashier_Cashier_prepare_save',
                ['Cashier' => $model, 'request' => $this->getRequest()]
            );

            try {

                if(!empty($data['username'])) {
                    $username = $data['username'];
                    $userId = $model->getId();
                    $sql = "SELECT * FROM $userTable WHERE username = '$username'";
                    if(!empty($userId)){
                        $sql .= " AND pos_user_id != $userId";
                    }
                    $userData = $connection->query($sql)->fetchAll();
                }
    
                if(empty($userData)){
                    $this->CashierRepository->save($model);
                    $this->messageManager->addSuccessMessage(__('You saved the employee data.'));
                    $this->dataPersistor->clear('Cashier');
                } else {
                    $data['password'] = '';
                    $data['confirm_password'] = '';
                    $this->messageManager->addErrorMessage('This username is not available.');
                }
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
                
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the employee data.'));
            }

            $this->dataPersistor->set('Cashier', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}