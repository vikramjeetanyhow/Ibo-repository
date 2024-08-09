<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Pricereduction;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxPriceReduction;

class Save extends \Magento\Backend\App\Action
{
    protected $dataPersistor;

    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Anyhow\SupermaxPos\Model\SupermaxPriceReductionFactory $SupermaxPriceReductionFactory = null,
        \Anyhow\SupermaxPos\Api\PriceReductionRepositoryInterface $PriceReductionRepository = null,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
        $this->SupermaxPriceReductionFactory = $SupermaxPriceReductionFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxPriceReductionFactory::class);
        $this->PriceReductionRepository = $PriceReductionRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\PriceReductionRepositoryInterface::class);
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::price_reduction_save');
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

        // $connection = $this->resourceConnection->getConnection();
        // $userRoleTable = $this->resourceConnection->getTableName('ah_supermax_pos_price_reductions');
        
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (isset($data['status']) && $data['status'] === 'true') {
                $data['status'] = SupermaxPriceReduction::STATUS_ENABLED;
            }
            if (empty($data['pos_price_reduction_id'])) {
                $data['pos_price_reduction_id'] = null;
            }

            /** @var \Anyhow\SupermaxPos\Model\SupermaxPriceReduction $model */
            $model = $this->SupermaxPriceReductionFactory->create();

            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->PriceReductionRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This override price no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            $this->_eventManager->dispatch(
                'PriceReduction_PriceReduction_prepare_save',
                ['PriceReduction' => $model, 'request' => $this->getRequest()]
            );

            try {
                $this->PriceReductionRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the override price data.'));
                $this->dataPersistor->clear('PriceReduction');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
                
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the override price data.'));
            }

            $this->dataPersistor->set('PriceReduction', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}