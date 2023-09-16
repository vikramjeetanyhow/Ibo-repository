<?php
namespace Ibo\RegionalPricing\Model;

/**
 * Price Zone manager interface
 */
interface PriceZoneManagerInterface
{
    const CONTEXT_PRICE_ZONE = 'price_zone';

    /**
     * Set current default store
     *
     * @param string $priceZone
     * @return void
     */
    public function setCurrentPriceZone($priceZone);

    /**
     * get current default store
     *
     * @return string
     */
    public function getCurrentPriceZone();
}
