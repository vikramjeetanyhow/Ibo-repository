<?php


namespace Ibo\TierPrice\Api;

/**
 * @api
 */
interface ProductTierPriceManagementInterface
{
    /**
     * Get product TierPrice in prices array.
     *
     * @api
     * @param string $sku
     * @param int $customer_group_id
     * @param string $prize_zone     
     * @return mixed
     * @throws \Magento\Framework\Exception\NotFoundException     
     */
    public function getTierPrice($sku,$customer_group_id,$prize_zone);
}
