<?php

namespace Embitel\Facade\Api\Data;

interface FacadeHistoryInterface
{

    const SKU = 'sku';

    const HITS = 'hits';

    /**
     * @return String|null
     */
    public function getSku(): ?String;

    /**
     * @param String $sku
     * @return FacadeHistoryInterface
     */
    public function setSku(String $sku): FacadeHistoryInterface;

    /**
     * @return Int|null
     */
    public function getHits(): ?Int;

    /**
     * @param Int $count
     * @return FacadeHistoryInterface
     */
    public function setHits(Int $count): FacadeHistoryInterface;

}
