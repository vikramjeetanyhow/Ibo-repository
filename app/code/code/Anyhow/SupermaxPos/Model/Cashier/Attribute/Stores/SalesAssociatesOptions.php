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

class SalesAssociatesOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->resource = $resourceConnection;
        $this->helper = $helper;
    }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $outletIDS = $this->helper->assignedOutletIds();
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('ah_supermax_pos_user'); 
        $cashierData = $connection->query("SELECT * FROM $tableName")->fetchAll();
        $result[] = ['value' => 0, 'label' => "-"];
        if(!empty($cashierData)){
            foreach ($cashierData as $data) {
                if($outletIDS != 0 && in_array($data['pos_outlet_id'], $outletIDS)) {
                    $result[] = ['value' => $data['pos_user_id'], 'label' => ($data['firstname'] . " " . $data['lastname'] . " - " . $data['username'])];
                } elseif($outletIDS == 0) {
                    $result[] = ['value' => $data['pos_user_id'], 'label' => ($data['firstname'] . " " . $data['lastname'] . " - " . $data['username'])];
                }
            }
        }
        return $result;
    }
}