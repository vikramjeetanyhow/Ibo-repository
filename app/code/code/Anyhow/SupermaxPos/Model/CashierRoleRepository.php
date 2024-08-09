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
use Anyhow\SupermaxPos\Api\CashierRoleRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole as SupermaxUserRole;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole\CollectionFactory as SupermaxUserRoleCollectionFactory;

class CashierRoleRepository implements CashierRoleRepositoryInterface
{
    protected $resource;

    protected $cashierRoleFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataCashierRoleFactory;

    private $storeManager;

    public function __construct(
        SupermaxUserRole $resource,
        SupermaxUserRoleFactory $cashierRoleFactory,
        Data\CashierRoleInterface $dataCashierRoleFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->cashierRoleFactory = $cashierRoleFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataCashierRoleFactory = $dataCashierRoleFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\CashierRoleInterface $cashierRole)
    {
        if ($cashierRole->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $cashierRole->setStoreId($storeId);
        }
        try {
            $this->resource->save($cashierRole);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the cashier role data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $cashierRole;
    }

    public function getById($cashierRoleId)
    {
		$cashierRole = $this->cashierRoleFactory->create();
        $cashierRole->load($cashierRoleId);
        if (!$cashierRole->getId()) {
            throw new NoSuchEntityException(__('Cashier Role Data with id "%1" does not exist.', $cashierRoleId));
        }
        return $cashierRole;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\CashierRoleInterface $cashierRole)
    {
        try {
            $this->resource->delete($cashierRole);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the cashier role data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($cashierRoleId)
    {
        return $this->delete($this->getById($cashierRoleId));
    }
}