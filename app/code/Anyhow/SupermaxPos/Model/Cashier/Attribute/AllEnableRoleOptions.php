<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Cashier\Attribute;

class AllEnableRoleOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->resource = $resourceConnection;
    }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('ah_supermax_pos_user_role'); 

        $cashier_roles = $connection->query("SELECT * FROM $tableName WHERE status = '1'")->fetchAll();

        $result[] = ['value' => 0, 'label' => '--- Please Select ---'];
        if(!empty($cashier_roles)){
            foreach ($cashier_roles as $role) {
                $result[] = ['value' => $role['pos_user_role_id'], 'label' => $role['title']];
            }
        }
        return $result;
    }
}