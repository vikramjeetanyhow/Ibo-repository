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
use Anyhow\SupermaxPos\Api\CashierRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser as SupermaxUser;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\CollectionFactory as SupermaxUserCollectionFactory;

class CashierRepository implements CashierRepositoryInterface
{
    protected $resource;

    protected $cashierFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataCashierFactory;

    private $storeManager;

    public function __construct(
        SupermaxUser $resource,
        SupermaxUserFactory $cashierFactory,
        Data\CashierInterface $dataCashierFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->cashierFactory = $cashierFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataCashierFactory = $dataCashierFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\CashierInterface $cashier)
    {
        if ($cashier->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $cashier->setStoreId($storeId);
        }
        try {
            $this->resource->save($cashier);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the cashier data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $cashier;
    }

    public function getById($cashierId)
    {
		$cashier = $this->cashierFactory->create();
        $cashier->load($cashierId);
        if (!$cashier->getId()) {
            throw new NoSuchEntityException(__('Cashier Data with id "%1" does not exist.', $cashierId));
        }
        return $cashier;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\CashierInterface $cashier)
    {
        try {
            $this->resource->delete($cashier);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the cashier data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($cashierId)
    {
        return $this->delete($this->getById($cashierId));
    }
}