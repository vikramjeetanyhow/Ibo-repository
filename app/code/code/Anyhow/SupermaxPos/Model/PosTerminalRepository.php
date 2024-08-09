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
use Anyhow\SupermaxPos\Api\PosTerminalRepositoryInterface;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal as SupermaxPosTerminal;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal\CollectionFactory as SupermaxPosTerminalCollectionFactory;

class PosTerminalRepository implements PosTerminalRepositoryInterface
{
    protected $resource;

    protected $priceReductionFactory;

    protected $dataObjectHelper;

    protected $dataObjectProcessor;

    protected $dataPosTerminalFactory;

    private $storeManager;

    public function __construct(
        SupermaxPosTerminal $resource,
        SupermaxPosTerminalFactory $priceReductionFactory,
        Data\PosTerminalInterface $dataPosTerminalFactory,
        DataObjectHelper $dataObjectHelper,
		DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
		$this->priceReductionFactory = $priceReductionFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataPosTerminalFactory = $dataPosTerminalFactory;
		$this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
    }

    public function save(\Anyhow\SupermaxPos\Api\Data\PosTerminalInterface $priceReduction)
    {
        if ($priceReduction->getStoreId() === null) {
            $storeId = $this->storeManager->getStore()->getId();
            $priceReduction->setStoreId($storeId);
        }
        try {
            $this->resource->save($priceReduction);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the price reduction data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $priceReduction;
    }

    public function getById($priceReductionId)
    {
		$priceReduction = $this->priceReductionFactory->create();
        $priceReduction->load($priceReductionId);
        if (!$priceReduction->getId()) {
            throw new NoSuchEntityException(__('Price reduction data with id "%1" does not exist.', $priceReductionId));
        }
        return $priceReduction;
    }
	
    public function delete(\Anyhow\SupermaxPos\Api\Data\PosTerminalInterface $priceReduction)
    {
        try {
            $this->resource->delete($priceReduction);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the price reduction data: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    public function deleteById($priceReductionId)
    {
        return $this->delete($this->getById($priceReductionId));
    }
}