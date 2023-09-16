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
use Anyhow\SupermaxPos\Api\OutletRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet as SupermaxPosOutlet;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\CollectionFactory as SupermaxPosOutletCollectionFactory;

class OutletRepository implements OutletRepositoryInterface
{
    protected $resource;

    protected $outletFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataOutletFactory;

    private $storeManager;

    public function __construct(
        SupermaxPosOutlet $resource,
        SupermaxPosOutletFactory $outletFactory,
        Data\OutletInterface $dataOutletFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->outletFactory = $outletFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataOutletFactory = $dataOutletFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\OutletInterface $outlet)
    {
        if ($outlet->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $outlet->setStoreId($storeId);
        }
        try {
            $this->resource->save($outlet);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the outlet data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $outlet;
    }

    public function getById($outletId)
    {
		$outlet = $this->outletFactory->create();
        $outlet->load($outletId);
        if (!$outlet->getId()) {
            throw new NoSuchEntityException(__('Outlet Data with id "%1" does not exist.', $outletId));
        }
        return $outlet;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\OutletInterface $outlet)
    {
        try {
            $this->resource->delete($outlet);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the outlet data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($outletId)
    {
        return $this->delete($this->getById($outletId));
    }
}