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

class MonthList implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
    \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxOldSalesOrders\Collection $repository,
    \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->repository = $repository;
        $this->helper = $helper;
      }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $outletIDS = $this->helper->assignedOutletIds();
        $outlets = $this->repository->getData();
      
        $options = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $sql = 'SELECT DISTINCT show_by FROM ah_supermax_old_sales_order';
        $data = $connection->fetchAll($sql);
       
        foreach ($data as $outlet) {
            $options[] = ['value' => $outlet['show_by'], 'label' => $outlet['show_by']];
        }
        
        
        return $options;
    }
}