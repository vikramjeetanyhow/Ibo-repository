<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Cashier\Attribute\Stores;

class AllStoresOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(\Magento\Store\Api\StoreRepositoryInterface $repository) {
        $this->repository = $repository;
      }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $stores = $this->repository->getList();
        foreach ($stores as $store) {
            if($store->getId() == 0 || $store->getWebsiteId() == 0){
                continue;
            }
            $result[] = ['value' => $store->getId(), 'label' => $store->getName()];
        }
        return $result;
    }
}