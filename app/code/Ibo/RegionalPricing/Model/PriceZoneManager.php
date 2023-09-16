<?php
namespace Ibo\RegionalPricing\Model;

class PriceZoneManager implements
    \Ibo\RegionalPricing\Model\PriceZoneManagerInterface
{
    /**
     * Default Price Zone
     *
     * @var string
     */
    protected $currentZone = null;

    /**
     * Set current default store
     *
     * @param string $priceZone
     * @return void
     */
    public function setCurrentPriceZone($priceZone) {
        $this->currentPriceZone = $priceZone;
    }

    /**
     * get current default store
     *
     * @return string
     */
    public function getCurrentPriceZone() {
        return $this->currentPriceZone;
    }
}
