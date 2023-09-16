<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Receipt;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxReceipt;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var \Anyhow\SupermaxPos\Model\SupermaxReceiptFactory
     */
    private $receiptFactory;

    /**
     * @var \Anyhow\SupermaxPos\Api\ReceiptRepositoryInterface
     */
    private $receiptRepository;

    /**
     * @param Action\Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param Anyhow\SupermaxPos\Model\SupermaxReceiptFactory $SupermaxReceiptFactory
     * @param Anyhow\SupermaxPos\Api\ReceiptRepositoryInterface $ReceiptRepository
     */
    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\SupermaxReceiptFactory $SupermaxReceiptFactory = null,
        \Anyhow\SupermaxPos\Api\ReceiptRepositoryInterface $ReceiptRepository = null
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->storeManager = $storeManager;
        $this->SupermaxReceiptFactory = $SupermaxReceiptFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxReceiptFactory::class);
        $this->ReceiptRepository = $ReceiptRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\ReceiptRepositoryInterface::class);
        parent::__construct($context);
        $this->resource = $resourceConnection;
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::receipt_save');
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

        $connection = $this->resource->getConnection();
        $receiptTable = $this->resource->getTableName('ah_supermax_pos_receipt');
        $receiptStoreTable = $this->resource->getTableName('ah_supermax_pos_receipt_store');
        $media_url = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $base_url = $this->storeManager->getStore()->getBaseUrl();

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (isset($data['header_logo'][0]['name'])) {
                $url = $data['header_logo'][0]['url'];
                $substr = substr($media_url, strlen($base_url)-1, strlen($media_url)-1);
                $imagePathData = explode($substr, $url);
                $imagePath = $imagePathData[1];
                $data['header_logo'] = $data['header_logo'][0]['name'];
                $data['header_logo_path'] = $imagePath;
            }else {
                $data['header_logo'] = null;
                $data['header_logo_path'] = null;
            }
            if (empty($data['pos_receipt_id'])) {
                $data['pos_receipt_id'] = null;
            }
            /** @var \Anyhow\SupermaxPos\Model\SupermaxReceipt $model */
            $model = $this->SupermaxReceiptFactory->create();

            $id = $this->getRequest()->getParam('pos_receipt_id');
            $storeId = $data['store_id'];
            if(empty($storeId)){
                $storeId = 0;
            }
            if ($id) {
                try {
                    $model = $this->ReceiptRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This Receipt no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            $this->_eventManager->dispatch(
                'Receipt_Receipt_prepare_save',
                ['Receipt' => $model, 'request' => $this->getRequest()]
            );

            try {
                if($id) {
                    $previousReceiptParentId = null;

                    $receiptAllStoreData = $connection->query("SELECT * FROM $receiptStoreTable WHERE receipt_id = $id AND store_id = 0")->fetchAll();
                    if(!empty($receiptAllStoreData)){
                        $rceiptAllTitle = $receiptAllStoreData[0]['title'];
                        $rceiptAllHeaderDetails = $receiptAllStoreData[0]['header_details'];
                        $rceiptAllFooterDetails = $receiptAllStoreData[0]['footer_details'];
                        $rceiptAllSellerBankInfo = $receiptAllStoreData[0]['seller_bank_info'];
                        $rceiptAllDisclaimerInfo = $receiptAllStoreData[0]['disclaimer'];
                    }
                    $receiptStoreDatas = $connection->query("SELECT * FROM $receiptStoreTable WHERE receipt_id = $id AND store_id = $storeId")->fetchAll();
                    if(!empty($receiptStoreDatas)){
                        foreach($receiptStoreDatas as $receiptStoreData){
                            $previousReceiptParentId = $receiptStoreData['receipt_id'];
                        }
                    }
                    $this->ReceiptRepository->save($model);

                    // To check default value checkbox set.
                    if(isset($data['use_default']['title'])){
                        if((bool)$data['use_default']['title']){
                            $data['title'] = $rceiptAllTitle;
                        }
                    }
                    if(isset($data['use_default']['header_details'])){
                        if((bool)$data['use_default']['header_details']){
                            $data['header_details'] = $rceiptAllHeaderDetails;
                        }
                    }
                    if(isset($data['use_default']['footer_details'])){
                        if((bool)$data['use_default']['footer_details']){
                            $data['footer_details'] = $rceiptAllFooterDetails;
                        }
                    }
                    if(isset($data['use_default']['seller_bank_info'])){
                        if((bool)$data['use_default']['seller_bank_info']){
                            $data['seller_bank_info'] = $rceiptAllSellerBankInfo;
                        }
                    }
                    if(isset($data['use_default']['disclaimer'])){
                        if((bool)$data['use_default']['disclaimer']){
                            $data['disclaimer'] = $rceiptAllDisclaimerInfo;
                        }
                    }

                    if(!empty($previousReceiptParentId)){
                        $connection->query("UPDATE $receiptStoreTable SET title = '" .$data['title']. "', 
                            header_details = '".$data['header_details']."', footer_details = '".$data['footer_details']."', seller_bank_info = '".$data['seller_bank_info']."', disclaimer = '".$data['disclaimer']."' WHERE  receipt_id = $id AND store_id = $storeId" );
                    } else {
                        $connection->query("INSERT INTO $receiptStoreTable (receipt_id, store_id, title, header_details, footer_details,seller_bank_info,disclaimer) VALUES( $id, $storeId, '".$data['title']. "', '".$data['header_details']. "', '".$data['footer_details']. "','".$data['seller_bank_info']. "','".$data['disclaimer']."') ");
                    }
                } else {
                    $this->ReceiptRepository->save($model);
                    $receiptId = $model->getId();
                    $connection->query("INSERT INTO $receiptStoreTable (receipt_id, store_id, title, header_details, footer_details,seller_bank_info) VALUES( $receiptId, $storeId, '".$data['title']. "', '".$data['header_details']. "', '".$data['footer_details']. "','".$data['seller_bank_info']. "') ");
                }
                $this->messageManager->addSuccessMessage(__('You saved the Receipt data.'));
                $this->dataPersistor->clear('Receipt');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId(), 'store'=>$storeId, '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Receipt data.'));
            }

            $this->dataPersistor->set('Receipt', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('pos_receipt_id'), 'store'=> $this->getRequest()->getParam('store')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}