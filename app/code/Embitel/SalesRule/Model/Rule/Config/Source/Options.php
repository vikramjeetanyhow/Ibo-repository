<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\SalesRule\Model\Rule\Config\Source;

use Magento\Framework\App\ResourceConnection;

/**
 * @api
 * @since 100.0.2
 */
class Options implements \Magento\Framework\Option\ArrayInterface
{
    private ResourceConnection $resourceConnection;

    private $stores = null;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(\Magento\Framework\App\ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function getDefaultOptions() {
        return [['value' => 'all', 'label' => __('All')], ['value' => 'online', 'label' => __('Online')]];
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->stores === null) {

            foreach ($this->getDefaultOptions() as $option) {
                $this->stores[] = $option;
            }

            $sql = "SELECT store_id from ah_supermax_pos_outlet";
            $result = $this->resourceConnection->getConnection()->fetchAll($sql);
            if (count($result)) {
               $stores = array_column($result, 'store_id');
                foreach ($stores as $store) {
                    $this->stores[] = [
                        'value' => $store,
                        'label' => $store
                    ];
                }
            }
        }

        return $this->stores;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return $this->toOptionArray();
    }
}
