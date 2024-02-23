<?php

namespace Ibo\Order\Plugin\Admin\Order;

class Grid
{
    public function beforeLoad(\Magento\Sales\Model\ResourceModel\Order\Grid\Collection $subject)
    {
        $subject->join(
            ['customer_entity' => 'customer_entity'],
            'customer_entity.entity_id = main_table.customer_id',
            'customer_entity.mobilenumber'
        );
            
    }
}