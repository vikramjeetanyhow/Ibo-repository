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
use Anyhow\SupermaxPos\Api\PincodeMasterRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPincodeMaster as SupermaxPincodeMaster;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPincodeMaster\CollectionFactory as SupermaxPincodeMasterCollectionFactory;

class PincodeMasterRepository implements PincodeMasterRepositoryInterface
{
    protected $resource;

    protected $pincodeFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataPincodeFactory;

    private $storeManager;

    public function __construct(
        SupermaxPincodeMaster $resource,
        SupermaxPincodeMasterFactory $pincodeFactory,
        Data\PincodeMasterInterface $dataPincodeFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->pincodeFactory = $pincodeFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataPincodeFactory = $dataPincodeFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\PincodeMasterInterface $pincode)
    {
        if ($pincode->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $pincode->setStoreId($storeId);
        }
        try {
            $this->resource->save($pincode);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the pincode data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $pincode;
    }

    public function getById($pincodeId)
    {
		$pincode = $this->pincodeFactory->create();
        $pincode->load($pincodeId);
        if (!$pincode->getId()) {
            throw new NoSuchEntityException(__('Pincode data with id "%1" does not exist.', $pincodeId));
        }
        return $pincode;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\PincodeMasterInterface $pincode)
    {
        try {
            $this->resource->delete($pincode);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the price reduction data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($pincodeId)
    {
        return $this->delete($this->getById($pincodeId));
    }
}