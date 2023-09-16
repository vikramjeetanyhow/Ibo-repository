<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model;

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;
use Anyhow\SupermaxPos\Api\Data;
use Anyhow\SupermaxPos\Api\ReceiptRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt as SupermaxReceipt;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt\CollectionFactory as SupermaxReceiptCollectionFactory;

class ReceiptRepository implements ReceiptRepositoryInterface
{
    protected $resource;

    protected $receiptFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataReceiptFactory;

    private $storeManager;

    public function __construct(
        SupermaxReceipt $resource,
        SupermaxReceiptFactory $receiptFactory,
        Data\ReceiptInterface $dataReceiptFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->receiptFactory = $receiptFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataReceiptFactory = $dataReceiptFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\ReceiptInterface $receipt )
    {
        // if ($receipt->getStoreId() === null) {
        //     $storeId = $this->storeManager->getStore()->getId();
        //     $receipt->setStoreId($storeId);
        // }
        // echo '<pre>';
        // print_r($receipt->getStoreId());
        // echo '</pre>';
        // die();
        try {
            $this->resource->save($receipt);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the receipt data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $receipt;
    }

    public function getById($receiptId)
    {
		$receipt = $this->receiptFactory->create();
        $receipt->load($receiptId);
        if (!$receipt->getId()) {
            throw new NoSuchEntityException(__('Receip Data with id "%1" does not exist.', $receiptId));
        }
        return $receipt;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\ReceiptInterface $receipt)
    {
        try {
            $this->resource->delete($receipt);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the receipt data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($receiptId)
    {
        return $this->delete($this->getById($receiptId));
    }
}